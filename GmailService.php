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
    public function fetchMessages(array $filters = []): array
    {

        $queryFilter = $this->generateQueryString($filters);

        $messages = $this->gmail->users_messages->listUsersMessages('me', [
            'q' => $queryFilter,
            'maxResults' => 10,
        ]);

        $result = [];

        if ($messages->getMessages()) {
            foreach ($messages->getMessages() as $message) {
                $message = $this->gmail->users_messages->get('me', $message->id, ['format' => 'full']);
                $payload = $message->getPayload();

                $result[] = [
                    'id' => $message->id,
                    'from' => $this->getHeader($payload, 'From'),
                    'to' => $this->getHeader($payload, 'To'),
                    'subject' => $this->getHeader($payload, 'Subject'),
                    'cc' => $this->getHeader($payload, 'Cc'),
                    'bcc' => $this->getHeader($payload, 'Bcc'),
                    'body' => $this->getMessagePart($payload),
                ];
            }
        }

        return $result;
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
}
