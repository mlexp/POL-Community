<?php

return [
    'connection' => 'legacy',
    'source_name' => env('LEGACY_SOURCE_NAME', 'mmc_production'),
    'allowed_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('LEGACY_ALLOWED_HOSTS', '')),
    ))),
    // Root directory of the legacy CMS. Stored paths such as users/... and
    // images/... are resolved beneath this directory.
    'files_root' => env('LEGACY_FILES_ROOT', base_path('old_project/app')),
    'tables' => [
        'users' => 'mmc_wk_users',
        'diary' => 'mmc_wk_diary',
        'rss' => 'mmc_wk_rss',
        'recent' => 'mmc_wk_recent',
        'bbs' => 'mmc_wk_bbs',
        'files' => 'mmc_wk_files',
        'movies' => 'mmc_wk_movies',
    ],
];
