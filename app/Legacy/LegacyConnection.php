<?php

namespace App\Legacy;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LegacyConnection
{
    public function connect(): Connection
    {
        $name = (string) config('legacy.connection', 'legacy');
        $legacy = DB::connection($name);
        $database = (string) $legacy->getDatabaseName();
        if ($database === '') {
            throw new RuntimeException('LEGACY_DB_DATABASE が設定されていません。');
        }

        $target = DB::connection();
        if ($target->getDriverName() === 'mysql'
            && $this->endpoint($legacy) === $this->endpoint($target)
            && strcasecmp($database, (string) $target->getDatabaseName()) === 0) {
            throw new RuntimeException('旧DBと移行先DBが同一です。移行を拒否しました。');
        }

        if ($legacy->getDriverName() !== 'mysql') {
            throw new RuntimeException('旧DB接続はMySQL専用です。');
        }

        // This is defence in depth in addition to the required SELECT-only user.
        $legacy->statement('SET SESSION TRANSACTION READ ONLY');

        return $legacy;
    }

    public function describe(Connection $connection): string
    {
        $config = $connection->getConfig();

        return sprintf('%s:%s/%s (user: %s, read-only)', $config['host'] ?? 'socket', $config['port'] ?? '3306', $connection->getDatabaseName(), $config['username'] ?? '');
    }

    private function endpoint(Connection $connection): string
    {
        $config = $connection->getConfig();

        return strtolower((string) ($config['host'] ?? '')).':'.($config['port'] ?? '3306').':'.($config['unix_socket'] ?? '');
    }
}
