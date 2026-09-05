<?php

namespace Tests\Feature;

use App\Services\FeedParser;
use App\Services\SafeFeedHttp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FeedFetchTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_is_idempotent_interval_aware_and_errors_are_redacted(): void
    {
        $id = (string) Str::ulid();
        DB::table('feed_sources')->insert(['id' => $id, 'name' => 'Feed', 'url' => 'https://example.com/feed', 'url_hash' => hash('sha256', 'https://example.com/feed'), 'is_enabled' => true, 'fetch_interval_minutes' => 60]);
        $xml = '<rss><channel><item><guid>1</guid><title>ニュース</title><link>https://example.com/news</link><description><![CDATA[<p>本文</p><script>alert(1)</script>]]></description></item></channel></rss>';
        $this->mock(SafeFeedHttp::class)->shouldReceive('get')->twice()->andReturn($xml);
        $this->artisan('feeds:fetch')->assertSuccessful();
        $this->artisan('feeds:fetch')->assertSuccessful();
        $this->artisan('feeds:fetch --force')->assertSuccessful();
        $this->assertDatabaseCount('feed_items', 1);
        $this->assertStringNotContainsString('script', DB::table('feed_items')->value('summary_html'));
        $this->mock(SafeFeedHttp::class)->shouldReceive('get')->once()->andThrow(new \RuntimeException('secret-token'));
        $this->artisan('feeds:fetch --force')->assertFailed();
        $this->assertStringNotContainsString('secret-token', DB::table('feed_sources')->value('last_error'));
        $this->assertDatabaseCount('feed_items', 1);
    }

    public function test_dns_rebinding_mixed_answers_and_non_public_destinations_are_rejected(): void
    {
        $http = new SafeFeedHttp;
        foreach (['127.0.0.1', '10.1.1.1', '169.254.169.254', '192.168.1.1', '100.64.0.1', '224.0.0.1', '::1', '::ffff:127.0.0.1', 'fe80::1', '2001:db8::1', '64:ff9b::7f00:1'] as $ip) {
            $this->assertFalse($http->publicIp($ip), $ip);
        }
        $this->assertTrue($http->publicIp('8.8.8.8'));
        $mock = \Mockery::mock(SafeFeedHttp::class)->makePartial();
        $mock->shouldReceive('resolve')->with('example.com')->once()->andReturn(['8.8.8.8', '127.0.0.1']);
        $mock->shouldNotReceive('download');
        $this->expectException(\RuntimeException::class);
        $mock->get('https://example.com/feed');
    }

    public function test_validated_ip_is_passed_to_transport_and_bad_schemes_are_rejected(): void
    {
        $mock = \Mockery::mock(SafeFeedHttp::class)->makePartial();
        $mock->shouldReceive('resolve')->with('example.com')->once()->andReturn(['8.8.8.8']);
        $mock->shouldReceive('download')->with('https://example.com/feed', 'example.com', '8.8.8.8')->once()->andReturn('ok');
        $this->assertSame('ok', $mock->get('https://example.com/feed'));
        foreach (['http://example.com', 'file:///etc/passwd', 'https://user:pass@example.com', 'https://example.com:8080/feed'] as $url) {
            try {
                $mock->get($url);
                $this->fail('Unsafe URL accepted');
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_atom_is_parsed_and_dtd_malformed_xml_and_oversize_are_rejected(): void
    {
        $parser = new FeedParser;
        $items = $parser->parse('<feed xmlns="http://www.w3.org/2005/Atom"><entry><id>x</id><title>Atom</title><link href="https://example.com/a"/><updated>2026-09-05T00:00:00Z</updated></entry><entry><title>Bad</title><link href="javascript:alert(1)"/></entry></feed>');
        $this->assertCount(1, $items);
        $this->assertSame('https://example.com/a', $items[0]['url']);
        foreach (['<!DOCTYPE rss [<!ENTITY x SYSTEM "file:///etc/passwd">]><rss><channel>&x;</channel></rss>', '<rss>', str_repeat('x', 2097153)] as $xml) {
            try {
                $parser->parse($xml);
                $this->fail('Unsafe XML accepted');
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
