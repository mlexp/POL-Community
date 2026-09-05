<?php

namespace App\Console\Commands;

use App\Services\FeedParser;
use App\Services\SafeFeedHttp;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchFeeds extends Command
{
    protected $signature = 'feeds:fetch {--force : 取得間隔を無視する}';

    protected $description = '有効なRSS/Atomソースを安全に取得する';

    public function handle(SafeFeedHttp $http, FeedParser $parser): int
    {
        $failed = false;
        foreach (DB::table('feed_sources')->where('is_enabled', true)->orderBy('id')->cursor() as $source) {
            $lock = Cache::lock('feed-fetch:'.$source->id, 120);
            if (! $lock->get()) {
                continue;
            }
            try {
                $source = DB::table('feed_sources')->where('id', $source->id)->where('is_enabled', true)->first();
                if ($source === null) {
                    continue;
                }
                if (! $this->option('force') && $source->last_fetched_at !== null && CarbonImmutable::parse($source->last_fetched_at)->addMinutes($source->fetch_interval_minutes)->isFuture()) {
                    continue;
                }
                DB::table('feed_sources')->where('id', $source->id)->update(['last_fetched_at' => now()]);
                $items = $parser->parse($http->get($source->url));
                DB::transaction(function () use ($source, $items): void {
                    foreach ($items as $item) {
                        DB::table('feed_items')->upsert([['id' => (string) Str::ulid(), 'feed_source_id' => $source->id, 'fetched_at' => now(), ...$item]], ['feed_source_id', 'external_id_hash'], ['title', 'url', 'summary_html', 'author', 'published_at', 'fetched_at']);
                    }
                    DB::table('feed_sources')->where('id', $source->id)->update(['last_success_at' => now(), 'last_error' => null]);
                });
            } catch (\Throwable) {
                $failed = true;
                DB::table('feed_sources')->where('id', $source->id)->update(['last_error' => '取得失敗。HTTPS URL、公開DNS、応答サイズ、RSS/Atom形式を確認してください。リダイレクトは拒否します。']);
                $this->error('RSSソースの取得に失敗しました。管理画面を確認してください。');
            } finally {
                $lock->release();
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
