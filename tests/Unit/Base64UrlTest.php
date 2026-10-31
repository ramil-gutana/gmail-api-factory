<?php

namespace Tests\Unit;

use App\Shared\Gmail\Support\Base64Url;
use PHPUnit\Framework\TestCase;

class Base64UrlTest extends TestCase
{
    public function test_it_round_trips_binary_data_without_unsafe_characters(): void
    {
        $data = random_bytes(257)."\xfb\xff\xfe";
        $encoded = Base64Url::encode($data);

        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $encoded);
        $this->assertSame($data, Base64Url::decode($encoded));
    }
}
