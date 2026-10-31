<?php

namespace App\Shared\Gmail;

use App\Shared\Gmail\Interfaces\GmailServiceInterface;
use Google\Client;
use Google\Service\Exception;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Google\Service\Gmail\MessagePart;

class GmailService implements GmailServiceInterface
{
    protected Gmail $gmail;

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

    /**
     * Filters that are Gmail "has:<value>" operators rather than "<name>:<value>" pairs.
     */
    protected array $hasFilters = [
        'has_attachment' => 'attachment',
        'has_userlabels' => 'userlabels',
        'has_nouserlabels' => 'nouserlabels',
    ];

    public function __construct(protected Client $client, ?Gmail $gmail = null)
    {
        $this->gmail = $gmail ?? new Gmail($this->client);
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
            'q' => $this->generateQueryString($filters),
            'maxResults' => $maxResults,
            'pageToken' => $pageToken,
        ], fn ($value) => $value !== null && $value !== '');

        $messages = $this->gmail->users_messages->listUsersMessages('me', $params);

        $result = [];

        foreach ($messages->getMessages() ?? [] as $message) {
            $result[] = $this->getMessage($message->getId());
        }

        return [
            'messages' => $result,
            'nextPageToken' => $messages->getNextPageToken(),
            'resultSizeEstimate' => $messages->getResultSizeEstimate(),
        ];
    }

    /**
     * @throws Exception
     */
    public function getMessage(string $messageId): array
    {
        $message = $this->gmail->users_messages->get('me', $messageId, ['format' => 'full']);
        $payload = $message->getPayload();

        return [
            'id' => $message->getId(),
            'threadId' => $message->getThreadId(),
            'labelIds' => $message->getLabelIds() ?? [],
            'snippet' => $message->getSnippet(),
            'messageId' => $this->getHeader($payload, 'Message-ID'),
            'date' => $this->getHeader($payload, 'Date'),
            'from' => $this->getHeader($payload, 'From'),
            'to' => $this->getHeader($payload, 'To'),
            'subject' => $this->getHeader($payload, 'Subject'),
            'cc' => $this->getHeader($payload, 'Cc'),
            'bcc' => $this->getHeader($payload, 'Bcc'),
            'body' => $this->getMessagePart($payload),
        ];
    }

    /**
     * Download an attachment listed in a message's body.attachments and return its raw bytes.
     *
     * @throws Exception
     */
    public function getAttachment(string $messageId, string $attachmentId): string
    {
        $attachment = $this->gmail->users_messages_attachments->get('me', $messageId, $attachmentId);

        return $this->decode((string) $attachment->getData());
    }

    private function getHeader(MessagePart $payload, $name)
    {
        foreach ($payload->getHeaders() ?? [] as $header) {
            if (strtolower($header->getName()) === strtolower($name)) {
                return $header->getValue();
            }
        }

        return null;
    }

    private function getMessagePart(MessagePart $payload): array
    {
        $result = [
            'text' => '',
            'html' => '',
            'attachments' => [],
        ];

        $processPart = function (MessagePart $part, &$result) use (&$processPart) {
            $mimeType = $part->getMimeType();
            $body = $part->getBody();
            $bodyData = $body?->getData() ? $this->decode($body->getData()) : '';

            if ($part->getFilename() && $body?->getAttachmentId()) {
                $result['attachments'][] = [
                    'filename' => $part->getFilename(),
                    'mimeType' => $mimeType,
                    'attachmentId' => $body->getAttachmentId(),
                ];
            }

            if ($bodyData && $mimeType === 'text/plain') {
                $plainText = str_replace(['\\r\\n', '\\n'], PHP_EOL, $bodyData);
                $plainText = preg_replace("/\n{2,}/", "\n\n", $plainText);
                $plainText = preg_replace('/^[ \t]+|[ \t]+$/m', '', $plainText);
                $result['text'] .= str_replace(["\r\n", "\r", "\n"], PHP_EOL, trim($plainText));
            }

            if ($bodyData && $mimeType === 'text/html') {
                $result['html'] .= $bodyData;
            }

            if ($part->getParts()) {
                foreach ($part->getParts() as $subPart) {
                    $processPart($subPart, $result);
                }
            }
        };

        $processPart($payload, $result);

        return $result;
    }

    private function generateQueryString($filters): string
    {
        $newFilter = array_merge($this->filters, $filters);
        $newFilter = array_filter($newFilter, fn ($value) => ! empty($value));
        $queryString = [];
        foreach ($newFilter as $name => $value) {
            if (isset($this->hasFilters[$name])) {
                $queryString[] = 'has:'.$this->hasFilters[$name];

                continue;
            }

            $subFilters = [];
            foreach ((array) $value as $subValue) {
                $subFilters[] = "$name:".$this->quote((string) $subValue);
            }
            $queryString[] = count($subFilters) > 1 ? '('.implode(' OR ', $subFilters).')' : $subFilters[0];
        }

        return implode(' ', $queryString);
    }

    private function quote(string $value): string
    {
        if (! preg_match('/[\s()"]/', $value)) {
            return $value;
        }

        return '"'.str_replace('"', '', $value).'"';
    }

    private function decode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * @throws \Throwable
     */
    public function send(GMailable $mailable): string|array
    {
        $raw = $mailable->render();
        $encodedMessage = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

        $message = new Message;
        $message->setRaw($encodedMessage);

        if ($mailable->threadId) {
            $message->setThreadId($mailable->threadId);
        }

        try {
            $sent = $this->gmail->users_messages->send('me', $message);

            return $sent->getId();
        } catch (Exception $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * @throws Exception
     */
    public function markAsRead(string $messageId): bool
    {
        $mods = new Gmail\ModifyMessageRequest;

        $mods->setRemoveLabelIds(['UNREAD']);

        $message = $this->gmail->users_messages->modify('me', $messageId, $mods);

        return ! in_array('UNREAD', $message->getLabelIds() ?? [], true);
    }

    /**
     * @throws Exception
     */
    public function markAsUnread(string $messageId): bool
    {
        return in_array('UNREAD', $this->modifyLabels($messageId, ['UNREAD']), true);
    }

    /**
     * Add and/or remove label IDs on a message and return its resulting label IDs.
     *
     * @throws Exception
     */
    public function modifyLabels(string $messageId, array $add = [], array $remove = []): array
    {
        $mods = new Gmail\ModifyMessageRequest;
        $mods->setAddLabelIds($add);
        $mods->setRemoveLabelIds($remove);

        return $this->gmail->users_messages->modify('me', $messageId, $mods)->getLabelIds() ?? [];
    }

    /**
     * @throws Exception
     */
    public function trash(string $messageId): bool
    {
        return in_array('TRASH', $this->gmail->users_messages->trash('me', $messageId)->getLabelIds() ?? [], true);
    }

    /**
     * @throws Exception
     */
    public function untrash(string $messageId): bool
    {
        return ! in_array('TRASH', $this->gmail->users_messages->untrash('me', $messageId)->getLabelIds() ?? [], true);
    }
}
