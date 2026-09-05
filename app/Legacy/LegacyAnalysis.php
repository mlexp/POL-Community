<?php

namespace App\Legacy;

use Illuminate\Database\Connection;

class LegacyAnalysis
{
    /** @return array<string, mixed> */
    public function run(Connection $db): array
    {
        $t = (array) config('legacy.tables');
        $counts = [];
        foreach ($t as $key => $table) {
            $counts[$key] = (int) ($db->selectOne("SELECT COUNT(*) aggregate FROM `{$table}`")->aggregate ?? 0);
        }
        $counts['diary_parents'] = (int) ($db->selectOne("SELECT COUNT(*) aggregate FROM `{$t['diary']}` WHERE parent_did IS NULL OR TRIM(parent_did) = ''")->aggregate ?? 0);
        $counts['diary_comments'] = $counts['diary'] - $counts['diary_parents'];
        $counts['orphan_diary_comments'] = (int) ($db->selectOne("SELECT COUNT(*) aggregate FROM `{$t['diary']}` child LEFT JOIN `{$t['diary']}` parent ON parent.did = child.parent_did WHERE child.parent_did IS NOT NULL AND TRIM(child.parent_did) <> '' AND parent.did IS NULL")->aggregate ?? 0);
        $counts['bbs_threads'] = (int) ($db->selectOne("SELECT COUNT(*) aggregate FROM `{$t['bbs']}` WHERE parent_bid IS NULL OR TRIM(parent_bid) = ''")->aggregate ?? 0);
        $counts['bbs_replies'] = $counts['bbs'] - $counts['bbs_threads'];
        $counts['orphan_bbs_replies'] = (int) ($db->selectOne("SELECT COUNT(*) aggregate FROM `{$t['bbs']}` child LEFT JOIN `{$t['bbs']}` parent ON parent.bid = child.parent_bid WHERE child.parent_bid IS NOT NULL AND TRIM(child.parent_bid) <> '' AND parent.bid IS NULL")->aggregate ?? 0);
        $counts['duplicate_login_ids_ci'] = (int) ($db->selectOne("SELECT COUNT(*) aggregate FROM (SELECT LOWER(TRIM(uid)) FROM `{$t['users']}` GROUP BY LOWER(TRIM(uid)) HAVING COUNT(*) > 1) x")->aggregate ?? 0);
        $counts['duplicate_emails_ci'] = (int) ($db->selectOne("SELECT COUNT(*) aggregate FROM (SELECT LOWER(TRIM(mail_address)) FROM `{$t['users']}` WHERE mail_address IS NOT NULL AND TRIM(mail_address) <> '' GROUP BY LOWER(TRIM(mail_address)) HAVING COUNT(*) > 1) x")->aggregate ?? 0);
        $counts['invalid_visibility'] = [];
        foreach (['users', 'diary', 'rss', 'bbs', 'files', 'movies'] as $key) {
            $counts['invalid_visibility'][$key] = (int) ($db->selectOne("SELECT COUNT(*) aggregate FROM `{$t[$key]}` WHERE open_role IS NULL OR open_role NOT IN (0, 1, 2)")->aggregate ?? 0);
        }
        $counts['recent_excluded'] = $counts['recent'];

        return $counts;
    }
}
