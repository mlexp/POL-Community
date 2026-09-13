<?php

namespace App\Legacy;

use App\Support\ContentSanitizer;
use App\Support\MediaUrl;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class LegacyMigrator
{
    private string $runId;

    /** @var array<string, int> */
    private array $stats = [];

    public function __construct(private ContentSanitizer $sanitizer, private MediaUrl $mediaUrl, private LegacyPathResolver $paths) {}

    /** @param array<int, string> $stages
     * @param  callable(string): void  $progress
     * @return array<string, int>
     */
    public function migrate(Connection $legacy, array $stages, int $batch, callable $progress): array
    {
        $this->runId = (string) Str::ulid();
        DB::table('legacy_import_runs')->insert(['id' => $this->runId, 'source_name' => config('legacy.source_name'), 'status' => 'running', 'started_at' => now(), 'created_at' => now()]);
        try {
            foreach ($stages as $stage) {
                $progress("[{$stage}] start");
                match ($stage) {
                    'users' => $this->users($legacy, $batch),
                    'diaries' => $this->diaries($legacy, $batch),
                    'community' => $this->community($legacy, $batch),
                    'feeds' => $this->feeds($legacy, $batch),
                    'media' => $this->media($legacy, $batch),
                };
                $progress("[{$stage}] complete");
            }
            DB::table('legacy_import_runs')->where('id', $this->runId)->update(['status' => 'completed', 'completed_at' => now(), 'stats_json' => json_encode($this->stats)]);

            return $this->stats;
        } catch (Throwable $e) {
            DB::table('legacy_import_runs')->where('id', $this->runId)->update(['status' => 'failed', 'completed_at' => now(), 'stats_json' => json_encode($this->stats), 'error_summary' => mb_substr($e->getMessage(), 0, 1000)]);
            throw $e;
        }
    }

    private function users(Connection $legacy, int $batch): void
    {
        $table = (string) config('legacy.tables.users');
        $legacy->table($table)->orderBy('sid')->chunk($batch, function ($rows) use ($table): void {
            DB::transaction(function () use ($rows, $table): void {
                foreach ($rows as $row) {
                    $sid = trim((string) $row->sid);
                    $login = trim((string) $row->uid);
                    if ($sid === '') {
                        $this->bump('users_skipped');

                        continue;
                    }
                    if ($this->mapped($table, $sid, 'user')) {
                        $character = $this->target($sid, $table, 'character');
                        if ($character !== null) {
                            $user = $this->target($sid, $table, 'user');
                            $avatar = $this->target(hash('sha256', ltrim((string) ($row->image ?? ''), '/')), 'legacy_asset', 'attachment');
                            DB::table('characters')->where('id', $character)->update(['name' => mb_substr($login, 0, 100), 'short_message' => mb_substr(trim((string) ($row->comment ?? '')), 0, 280) ?: null, 'avatar_attachment_id' => $avatar, 'updated_at' => $this->date($row->update_time ?? null)]);
                            if ($user !== null) {
                                DB::table('users')->where('id', $user)->update(['short_message' => null, 'avatar_attachment_id' => null]);
                            }
                            $this->profile($row, $character, $table, $sid, $this->date($row->update_time ?? null), true);
                        }
                        $this->bump('users_skipped');

                        continue;
                    }
                    $email = $this->email($row->mail_address ?? null);
                    if ($login === '' || DB::table('users')->whereRaw('LOWER(login_id) = ?', [mb_strtolower($login)])->exists()) {
                        $this->issue($table, $sid, 'error', 'login_id_conflict', 'ログインIDが空または大文字小文字を無視して重複しています。');
                        $this->bump('users_skipped');

                        continue;
                    }
                    if ($email !== null && DB::table('users')->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->exists()) {
                        $this->issue($table, $sid, 'warning', 'email_conflict', 'メールアドレスが重複したためNULLで移行しました。');
                        $email = null;
                    }
                    $id = (string) Str::ulid();
                    $at = $this->date($row->update_time ?? null);
                    DB::table('users')->insert(['id' => $id, 'login_id' => mb_substr($login, 0, 64), 'email' => $email, 'display_name' => mb_substr(trim((string) ($row->name ?? '')) ?: $login, 0, 100), 'bio' => $this->text($row->comment_detail ?? null), 'short_message' => null, 'website_url' => $this->httpsOrNull($row->url ?? null), 'password' => null, 'password_reset_required' => true, 'role' => ((int) ($row->user_role ?? 0)) >= 2 ? 'admin' : 'member', 'status' => 'active', 'created_at' => $at, 'updated_at' => $at]);
                    $this->map($table, $sid, 'user', $id, $row);
                    $avatar = $this->importLegacyImage((string) ($row->image ?? ''), $id, $this->visibility($row->open_role ?? 0, $table, $sid), $table, $sid);
                    if ($avatar !== null) {
                        // The legacy image represents the migrated primary character.
                    }
                    $character = (string) Str::ulid();
                    $game = DB::table('games')->where('code', 'ffxi')->value('id');
                    if ($game !== null) {
                        DB::table('characters')->insert(['id' => $character, 'user_id' => $id, 'game_id' => $game, 'name' => mb_substr($login, 0, 100), 'is_primary' => true, 'profile_text' => $this->text($row->comment_detail ?? null), 'short_message' => mb_substr(trim((string) ($row->comment ?? '')), 0, 280) ?: null, 'avatar_attachment_id' => $avatar, 'visibility' => $this->visibility($row->open_role ?? 0, $table, $sid), 'created_at' => $at, 'updated_at' => $at]);
                        $this->map($table, $sid, 'character', $character, $row);
                        $this->profile($row, $character, $table, $sid, $at);
                    }
                    $this->socialLinks($row, $id, $at);
                    $this->bump('users_imported');
                }
            });
        });
    }

    private function diaries(Connection $legacy, int $batch): void
    {
        $table = (string) config('legacy.tables.diary');
        $legacy->table($table)->where(fn ($q) => $q->whereNull('parent_did')->orWhereRaw("TRIM(parent_did) = ''"))->orderBy('did')->chunk($batch, function ($rows) use ($table): void {
            DB::transaction(function () use ($rows, $table): void {
                foreach ($rows as $row) {
                    $did = trim((string) $row->did);
                    if ($this->mapped($table, $did, 'diary')) {
                        $this->bump('diaries_skipped');

                        continue;
                    }
                    $user = $this->target((string) ($row->sid ?? ''), (string) config('legacy.tables.users'), 'user');
                    if ($user === null) {
                        $user = $this->fallbackUser();
                        $this->issue($table, $did, 'warning', 'missing_user', '日記の投稿者を解決できないため移行専用ユーザーへ割り当てました。');
                    }
                    $id = (string) Str::ulid();
                    $at = $this->date($row->update_time ?? null);
                    $visibility = $this->visibility($row->open_role ?? 0, $table, $did);
                    DB::table('diaries')->insert(['id' => $id, 'user_id' => $user, 'character_id' => $this->target((string) ($row->sid ?? ''), (string) config('legacy.tables.users'), 'character'), 'title' => mb_substr(trim((string) ($row->title ?? '')) ?: '無題', 0, 200), 'body_json' => null, 'body_html' => $this->sanitizer->sanitize((string) ($row->body ?? '')), 'visibility' => $visibility, 'status' => 'published', 'published_at' => $at, 'comments_enabled' => true, 'created_at' => $at, 'updated_at' => $at]);
                    $this->map($table, $did, 'diary', $id, $row);
                    $this->attachBodyImages((string) ($row->body ?? ''), 'diary', $id, $user, $visibility, $table, $did);
                    $this->bump('diaries_imported');
                }
            });
        });
        $legacy->table($table)->whereNotNull('parent_did')->whereRaw("TRIM(parent_did) <> ''")->orderBy('did')->chunk($batch, function ($rows) use ($table): void {
            DB::transaction(function () use ($rows, $table): void {
                foreach ($rows as $row) {
                    $did = trim((string) $row->did);
                    if ($this->mapped($table, $did, 'diary_comment')) {
                        $this->bump('diary_comments_skipped');

                        continue;
                    }
                    $parent = $this->target(trim((string) $row->parent_did), $table, 'diary');
                    if ($parent === null) {
                        $this->issue($table, $did, 'error', 'orphan_comment', '親日記を解決できません。');
                        $this->bump('diary_comments_skipped');

                        continue;
                    }
                    $id = (string) Str::ulid();
                    $at = $this->date($row->update_time ?? null);
                    DB::table('diary_comments')->insert(['id' => $id, 'diary_id' => $parent, 'user_id' => $this->target((string) ($row->sid ?? ''), (string) config('legacy.tables.users'), 'user'), 'author_name_snapshot' => mb_substr(trim((string) ($row->name ?? '')), 0, 100) ?: null, 'body_html' => $this->sanitizer->sanitize((string) ($row->body ?? '')), 'status' => 'visible', 'created_at' => $at, 'updated_at' => $at]);
                    $this->map($table, $did, 'diary_comment', $id, $row);
                    $diary = DB::table('diaries')->where('id', $parent)->first(['user_id', 'visibility']);
                    if ($diary !== null) {
                        $this->attachBodyImages((string) ($row->body ?? ''), 'diary', $parent, (string) $diary->user_id, (string) $diary->visibility, $table, $did);
                    }
                    $this->bump('diary_comments_imported');
                }
            });
        });
    }

    private function community(Connection $legacy, int $batch): void
    {
        $table = (string) config('legacy.tables.bbs');
        $category = DB::table('community_categories')->orderBy('sort_order')->value('id');
        if ($category === null) {
            throw new \RuntimeException('コミュニティカテゴリがありません。Seederを先に実行してください。');
        }
        $legacy->table($table)->where(fn ($q) => $q->whereNull('parent_bid')->orWhereRaw("TRIM(parent_bid) = ''"))->orderBy('bid')->chunk($batch, function ($rows) use ($table, $category): void {
            DB::transaction(function () use ($rows, $table, $category): void {
                foreach ($rows as $row) {
                    $bid = trim((string) $row->bid);
                    if ($this->mapped($table, $bid, 'community_thread')) {
                        $this->bump('threads_skipped');

                        continue;
                    }
                    $at = $this->date($row->update_time ?? null);
                    $user = $this->target((string) ($row->sid ?? ''), (string) config('legacy.tables.users'), 'user');
                    $thread = (string) Str::ulid();
                    $post = (string) Str::ulid();
                    $visibility = $this->visibility($row->open_role ?? 0, $table, $bid);
                    DB::table('community_threads')->insert(['id' => $thread, 'category_id' => $category, 'created_by_user_id' => $user, 'title' => mb_substr(trim((string) ($row->title ?? '')) ?: '無題', 0, 200), 'visibility' => $visibility, 'status' => 'open', 'is_pinned' => false, 'last_posted_at' => $at, 'post_count' => 1, 'created_at' => $at, 'updated_at' => $at]);
                    DB::table('community_posts')->insert($this->postData($row, $post, $thread, $user, $at));
                    $this->map($table, $bid, 'community_thread', $thread, $row);
                    $this->map($table, $bid, 'community_post', $post, $row);
                    $this->attachBodyImages((string) ($row->body ?? ''), 'community_post', $post, $user, $visibility, $table, $bid);
                    $this->bump('threads_imported');
                }
            });
        });
        $legacy->table($table)->whereNotNull('parent_bid')->whereRaw("TRIM(parent_bid) <> ''")->orderBy('bid')->chunk($batch, function ($rows) use ($table): void {
            DB::transaction(function () use ($rows, $table): void {
                foreach ($rows as $row) {
                    $bid = trim((string) $row->bid);
                    if ($this->mapped($table, $bid, 'community_post')) {
                        $this->bump('posts_skipped');

                        continue;
                    }
                    $thread = $this->target(trim((string) $row->parent_bid), $table, 'community_thread');
                    if ($thread === null) {
                        $this->issue($table, $bid, 'error', 'orphan_reply', '親スレッドを解決できません。');
                        $this->bump('posts_skipped');

                        continue;
                    }
                    $at = $this->date($row->update_time ?? null);
                    $post = (string) Str::ulid();
                    $user = $this->target((string) ($row->sid ?? ''), (string) config('legacy.tables.users'), 'user');
                    DB::table('community_posts')->insert($this->postData($row, $post, $thread, $user, $at));
                    $current = DB::table('community_threads')->where('id', $thread)->first(['last_posted_at', 'post_count']);
                    DB::table('community_threads')->where('id', $thread)->update([
                        'last_posted_at' => $current !== null && CarbonImmutable::parse((string) $current->last_posted_at)->isAfter($at) ? $current->last_posted_at : $at,
                        'post_count' => ((int) ($current->post_count ?? 0)) + 1,
                    ]);
                    $this->map($table, $bid, 'community_post', $post, $row);
                    $threadRow = DB::table('community_threads')->where('id', $thread)->first(['visibility']);
                    if ($threadRow !== null) {
                        $this->attachBodyImages((string) ($row->body ?? ''), 'community_post', $post, $user, (string) $threadRow->visibility, $table, $bid);
                    }
                    $this->bump('posts_imported');
                }
            });
        });
    }

    private function feeds(Connection $legacy, int $batch): void
    {
        $table = (string) config('legacy.tables.rss');
        $legacy->table($table)->orderBy('rid')->chunk($batch, function ($rows) use ($table): void {
            DB::transaction(function () use ($rows, $table): void {
                foreach ($rows as $row) {
                    $rid = trim((string) $row->rid);
                    if ($this->mapped($table, $rid, 'feed_source')) {
                        $this->bump('feeds_skipped');

                        continue;
                    }
                    $url = trim((string) ($row->url ?? ''));
                    if ($this->httpsOrNull($url) === null) {
                        $this->issue($table, $rid, 'warning', 'unsafe_feed_url', 'HTTPSではないため無効状態で保持します。');
                    }
                    $id = (string) Str::ulid();
                    $hash = hash('sha256', $url);
                    if (DB::table('feed_sources')->where('url_hash', $hash)->exists()) {
                        $this->issue($table, $rid, 'warning', 'duplicate_feed_url', '同じURLのRSSが既にあります。');
                        $this->bump('feeds_skipped');

                        continue;
                    }
                    DB::table('feed_sources')->insert(['id' => $id, 'name' => mb_substr(trim((string) ($row->name ?? '')) ?: 'Legacy feed', 0, 100), 'url' => mb_substr($url, 0, 2048), 'url_hash' => $hash, 'visibility' => $this->visibility($row->open_role ?? 0, $table, $rid), 'is_enabled' => false, 'fetch_interval_minutes' => 60, 'created_at' => now(), 'updated_at' => now()]);
                    $this->map($table, $rid, 'feed_source', $id, $row);
                    $this->bump('feeds_imported');
                }
            });
        });
    }

    private function media(Connection $legacy, int $batch): void
    {
        $this->movies($legacy, $batch);
        $this->files($legacy, $batch);
    }

    private function movies(Connection $legacy, int $batch): void
    {
        $table = (string) config('legacy.tables.movies');
        $legacy->table($table)->orderBy('mid')->chunk($batch, function ($rows) use ($table): void {
            DB::transaction(function () use ($rows, $table): void {
                foreach ($rows as $row) {
                    $mid = trim((string) $row->mid);
                    if ($this->mapped($table, $mid, 'video_embed')) {
                        $this->bump('movies_skipped');

                        continue;
                    }
                    $url = trim((string) ($row->url ?? ''));
                    $videoId = trim((string) ($row->vid ?? ''));
                    if (preg_match('/^[A-Za-z0-9_-]{11}$/D', $videoId)) {
                        $url = 'https://www.youtube.com/watch?v='.$videoId;
                    } elseif (preg_match('/^(?:sm|nm|so)[0-9]{1,12}$/D', $videoId)) {
                        $url = 'https://www.nicovideo.jp/watch/'.$videoId;
                    }
                    try {
                        $parsed = $this->mediaUrl->video($url);
                    } catch (Throwable) {
                        $this->issue($table, $mid, 'warning', 'unsupported_video', '対応プロバイダの安全な視聴URLではありません。');
                        $this->bump('movies_skipped');

                        continue;
                    }
                    $existing = DB::table('video_embeds')->where(['provider' => $parsed['provider'], 'video_id' => $parsed['video_id']])->value('id');
                    $id = is_string($existing) ? $existing : (string) Str::ulid();
                    if (! is_string($existing)) {
                        DB::table('video_embeds')->insert(['id' => $id, 'provider' => $parsed['provider'], 'video_id' => $parsed['video_id'], 'original_url' => $url, 'title_snapshot' => mb_substr(trim((string) ($row->subject ?? '')), 0, 200) ?: null, 'created_by_user_id' => $this->target((string) ($row->sid ?? ''), (string) config('legacy.tables.users'), 'user'), 'created_at' => $this->date($row->update_time ?? null), 'updated_at' => $this->date($row->update_time ?? null)]);
                    }
                    $this->map($table, $mid, 'video_embed', $id, $row);
                    $this->bump('movies_imported');
                }
            });
        });
    }

    private function files(Connection $legacy, int $batch): void
    {
        $table = (string) config('legacy.tables.files');
        if (realpath((string) config('legacy.files_root')) === false) {
            $this->issue($table, null, 'error', 'missing_files_root', '旧ファイルルートが存在しません。');

            return;
        }
        $legacy->table($table)->orderBy('fid')->chunk($batch, function ($rows) use ($table): void {
            foreach ($rows as $row) {
                $fid = trim((string) $row->fid);
                if ($this->mapped($table, $fid, 'attachment')) {
                    $this->bump('files_skipped');

                    continue;
                }
                $uploader = $this->target((string) ($row->sid ?? ''), (string) config('legacy.tables.users'), 'user');
                $id = $this->importLegacyImage((string) $row->path, $uploader, $this->visibility($row->open_role ?? 0, $table, $fid), $table, $fid);
                if ($id === null) {
                    $this->bump('files_skipped');
                } else {
                    $this->map($table, $fid, 'attachment', $id, $row);
                    $this->bump('files_imported');
                }
            }
        });
    }

    private function importLegacyImage(string $reference, ?string $uploader, string $visibility, string $sourceTable, string $sourceId): ?string
    {
        if (trim($reference) === '') {
            return null;
        }
        $resolved = $this->paths->resolve($reference);
        if ($resolved['status'] !== 'present' || $resolved['path'] === null || $resolved['relative'] === null) {
            $this->issue($sourceTable, $sourceId, 'warning', 'unsafe_or_missing_file', '画像が欠損、未対応URL、シンボリックリンク、または許可ルート外です。', ['status' => $resolved['status']]);

            return null;
        }
        $assetKey = hash('sha256', $resolved['relative']);
        $existing = $this->target($assetKey, 'legacy_asset', 'attachment');
        if ($existing !== null) {
            return $existing;
        }
        $bytes = file_get_contents($resolved['path']);
        $info = $bytes === false ? false : @getimagesizefromstring($bytes);
        $limit = min((int) config('content.image_hard_max_bytes'), 20 * 1024 * 1024);
        // GIF is accepted only as a legacy input and is always flattened into
        // a freshly encoded WebP; new uploads continue to reject GIF.
        if ($info === false || strlen($bytes) > $limit || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)
            || $info[0] < 1 || $info[1] < 1 || max($info[0], $info[1]) > (int) config('content.image_max_dimension')
            || (int) config('content.image_max_pixels') < $info[0] * $info[1]) {
            $this->issue($sourceTable, $sourceId, 'warning', 'unsupported_file', '画像の形式、容量、または寸法が許可範囲外です。');

            return null;
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            $this->issue($sourceTable, $sourceId, 'warning', 'invalid_image', '画像をデコードできません。');

            return null;
        }
        try {
            $encoded = $this->encodeWebp($image, $info[0], $info[1]);
            $ratio = min(1, (int) config('content.thumbnail_dimension') / max($info[0], $info[1]));
            $thumbWidth = max(1, (int) round($info[0] * $ratio));
            $thumbHeight = max(1, (int) round($info[1] * $ratio));
            $thumbnail = $this->encodeWebp($image, $thumbWidth, $thumbHeight);
        } finally {
            imagedestroy($image);
        }
        $owner = $uploader ?? 'legacy';
        $id = (string) Str::ulid();
        $path = "users/{$owner}/images/{$id}/original.webp";
        $thumbnailPath = "users/{$owner}/images/{$id}/thumbnail.webp";
        if (! Storage::disk('local')->put($path, $encoded) || ! Storage::disk('local')->put($thumbnailPath, $thumbnail)) {
            Storage::disk('local')->delete([$path, $thumbnailPath]);
            throw new \RuntimeException('移行画像を保存できませんでした。');
        }
        try {
            DB::table('attachments')->insert(['id' => $id, 'uploaded_by_user_id' => $uploader, 'disk' => 'local', 'path' => $path, 'path_hash' => hash('sha256', $path), 'original_name' => mb_substr(basename($resolved['relative']), 0, 255), 'mime_type' => 'image/webp', 'extension' => 'webp', 'size_bytes' => strlen($encoded), 'width' => $info[0], 'height' => $info[1], 'sha256' => hash('sha256', $encoded), 'visibility' => $visibility, 'status' => 'ready', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('attachment_variants')->insert(['id' => (string) Str::ulid(), 'attachment_id' => $id, 'variant' => 'thumbnail', 'disk' => 'local', 'path' => $thumbnailPath, 'path_hash' => hash('sha256', $thumbnailPath), 'mime_type' => 'image/webp', 'size_bytes' => strlen($thumbnail), 'width' => $thumbWidth, 'height' => $thumbHeight, 'created_at' => now(), 'updated_at' => now()]);
            $this->map('legacy_asset', $assetKey, 'attachment', $id, (object) ['path' => $resolved['relative'], 'sha256' => hash('sha256', $bytes)]);
        } catch (Throwable $e) {
            Storage::disk('local')->delete([$path, $thumbnailPath]);
            throw $e;
        }
        $this->bump('images_imported');

        return $id;
    }

    private function attachBodyImages(string $html, string $type, string $targetId, ?string $uploader, string $visibility, string $sourceTable, string $sourceId): void
    {
        if (! str_contains(strtolower($html), '<img')) {
            return;
        }
        $dom = new \DOMDocument;
        @$dom->loadHTML('<meta charset="utf-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $sort = (int) DB::table('attachables')->where(['attachable_type' => $type, 'attachable_id' => $targetId])->max('sort_order');
        foreach ($dom->getElementsByTagName('img') as $image) {
            $attachment = $this->importLegacyImage($image->getAttribute('src'), $uploader, $visibility, $sourceTable, $sourceId);
            if ($attachment !== null) {
                DB::table('attachables')->insertOrIgnore(['attachment_id' => $attachment, 'attachable_type' => $type, 'attachable_id' => $targetId, 'sort_order' => $sort++]);
            }
        }
    }

    private function encodeWebp(\GdImage $source, int $width, int $height): string
    {
        if ($width < 1 || $height < 1) {
            throw new \RuntimeException('画像寸法が不正です。');
        }
        $clean = imagecreatetruecolor($width, $height);
        if ($clean === false) {
            throw new \RuntimeException('画像処理に失敗しました。');
        }
        imagealphablending($clean, false);
        imagesavealpha($clean, true);
        imagecopyresampled($clean, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        ob_start();
        try {
            if (! imagewebp($clean, null, 85)) {
                throw new \RuntimeException('WebP変換に失敗しました。');
            }
            $bytes = ob_get_contents();
            if (! is_string($bytes) || $bytes === '') {
                throw new \RuntimeException('WebP変換に失敗しました。');
            }

            return $bytes;
        } finally {
            ob_end_clean();
            imagedestroy($clean);
        }
    }

    private function profile(object $row, string $character, string $table, string $sid, CarbonImmutable $at, bool $update = false): void
    {
        $nationCodes = [1 => 'sandoria', 2 => 'bastok', 3 => 'windurst'];
        $raceProfiles = [0 => ['hume', 'male'], 1 => ['hume', 'female'], 2 => ['elvaan', 'male'], 3 => ['elvaan', 'female'], 4 => ['tarutaru', 'male'], 5 => ['tarutaru', 'female'], 6 => ['mithra', 'female'], 7 => ['galka', 'male']];
        $nation = DB::table('ffxi_nations')->where('code', $nationCodes[(int) ($row->ffxi_realm ?? 0)] ?? '')->value('id');
        [$raceCode, $gender] = $raceProfiles[(int) ($row->ffxi_race ?? 0)] ?? [null, null];
        $race = DB::table('ffxi_races')->where('code', $raceCode)->value('id');
        $faceNumber = intdiv(max(0, (int) ($row->ffxi_face ?? 0)), 2) + 1;
        $faceCode = $faceNumber.(((int) ($row->ffxi_face ?? 0)) % 2 === 0 ? 'a' : 'b');
        $face = DB::table('ffxi_face_types')->where(['race_id' => $race, 'gender' => $gender, 'face_code' => $faceCode])->value('id');
        $jobs = DB::table('ffxi_jobs')->orderBy('sort_order')->pluck('id')->all();
        $main = $jobs[max(0, (int) ($row->ffxi_main_job ?? 0) - 1)] ?? null;
        $support = $jobs[max(0, (int) ($row->ffxi_sub_job ?? 0) - 1)] ?? null;
        DB::table('ffxi_profiles')->updateOrInsert(['character_id' => $character], ['nation_id' => $nation, 'rank' => min(10, max(1, (int) ($row->ffxi_rank ?? 1))), 'race_id' => $race, 'gender' => $gender, 'face_type_id' => $face, 'main_job_id' => $main, 'support_job_id' => $support, 'pol_handle' => mb_substr(trim((string) ($row->ffxi_pol_handle ?? '')), 0, 64) ?: null, 'created_at' => $at, 'updated_at' => $at]);
        if ($update) {
            DB::table('ffxi_character_job_levels')->where('character_id', $character)->delete();
            DB::table('ffxi_character_craft_levels')->where('character_id', $character)->delete();
        }
        $this->levels((string) ($row->ffxi_jobs_level ?? ''), $jobs, 'ffxi_character_job_levels', 'job_id', 99, $character, $table, $sid, $at);
        $crafts = DB::table('ffxi_crafts')->orderBy('sort_order')->pluck('id')->all();
        $this->levels((string) ($row->ffxi_ics_level ?? ''), $crafts, 'ffxi_character_craft_levels', 'craft_id', 255, $character, $table, $sid, $at);
    }

    /** @param array<int, int> $ids */
    private function levels(string $csv, array $ids, string $target, string $foreign, int $max, string $character, string $table, string $sid, CarbonImmutable $at): void
    {
        if (trim($csv) === '') {
            return;
        } $values = explode(',', $csv);
        if (count($values) !== count($ids)) {
            $this->issue($table, $sid, 'warning', 'level_count_mismatch', "{$target} のCSV要素数がマスタと一致しません。", ['actual' => count($values), 'expected' => count($ids)]);
        }
        foreach (array_slice($values, 0, count($ids)) as $i => $value) {
            if (! ctype_digit(trim($value)) || (int) $value > $max) {
                $this->issue($table, $sid, 'warning', 'invalid_level', 'レベル値が不正です。', ['index' => $i]);

                continue;
            } DB::table($target)->insert(['character_id' => $character, $foreign => $ids[$i], 'level' => (int) $value, 'updated_at' => $at]);
        }
    }

    private function socialLinks(object $row, string $user, CarbonImmutable $at): void
    {
        foreach (['twitter_id' => 'x', 'facebook_id' => 'facebook', 'youtube_id' => 'youtube'] as $column => $service) {
            $value = trim((string) ($row->{$column} ?? ''));
            if ($value === '') {
                continue;
            } $url = match ($service) {
                'x' => 'https://x.com/'.rawurlencode(ltrim($value, '@')), 'facebook' => 'https://www.facebook.com/'.rawurlencode($value), default => 'https://www.youtube.com/user/'.rawurlencode($value)
            };
            DB::table('social_links')->insert(['id' => (string) Str::ulid(), 'user_id' => $user, 'service' => $service, 'profile_url' => $url, 'profile_url_hash' => hash('sha256', $url), 'sort_order' => 0, 'created_at' => $at, 'updated_at' => $at]);
        }
    }

    /** @return array<string, mixed> */
    private function postData(object $row, string $id, string $thread, ?string $user, CarbonImmutable $at): array
    {
        return ['id' => $id, 'thread_id' => $thread, 'user_id' => $user, 'author_name_snapshot' => mb_substr(trim((string) ($row->name ?? '')), 0, 100) ?: null, 'body_html' => $this->sanitizer->sanitize((string) ($row->body ?? '')), 'status' => 'visible', 'legacy_contact_email' => $this->email($row->mail_address ?? null), 'legacy_access_log' => mb_substr(trim((string) ($row->log ?? '')), 0, 512) ?: null, 'created_at' => $at, 'updated_at' => $at];
    }

    private function mapped(string $table, string $legacyId, string $type): bool
    {
        return DB::table('legacy_id_mappings')->where(['source_table' => $table, 'legacy_id' => $legacyId, 'target_type' => $type])->exists();
    }

    private function target(string $legacyId, string $table, string $type): ?string
    {
        $id = DB::table('legacy_id_mappings')->where(['source_table' => $table, 'legacy_id' => trim($legacyId), 'target_type' => $type])->value('target_id');

        return is_string($id) ? $id : null;
    }

    private function fallbackUser(): string
    {
        $existing = DB::table('users')->where('login_id', 'legacy-orphan')->value('id');
        if (is_string($existing)) {
            return $existing;
        }
        $id = (string) Str::ulid();
        DB::table('users')->insert([
            'id' => $id,
            'login_id' => 'legacy-orphan',
            'email' => null,
            'display_name' => '旧データ投稿者不明',
            'password' => null,
            'password_reset_required' => true,
            'role' => 'member',
            'status' => 'suspended',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function map(string $table, string $legacyId, string $type, string $targetId, object $row): void
    {
        DB::table('legacy_id_mappings')->insert(['import_run_id' => $this->runId, 'source_table' => $table, 'legacy_id' => $legacyId, 'target_type' => $type, 'target_id' => $targetId, 'source_checksum' => hash('sha256', json_encode((array) $row, JSON_UNESCAPED_UNICODE) ?: ''), 'created_at' => now()]);
    }

    /** @param array<string, int|string> $context */
    private function issue(string $table, ?string $id, string $severity, string $code, string $message, array $context = []): void
    {
        DB::table('legacy_import_issues')->insert(['import_run_id' => $this->runId, 'source_table' => $table, 'legacy_id' => $id, 'severity' => $severity, 'code' => $code, 'message' => $message, 'context_json' => $context === [] ? null : json_encode($context), 'created_at' => now()]);
        $this->bump('issues');
    }

    private function visibility(mixed $value, string $table, string $id): string
    {
        $int = (int) $value;
        if (! in_array($int, [0, 1, 2], true)) {
            $this->issue($table, $id, 'warning', 'invalid_visibility', '公開範囲が不正なためprivateにしました。');

            return 'private';
        }

        return [0 => 'public', 1 => 'members', 2 => 'private'][$int];
    }

    private function email(mixed $value): ?string
    {
        $email = mb_strtolower(trim((string) $value));

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 254 ? $email : null;
    }

    private function httpsOrNull(mixed $value): ?string
    {
        $url = trim((string) $value);

        return $url !== '' && strlen($url) <= 2048 && filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https' ? $url : null;
    }

    private function text(mixed $value): ?string
    {
        $text = trim(strip_tags((string) $value));

        return $text === '' ? null : $text;
    }

    private function date(mixed $value): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse((string) $value)->utc();
        } catch (Throwable) {
            return now();
        }
    }

    private function bump(string $key): void
    {
        $this->stats[$key] = ($this->stats[$key] ?? 0) + 1;
    }
}
