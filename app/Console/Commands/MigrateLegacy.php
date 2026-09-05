<?php

namespace App\Console\Commands;

use App\Legacy\LegacyAnalysis;
use App\Legacy\LegacyConnection;
use App\Legacy\LegacyMigrator;
use Illuminate\Console\Command;
use Throwable;

class MigrateLegacy extends Command
{
    protected $signature = 'legacy:migrate
        {--dry-run : 新DB・ファイルへ一切書き込まず変換可否を診断する}
        {--only=* : users,diaries,community,feeds,media の一部だけを実行する}
        {--batch=200 : バッチ件数}
        {--confirm-source= : 実移行時に旧DB名を明示して誤操作を防ぐ}';

    protected $description = '旧CMSデータを段階的かつ冪等に移行する';

    public function handle(LegacyConnection $connection, LegacyAnalysis $analysis, LegacyMigrator $migrator): int
    {
        try {
            $db = $connection->connect();
            $this->components->info('Source: '.$connection->describe($db));
            $batch = (int) $this->option('batch');
            if ($batch < 1 || $batch > 2000) {
                throw new \InvalidArgumentException('--batch は1〜2000で指定してください。');
            }
            $only = array_values(array_filter((array) $this->option('only')));
            $valid = ['users', 'diaries', 'community', 'feeds', 'media'];
            if (array_diff($only, $valid) !== []) {
                throw new \InvalidArgumentException('--only の値が不正です。');
            }

            if ($this->option('dry-run')) {
                $stats = $analysis->run($db);
                $this->line((string) json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->components->info('Dry-run complete: 新DBとファイルへの書き込みはありません。');

                return self::SUCCESS;
            }

            if ((string) $this->option('confirm-source') !== (string) $db->getDatabaseName()) {
                throw new \RuntimeException('実移行には --confirm-source='.$db->getDatabaseName().' を指定してください。');
            }
            $stats = $migrator->migrate($db, $only ?: $valid, $batch, fn (string $message) => $this->line($message));
            $this->line((string) json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
