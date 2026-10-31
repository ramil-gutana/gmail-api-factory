<?php

namespace Tests\Unit;

use App\Shared\Gmail\Support\SearchQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SearchQueryTest extends TestCase
{
    public static function queries(): array
    {
        return [
            'empty' => [[], ''],
            'empty values are skipped' => [['from' => '', 'to' => null, 'is' => 'unread'], 'is:unread'],
            'pairs' => [['from' => 'a@example.com', 'in' => 'inbox'], 'from:a@example.com in:inbox'],
            'or group' => [['label' => ['work', 'urgent']], '(label:work OR label:urgent)'],
            'single item array' => [['label' => ['work']], 'label:work'],
            'has flags' => [['has_attachment' => true, 'has_userlabels' => 1], 'has:attachment has:userlabels'],
            'quoting' => [['subject' => 'Re: "Q3" (draft)'], 'subject:"Re: Q3 (draft)"'],
        ];
    }

    #[DataProvider('queries')]
    public function test_it_builds_gmail_queries(array $filters, string $expected): void
    {
        $this->assertSame($expected, SearchQuery::build($filters));
    }
}
