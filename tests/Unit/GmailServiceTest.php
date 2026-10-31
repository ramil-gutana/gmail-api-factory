<?php

namespace Tests\Unit;

use App\Shared\Gmail\GmailService;
use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\ListMessagesResponse;
use Google\Service\Gmail\Message;
use Google\Service\Gmail\ModifyMessageRequest;
use Mockery;
use Mockery\MockInterface;
use Tests\Fixtures\MessageFactory;
use Tests\TestCase;

class GmailServiceTest extends TestCase
{
    protected MockInterface $messages;

    protected GmailService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $client = new Client;
        $gmail = new Gmail($client);
        $this->messages = Mockery::mock(Gmail\Resource\UsersMessages::class);
        $gmail->users_messages = $this->messages;

        $this->service = new GmailService($client, $gmail);
    }

    private function expectQuery(string $query): void
    {
        $this->messages->shouldReceive('listUsersMessages')
            ->once()
            ->with('me', Mockery::on(fn ($params) => $params['q'] === $query))
            ->andReturn(new ListMessagesResponse);
    }

    public function test_it_builds_simple_filters(): void
    {
        $this->expectQuery('from:alice@example.com is:unread');

        $this->assertSame([], $this->service->fetchMessages(['from' => 'alice@example.com', 'is' => 'unread']));
    }

    public function test_array_filters_are_or_grouped(): void
    {
        $this->expectQuery('(from:a@example.com OR from:b@example.com)');

        $this->service->fetchMessages(['from' => ['a@example.com', 'b@example.com']]);
    }

    public function test_has_filters_use_gmail_has_operator(): void
    {
        $this->expectQuery('has:attachment has:nouserlabels');

        $this->service->fetchMessages(['has_attachment' => true, 'has_nouserlabels' => true]);
    }

    public function test_values_with_spaces_are_quoted(): void
    {
        $this->expectQuery('subject:"Monthly report"');

        $this->service->fetchMessages(['subject' => 'Monthly report']);
    }

    public function test_it_parses_headers_bodies_and_attachments(): void
    {
        $payload = MessageFactory::part('multipart/mixed', null, [
            MessageFactory::part('multipart/alternative', null, [
                MessageFactory::part('text/plain', "Hello\n\n\n  World  "),
                MessageFactory::part('text/html', '<p>Hello World</p>'),
            ]),
            MessageFactory::part('application/pdf', null, [], 'invoice.pdf', 'att-1'),
        ], headers: ['From' => 'alice@example.com', 'To' => 'bob@example.com', 'Subject' => 'Hi']);

        $list = new ListMessagesResponse;
        $list->setMessages([MessageFactory::message('m1', $payload)]);

        $this->messages->shouldReceive('listUsersMessages')->andReturn($list);
        $this->messages->shouldReceive('get')->with('me', 'm1', ['format' => 'full'])->andReturn(MessageFactory::message('m1', $payload));

        $result = $this->service->fetchMessages();

        $this->assertCount(1, $result);
        $this->assertSame('m1', $result[0]['id']);
        $this->assertSame('alice@example.com', $result[0]['from']);
        $this->assertSame('Hi', $result[0]['subject']);
        $this->assertNull($result[0]['cc']);
        $this->assertSame('Hello'.PHP_EOL.PHP_EOL.'World', $result[0]['body']['text']);
        $this->assertSame('<p>Hello World</p>', $result[0]['body']['html']);
        $this->assertSame([[
            'filename' => 'invoice.pdf',
            'mimeType' => 'application/pdf',
            'attachmentId' => 'att-1',
        ]], $result[0]['body']['attachments']);
    }

    public function test_mark_as_read_removes_unread_label(): void
    {
        $this->messages->shouldReceive('modify')
            ->once()
            ->with('me', 'm1', Mockery::on(fn (ModifyMessageRequest $r) => $r->getRemoveLabelIds() === ['UNREAD']))
            ->andReturn(MessageFactory::message('m1', MessageFactory::part('text/plain'), []));

        // A message with no labels left must still count as a success.
        $this->assertTrue($this->service->markAsRead('m1'));
    }

    public function test_mark_as_read_reports_failure_when_still_unread(): void
    {
        $this->messages->shouldReceive('modify')
            ->andReturn(MessageFactory::message('m1', MessageFactory::part('text/plain'), ['UNREAD', 'INBOX']));

        $this->assertFalse($this->service->markAsRead('m1'));
    }

    public function test_send_returns_the_message_id(): void
    {
        $sent = new Message;
        $sent->setId('sent-1');

        $this->messages->shouldReceive('send')
            ->once()
            ->with('me', Mockery::on(fn (Message $m) => str_contains(base64_decode(strtr($m->getRaw(), '-_', '+/')), 'Subject: Hello')))
            ->andReturn($sent);

        $mailable = (new \App\Shared\Gmail\GMailable('me@example.com', 'Hello'))->to('you@example.com')->plain('Hi');

        $this->assertSame('sent-1', $this->service->send($mailable));
    }
}
