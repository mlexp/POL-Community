<?php

namespace App\Services;

use App\Support\MediaUrl;
use RuntimeException;
use Symfony\Component\HttpFoundation\IpUtils;

class SafeFeedHttp
{
    /** @return list<string> */
    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false) {
            throw new RuntimeException('DNS解決に失敗しました。');
        }
        $ips = [];
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip)) {
                $ips[] = $ip;
            }
        }

        return array_values(array_unique($ips));
    }

    public function publicIp(string $ip): bool
    {
        if (str_contains($ip, ':') && ! IpUtils::checkIp($ip, '2000::/3')) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            && ! IpUtils::checkIp($ip, ['0.0.0.0/8', '100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4', '::/96', '::ffff:0:0/96', '64:ff9b::/96', '64:ff9b:1::/48', '100::/64', '2001::/23', '2001:db8::/32', '3fff::/20', '2002::/16', 'fc00::/7', 'fe80::/10', 'ff00::/8']);
    }

    public function get(string $url): string
    {
        if (! app(MediaUrl::class)->https($url)) {
            throw new RuntimeException('HTTPSの標準ポートのURLのみ取得できます。');
        }
        $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));
        $ips = $this->resolve($host);
        if ($ips === []) {
            throw new RuntimeException('DNS解決に失敗しました。');
        }
        foreach ($ips as $ip) {
            if (! $this->publicIp($ip)) {
                throw new RuntimeException('公開IP以外への接続を拒否しました。');
            }
        }

        return $this->download($url, $host, $ips[0]);
    }

    public function download(string $url, string $host, string $ip): string
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('HTTPクライアントを開始できません。');
        }
        $body = '';
        $max = (int) config('content.feed_max_bytes');
        // Pin the validated address, retain hostname TLS verification, and disable proxies and redirects.
        curl_setopt_array($curl, [CURLOPT_RESOLVE => [$host.':443:'.(str_contains($ip, ':') ? '['.$ip.']' : $ip)], CURLOPT_PROXY => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_USERAGENT => 'POL-Community-Feed/1.0', CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, application/atom+xml, application/xml'], CURLOPT_WRITEFUNCTION => function ($handle, string $chunk) use (&$body, $max): int {
            if (strlen($body) + strlen($chunk) > $max) {
                return 0;
            }
            $body .= $chunk;

            return strlen($chunk);
        }]);
        try {
            $ok = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($ok === false || $status !== 200) {
                throw new RuntimeException('RSS取得失敗（通信、サイズ上限またはHTTP応答）。リダイレクトは許可していません。');
            }

            return $body;
        } finally {
            curl_close($curl);
        }
    }
}
