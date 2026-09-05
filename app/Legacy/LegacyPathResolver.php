<?php

namespace App\Legacy;

class LegacyPathResolver
{
    /** @return array{status: 'present'|'missing'|'symlink'|'outside'|'unsupported', path: string|null, relative: string|null} */
    public function resolve(string $reference): array
    {
        $root = realpath((string) config('legacy.files_root'));
        if ($root === false) {
            return ['status' => 'missing', 'path' => null, 'relative' => null];
        }
        $reference = html_entity_decode(trim($reference), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($reference === '' || str_starts_with($reference, '//')) {
            return ['status' => 'unsupported', 'path' => null, 'relative' => null];
        }
        if (filter_var($reference, FILTER_VALIDATE_URL)) {
            $host = strtolower((string) parse_url($reference, PHP_URL_HOST));
            if (! in_array($host, ['www.mlexp.com', 'mlexp.com'], true)) {
                return ['status' => 'unsupported', 'path' => null, 'relative' => null];
            }
            $reference = (string) parse_url($reference, PHP_URL_PATH);
        }
        $path = rawurldecode(str_replace('\\', '/', $reference));
        $path = preg_replace('~^/(?:wk|mmc)/~i', '', $path) ?? $path;
        $relative = ltrim($path, '/');
        if ($relative === '' || str_contains($relative, "\0")) {
            return ['status' => 'unsupported', 'path' => null, 'relative' => null];
        }
        $candidate = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if ($this->hasSymlink($root, $candidate)) {
            return ['status' => 'symlink', 'path' => null, 'relative' => $relative];
        }
        $real = realpath($candidate);
        if ($real === false || ! is_file($real)) {
            return ['status' => 'missing', 'path' => null, 'relative' => $relative];
        }
        if (! str_starts_with($real.DIRECTORY_SEPARATOR, $root.DIRECTORY_SEPARATOR)) {
            return ['status' => 'outside', 'path' => null, 'relative' => $relative];
        }

        return ['status' => 'present', 'path' => $real, 'relative' => $relative];
    }

    private function hasSymlink(string $root, string $candidate): bool
    {
        $relative = ltrim(substr($candidate, strlen($root)), DIRECTORY_SEPARATOR);
        $current = $root;
        foreach (array_filter(explode(DIRECTORY_SEPARATOR, $relative), fn (string $part) => $part !== '') as $part) {
            $current .= DIRECTORY_SEPARATOR.$part;
            if (is_link($current)) {
                return true;
            }
        }

        return false;
    }
}
