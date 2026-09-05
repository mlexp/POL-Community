<?php

namespace App\Services;

use App\Support\ContentSanitizer;
use App\Support\MediaUrl;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

class FeedParser
{
    /** @return list<array<string, mixed>> */
    public function parse(string $xml): array
    {
        if (strlen($xml) > config('content.feed_max_bytes') || preg_match('/<!\s*(DOCTYPE|ENTITY)/i', $xml)) {
            throw new RuntimeException('外部実体・DTDまたはサイズ超過のXMLを拒否しました。');
        }
        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        try {
            if (! $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS) || $doc->doctype !== null) {
                throw new RuntimeException('RSS XMLを解析できませんでした。');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xp = new DOMXPath($doc);
        $nodes = $xp->query('/*[local-name()="rss"]/*[local-name()="channel"]/*[local-name()="item"] | /*[local-name()="feed"]/*[local-name()="entry"] | /*[local-name()="RDF"]/*[local-name()="item"]');
        if ($nodes === false || ! in_array($doc->documentElement?->localName, ['rss', 'feed', 'RDF'], true)) {
            throw new RuntimeException('RSSまたはAtom形式ではありません。');
        }
        $items = [];
        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement || count($items) >= 100) {
                continue;
            }
            $value = fn (string $name): string => trim((string) $xp->evaluate('string(./*[local-name()="'.$name.'"][1])', $node));
            $url = $value('link');
            if ($node->localName === 'entry') {
                $url = trim((string) $xp->evaluate('string(./*[local-name()="link"][@rel="alternate" or not(@rel)][1]/@href)', $node));
            }
            if (! app(MediaUrl::class)->https($url) || $value('title') === '') {
                continue;
            }
            $external = $value('guid') ?: ($value('id') ?: $url);
            $date = $value('pubDate') ?: ($value('published') ?: ($value('updated') ?: $value('date')));
            try {
                $published = $date === '' ? null : CarbonImmutable::parse($date)->utc();
            } catch (\Exception) {
                $published = null;
            }
            $summary = $value('description') ?: $value('summary');
            $items[] = ['external_id' => mb_substr($external, 0, 512), 'external_id_hash' => hash('sha256', $external), 'title' => mb_substr(strip_tags($value('title')), 0, 500), 'url' => $url, 'summary_html' => app(ContentSanitizer::class)->sanitize(mb_substr($summary, 0, 10000)), 'author' => mb_substr(strip_tags($value('author') ?: $value('creator')), 0, 200), 'published_at' => $published];
        }

        return $items;
    }
}
