<?php

namespace App\Console\Commands;

use App\Legacy\LegacyConnection;
use App\Legacy\LegacyInspector;
use Illuminate\Console\Command;
use Throwable;

class InspectLegacy extends Command
{
    protected $signature = 'legacy:inspect {--json : JSONで詳細を出力する}';

    protected $description = '旧DBと旧ファイル領域を変更せず棚卸しする';

    public function handle(LegacyConnection $connection, LegacyInspector $inspector): int
    {
        try {
            $db = $connection->connect();
            $this->components->info('Source: '.$connection->describe($db));
            $result = $inspector->inspect($db);
            if ($this->option('json')) {
                $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } else {
                $rows = [];
                foreach ((array) config('legacy.tables') as $key => $table) {
                    $row = $result[$key];
                    $rows[] = [$table, ($row['exists'] ?? false) ? 'yes' : 'NO', $row['count'] ?? '-', $row['duplicate_ids'] ?? '-'];
                }
                $this->table(['table', 'exists', 'rows', 'duplicate IDs'], $rows);
                $files = $result['files_inventory'];
                $this->line(sprintf('Files: present=%d missing=%d symlink=%d outside=%d', $files['present'], $files['missing'], $files['symlinks'], $files['outside_root']));
                $assets = $result['asset_inventory'];
                $this->line(sprintf('All image references: refs=%d distinct=%d present=%d missing=%d symlink=%d outside=%d unsupported=%d', $assets['references'], $assets['distinct'], $assets['present'], $assets['missing'], $assets['symlink'], $assets['outside'], $assets['unsupported']));
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
