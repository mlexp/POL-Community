<?php

namespace App\Services;

use App\Models\User;
use App\Support\ContentAccess;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ContentSearch
{
    public function query(string $term, ?User $user): Builder
    {
        $access = app(ContentAccess::class);
        // Bound LIKE patterns work with Japanese on both SQLite and MySQL; user wildcards are literal.
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
        $diaries = $access->diaries($user)->where(fn ($q) => $q->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("body_html LIKE ? ESCAPE '!'", [$pattern]))->select(['id', 'title', 'excerpt as summary', 'published_at as date'])->selectRaw("'diary' as kind");
        $threads = $access->threads($user)->where(function ($q) use ($pattern): void {
            $q->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])->orWhereExists(function ($posts) use ($pattern): void {
                $posts->selectRaw('1')->from('community_posts')->whereColumn('thread_id', 'community_threads.id')->whereNull('deleted_at')->where('status', 'visible')->whereRaw("body_html LIKE ? ESCAPE '!'", [$pattern]);
            });
        })->select(['id', 'title', 'title as summary', 'last_posted_at as date'])->selectRaw("'community' as kind");
        $announcements = $access->announcements($user)->where(fn ($q) => $q->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("summary LIKE ? ESCAPE '!'", [$pattern]))->select(['id', 'title', 'summary', 'published_at as date'])->selectRaw("'announcement' as kind");

        return DB::query()->fromSub($diaries->unionAll($threads)->unionAll($announcements), 'results')->orderByDesc('date')->orderBy('kind')->orderBy('id');
    }
}
