<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class MediaUrl
{
    public function https(string $url): bool
    {
        if (strlen($url) > 2048 || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $p = parse_url($url);

        return is_array($p) && ($p['scheme'] ?? '') === 'https' && ! isset($p['user']) && ! isset($p['pass']) && (! isset($p['port']) || $p['port'] === 443) && ! preg_match('/[\x00-\x20\\\\]/', $url);
    }

    /** @return array{provider: string, video_id: string, src: string} */
    public function video(string $url): array
    {
        if (! $this->https($url)) {
            $this->invalid();
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $id = '';
        if ($host === 'youtu.be') {
            $id = ltrim($path, '/');
        }
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            if ($path === '/watch' && is_string($query['v'] ?? null)) {
                $id = $query['v'];
            } elseif (preg_match('~^/(?:shorts|embed)/([A-Za-z0-9_-]{11})$~D', $path, $m)) {
                $id = $m[1];
            }
        }
        if (preg_match('/^[A-Za-z0-9_-]{11}$/D', $id)) {
            return ['provider' => 'youtube', 'video_id' => $id, 'src' => 'https://www.youtube.com/embed/'.$id];
        }
        if (in_array($host, ['www.nicovideo.jp', 'nicovideo.jp'], true) && preg_match('~^/watch/((?:sm|nm|so)[0-9]{1,12})$~D', $path, $m)) {
            return ['provider' => 'niconico', 'video_id' => $m[1], 'src' => 'https://embed.nicovideo.jp/watch/'.$m[1]];
        }
        $this->invalid();
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['url' => 'YouTubeまたはニコニコ動画の視聴URLを指定してください。']);
    }
}
