<?php

namespace App\Shared\Gmail;

use App\Shared\Gmail\Exceptions\GmailException;
use App\Shared\Gmail\Interfaces\GmailServiceInterface;
use App\Shared\Gmail\Support\Base64Url;
use App\Shared\Gmail\Support\MessageParser;
use App\Shared\Gmail\Support\SearchQuery;
use Google\Client;
use Google\Service\Exception;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Google\Service\Gmail\ModifyMessageRequest;
use Throwable;

class GmailService implements GmailServiceInterface
{
    /**
     * Gmail API alias for the authenticated user.
     */
    protected const string USER = 'me';

    protected Gmail $gmail;

    /**
     * Supported filters, in the order they appear in the search query.
     */
    public array $filters = [
        'from' => '',
        'to' => '',
        'cc' => '',
        'bcc' => '',
        'subject' => '',
        'label' => '',
        'has_attachment' => '',
        'filename' => '',
        'is' => '',
        'before' => '',
        'after' => '',
        'older' => '',
        'newer' => '',
        'older_than' => '',
        'newer_than' => '',
        'has_userlabels' => '',
        'has_nouserlabels' => '',
        'in' => '',
    ];

    public function __construct(protected Client $client, ?Gmail $gmail = null, protected ?string $account = null)
    {
        $this->gmail = $gmail ?? new Gmail($this->client);
    }

    /**
     * The configured account name this service is authenticated as, when built by GmailManager.
     */
    public function getAccount(): ?string
    {
        return $this->account;
    }

    /**
     * @throws Exception
     */
    public function fetchMessages(array $filters = [], int $maxResults = 10): array
    {
        return $this->fetchPage($filters, $maxResults)['messages'];
    }

    /**
     * Fetch one page of messages. Pass the returned nextPageToken back in to
     * get the following page; it is null on the last page.
     *
     * @return array{messages: array, nextPageToken: ?string, resultSizeEstimate: ?int}
     *
     * @throws Exception
     */
    public function fetchPage(array $filters = [], int $maxResults = 10, ?string $pageToken = null): array
    {
        $params = array_filter([
            'q' => SearchQuery::build(array_merge($this->filters, $filters)),
            'maxResults' => $maxResults,
            'pageToken' => $pageToken,
        ], fn ($value) => $value !== null && $value !== '');

        $response = $this->gmail->users_messages->listUsersMessages(self::USER, $params);

        return [
            'messages' => array_map(
                fn (Message $message) => $this->getMessage($message->getId()),
                $response->getMessages() ?? []
            ),
            'nextPageToken' => $response->getNextPageToken(),
            'resultSizeEstimate' => $response->getResultSizeEstimate(),
        ];
    }

    /**
     * @throws Exception
     */
    public function getMessage(string $messageId): array
    {
        return MessageParser::parse(
            $this->gmail->users_messages->get(self::USER, $messageId, ['format' => 'full'])
        );
    }

    /**
     * Download an attachment listed in a message's body.attachments and return its raw bytes.
     *
     * @throws Exception
     */
    public function getAttachment(string $messageId, string $attachmentId): string
    {
        $attachment = $this->gmail->users_messages_attachments->get(self::USER, $messageId, $attachmentId);

        return Base64Url::decode((string) $attachment->getData());
    }

    /**
     * Send the message and return its Gmail ID, or "Error: <reason>" when the API rejects it.
     *
     * @throws GmailException when the mailable is set to a different account
     * @throws Throwable when the message cannot be rendered
     */
    public function send(GMailable $mailable): string
    {
        if ($mailable->account !== null && $this->account !== null && $mailable->account !== $this->account) {
            throw GmailException::accountMismatch($mailable->account, $this->account);
        }
        $message = new Message;
        $message->setRaw(Base64Url::encode($mailable->render()));

        if ($mailable->threadId) {
            $message->setThreadId($mailable->threadId);
        }

        try {
            return $this->gmail->users_messages->send(self::USER, $message)->getId();
        } catch (Exception $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * @throws Exception
     */
    public function markAsRead(string $messageId): bool
    {
        return ! in_array('UNREAD', $this->modifyLabels($messageId, remove: ['UNREAD']), true);
    }

    /**
     * @throws Exception
     */
    public function markAsUnread(string $messageId): bool
    {
        return in_array('UNREAD', $this->modifyLabels($messageId, add: ['UNREAD']), true);
    }

    /**
     * Add and/or remove label IDs on a message and return its resulting label IDs.
     *
     * @throws Exception
     */
    public function modifyLabels(string $messageId, array $add = [], array $remove = []): array
    {
        $request = new ModifyMessageRequest;
        $request->setAddLabelIds($add);
        $request->setRemoveLabelIds($remove);

        return $this->gmail->users_messages->modify(self::USER, $messageId, $request)->getLabelIds() ?? [];
    }

    /**
     * @throws Exception
     */
    public function trash(string $messageId): bool
    {
        return in_array('TRASH', $this->gmail->users_messages->trash(self::USER, $messageId)->getLabelIds() ?? [], true);
    }

    /**
     * @throws Exception
     */
    public function untrash(string $messageId): bool
    {
        return ! in_array('TRASH', $this->gmail->users_messages->untrash(self::USER, $messageId)->getLabelIds() ?? [], true);
    }
}
