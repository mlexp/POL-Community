<?php

namespace App\Legacy;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;

class LegacyInspector
{
    public function __construct(private LegacyPathResolver $paths) {}

    /** @return array<string, array<string, mixed>> */
    public function inspect(Connection $db): array
    {
        $result = [];
        foreach ((array) config('legacy.tables') as $key => $table) {
            $columns = $db->select('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, CHARACTER_SET_NAME, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', [$db->getDatabaseName(), $table]);
            if ($columns === []) {
                $result[$key] = ['table' => $table, 'exists' => false];

                continue;
            }
            $primary = $this->primaryColumn((string) $key);
            $count = (int) ($db->selectOne("SELECT COUNT(*) AS aggregate FROM `{$table}`")->aggregate ?? 0);
            $nulls = $this->nullAndEmptyCounts($db, (string) $table, $columns);
            $duplicates = $primary === null ? 0 : (int) ($db->selectOne("SELECT COUNT(*) AS aggregate FROM (SELECT `{$primary}` FROM `{$table}` GROUP BY `{$primary}` HAVING COUNT(*) > 1) duplicates")->aggregate ?? 0);
            $result[$key] = ['table' => $table, 'exists' => true, 'count' => $count, 'columns' => array_map(fn ($c) => (array) $c, $columns), 'null_empty' => $nulls, 'duplicate_ids' => $duplicates];
        }

        $result['files_inventory'] = $this->files($db);
        $result['asset_inventory'] = $this->assets($db);

        return $result;
    }

    /** @param array<int, object> $columns
     * @return array<string, array{null: int, empty: int}>
     */
    private function nullAndEmptyCounts(Connection $db, string $table, array $columns): array
    {
        $result = [];
        foreach ($columns as $column) {
            $definition = (array) $column;
            $name = (string) $definition['COLUMN_NAME'];
            $null = (int) ($db->selectOne("SELECT COUNT(*) AS aggregate FROM `{$table}` WHERE `{$name}` IS NULL")->aggregate ?? 0);
            $empty = Str::contains((string) $definition['COLUMN_TYPE'], ['char', 'text'])
                ? (int) ($db->selectOne("SELECT COUNT(*) AS aggregate FROM `{$table}` WHERE `{$name}` = ''")->aggregate ?? 0) : 0;
            if ($null > 0 || $empty > 0) {
                $result[$name] = ['null' => $null, 'empty' => $empty];
            }
        }

        return $result;
    }

    /** @return array<string, int|string|bool> */
    private function files(Connection $db): array
    {
        $table = (string) config('legacy.tables.files');
        $root = realpath((string) config('legacy.files_root'));
        $stats = ['configured_root' => (string) config('legacy.files_root'), 'root_exists' => $root !== false, 'rows' => 0, 'present' => 0, 'missing' => 0, 'symlinks' => 0, 'outside_root' => 0, 'unsupported' => 0];
        if ($root === false) {
            return $stats;
        }
        foreach ($db->table($table)->select(['fid', 'path'])->orderBy('fid')->cursor() as $row) {
            $stats['rows']++;
            $status = $this->paths->resolve((string) $row->path)['status'];
            $key = match ($status) {
                'symlink' => 'symlinks', 'outside' => 'outside_root', default => $status
            };
            $stats[$key]++;
        }

        return $stats;
    }

    /** @return array<string, int> */
    private function assets(Connection $db): array
    {
        $references = [];
        $users = (string) config('legacy.tables.users');
        foreach ($db->table($users)->whereNotNull('image')->whereRaw("TRIM(image) <> ''")->pluck('image') as $reference) {
            $references[] = (string) $reference;
        }
        foreach ([(string) config('legacy.tables.diary'), (string) config('legacy.tables.bbs')] as $table) {
            foreach ($db->table($table)->where('body', 'like', '%<img%')->pluck('body') as $html) {
                $dom = new \DOMDocument;
                @$dom->loadHTML('<meta charset="utf-8">'.(string) $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
                foreach ($dom->getElementsByTagName('img') as $image) {
                    $references[] = $image->getAttribute('src');
                }
            }
        }
        $fileTable = (string) config('legacy.tables.files');
        foreach ($db->table($fileTable)->pluck('path') as $reference) {
            $references[] = (string) $reference;
        }
        $stats = ['references' => count($references), 'distinct' => 0, 'present' => 0, 'missing' => 0, 'symlink' => 0, 'outside' => 0, 'unsupported' => 0];
        foreach (array_unique($references) as $reference) {
            $stats['distinct']++;
            $stats[$this->paths->resolve($reference)['status']]++;
        }

        return $stats;
    }

    private function primaryColumn(string $key): ?string
    {
        return ['users' => 'sid', 'diary' => 'did', 'rss' => 'rid', 'recent' => 'rid', 'bbs' => 'bid', 'files' => 'fid', 'movies' => 'mid'][$key] ?? null;
    }
}
