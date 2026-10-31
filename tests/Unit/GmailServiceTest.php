<?php

namespace Tests\Unit;

use App\Shared\Gmail\Exceptions\GmailException;
use App\Shared\Gmail\GMailable;
use App\Shared\Gmail\GmailService;
use App\Shared\Gmail\Support\Base64Url;
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

    protected MockInterface $attachments;

    protected GmailService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $client = new Client;
        $gmail = new Gmail($client);
        $this->messages = Mockery::mock(Gmail\Resource\UsersMessages::class);
        $this->attachments = Mockery::mock(Gmail\Resource\UsersMessagesAttachments::class);
        $gmail->users_messages = $this->messages;
        $gmail->users_messages_attachments = $this->attachments;

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
            ->with('me', Mockery::on(fn (Message $m) => str_contains(Base64Url::decode($m->getRaw()), 'Subject: Hello')))
            ->andReturn($sent);

        $mailable = (new GMailable('me@example.com', 'Hello'))->to('you@example.com')->plain('Hi');

        $this->assertSame('sent-1', $this->service->send($mailable));
    }

    public function test_fetch_page_passes_page_token_and_returns_the_next_one(): void
    {
        $list = new ListMessagesResponse;
        $list->setMessages([MessageFactory::message('m2', MessageFactory::part('text/plain'))]);
        $list->setNextPageToken('page-3');
        $list->setResultSizeEstimate(42);

        $this->messages->shouldReceive('listUsersMessages')
            ->once()
            ->with('me', ['q' => 'is:unread', 'maxResults' => 50, 'pageToken' => 'page-2'])
            ->andReturn($list);
        $this->messages->shouldReceive('get')->andReturn(MessageFactory::message('m2', MessageFactory::part('text/plain', 'Hi', headers: [
            'Message-ID' => '<m2@mail.gmail.com>',
            'Date' => 'Sat, 31 Oct 2026 10:00:00 +0000',
        ]), ['INBOX'], 't2'));

        $page = $this->service->fetchPage(['is' => 'unread'], 50, 'page-2');

        $this->assertSame('page-3', $page['nextPageToken']);
        $this->assertSame(42, $page['resultSizeEstimate']);
        $this->assertSame('t2', $page['messages'][0]['threadId']);
        $this->assertSame(['INBOX'], $page['messages'][0]['labelIds']);
        $this->assertSame('<m2@mail.gmail.com>', $page['messages'][0]['messageId']);
        $this->assertSame('Sat, 31 Oct 2026 10:00:00 +0000', $page['messages'][0]['date']);
    }

    public function test_fetch_messages_accepts_max_results(): void
    {
        $this->messages->shouldReceive('listUsersMessages')
            ->once()
            ->with('me', ['maxResults' => 100])
            ->andReturn(new ListMessagesResponse);

        $this->assertSame([], $this->service->fetchMessages([], 100));
    }

    public function test_get_attachment_returns_decoded_bytes(): void
    {
        $body = new Gmail\MessagePartBody;
        $body->setData(Base64Url::encode("%PDF-1.4\xff\xfe binary"));

        $this->attachments->shouldReceive('get')->once()->with('me', 'm1', 'att-1')->andReturn($body);

        $this->assertSame("%PDF-1.4\xff\xfe binary", $this->service->getAttachment('m1', 'att-1'));
    }

    public function test_mark_as_unread_and_modify_labels(): void
    {
        $this->messages->shouldReceive('modify')
            ->once()
            ->with('me', 'm1', Mockery::on(fn (ModifyMessageRequest $r) => $r->getAddLabelIds() === ['UNREAD'] && $r->getRemoveLabelIds() === []))
            ->andReturn(MessageFactory::message('m1', MessageFactory::part('text/plain'), ['UNREAD', 'INBOX']));

        $this->assertTrue($this->service->markAsUnread('m1'));

        $this->messages->shouldReceive('modify')
            ->once()
            ->with('me', 'm1', Mockery::on(fn (ModifyMessageRequest $r) => $r->getAddLabelIds() === ['Label_1'] && $r->getRemoveLabelIds() === ['INBOX']))
            ->andReturn(MessageFactory::message('m1', MessageFactory::part('text/plain'), ['Label_1']));

        $this->assertSame(['Label_1'], $this->service->modifyLabels('m1', ['Label_1'], ['INBOX']));
    }

    public function test_trash_and_untrash(): void
    {
        $this->messages->shouldReceive('trash')->once()->with('me', 'm1')
            ->andReturn(MessageFactory::message('m1', MessageFactory::part('text/plain'), ['TRASH']));
        $this->messages->shouldReceive('untrash')->once()->with('me', 'm1')
            ->andReturn(MessageFactory::message('m1', MessageFactory::part('text/plain'), ['INBOX']));

        $this->assertTrue($this->service->trash('m1'));
        $this->assertTrue($this->service->untrash('m1'));
    }

    public function test_replies_are_sent_on_the_thread(): void
    {
        $sent = new Message;
        $sent->setId('sent-2');

        $this->messages->shouldReceive('send')
            ->once()
            ->with('me', Mockery::on(fn (Message $m) => $m->getThreadId() === 'thread-1'))
            ->andReturn($sent);

        $mailable = (new GMailable('me@example.com', 'Re: Hello'))
            ->to('you@example.com')
            ->inReplyTo('<abc@mail.gmail.com>', 'thread-1')
            ->plain('Thanks');

        $this->assertSame('sent-2', $this->service->send($mailable));
    }

    private function serviceFor(?string $account): GmailService
    {
        $client = new Client;
        $gmail = new Gmail($client);
        $gmail->users_messages = $this->messages;

        return new GmailService($client, $gmail, $account);
    }

    public function test_send_rejects_a_mailable_set_to_another_account(): void
    {
        $this->messages->shouldNotReceive('send');

        $mailable = (new GMailable('me@example.com', 'Hi'))->account('sales')->to('you@example.com')->plain('Hi');

        $this->expectException(GmailException::class);
        $this->expectExceptionMessage('account [sales] but was sent through account [support]');

        $this->serviceFor('support')->send($mailable);
    }

    public function test_send_accepts_a_mailable_for_the_same_or_an_unknown_account(): void
    {
        $sent = new Message;
        $sent->setId('sent-3');
        $this->messages->shouldReceive('send')->twice()->andReturn($sent);

        $mailable = (new GMailable('me@example.com', 'Hi'))->account('sales')->to('you@example.com')->plain('Hi');

        $this->assertSame('sales', $this->serviceFor('sales')->getAccount());
        $this->assertSame('sent-3', $this->serviceFor('sales')->send($mailable));
        $this->assertSame('sent-3', $this->serviceFor(null)->send($mailable));
    }
}
