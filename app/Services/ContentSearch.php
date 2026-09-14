<?php

namespace App\Services;

use App\Models\User;
use App\Support\ContentAccess;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ContentSearch
{
    /** @param array<int, string> $types */
    public function query(string $term, ?User $user, array $types = ['diary', 'community', 'announcement']): Builder
    {
        $access = app(ContentAccess::class);
        // Bound LIKE patterns work with Japanese on both SQLite and MySQL; user wildcards are literal.
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
        $diaries = $access->diaries($user)->join('users as diary_users', 'diaries.user_id', '=', 'diary_users.id')->where(fn ($q) => $q->whereRaw("diaries.title LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("diaries.body_html LIKE ? ESCAPE '!'", [$pattern]))->select(['diaries.id', 'diaries.title', 'diaries.excerpt as summary', 'diaries.published_at as date', 'diary_users.id as author_user_id', 'diary_users.display_name as author'])->selectRaw("'diary' as kind");
        $threads = $access->threads($user)->leftJoin('users as thread_users', 'community_threads.created_by_user_id', '=', 'thread_users.id')->where(function ($q) use ($pattern): void {
            $q->whereRaw("community_threads.title LIKE ? ESCAPE '!'", [$pattern])->orWhereExists(function ($posts) use ($pattern): void {
                $posts->selectRaw('1')->from('community_posts')->whereColumn('community_posts.thread_id', 'community_threads.id')->whereNull('community_posts.deleted_at')->where('community_posts.status', 'visible')->whereRaw("community_posts.body_html LIKE ? ESCAPE '!'", [$pattern]);
            });
        })->select(['community_threads.id', 'community_threads.title', 'community_threads.title as summary', 'community_threads.last_posted_at as date', 'community_threads.created_by_user_id as author_user_id', 'thread_users.display_name as author'])->selectRaw("'community' as kind");
        $announcements = $access->announcements($user)->leftJoin('users as announcement_users', 'announcements.created_by_user_id', '=', 'announcement_users.id')->where(fn ($q) => $q->whereRaw("announcements.title LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("announcements.summary LIKE ? ESCAPE '!'", [$pattern]))->select(['announcements.id', 'announcements.title', 'announcements.summary', 'announcements.published_at as date', 'announcement_users.id as author_user_id', 'announcement_users.display_name as author'])->selectRaw("'announcement' as kind");

        $queries = ['diary' => $diaries, 'community' => $threads, 'announcement' => $announcements];
        $selected = collect($types)->filter(fn ($type) => isset($queries[$type]))->map(fn ($type) => $queries[$type])->values();
        $union = $selected->shift();
        foreach ($selected as $query) {
            $union->unionAll($query);
        }

        return DB::query()->fromSub($union, 'results')->orderByDesc('date')->orderBy('kind')->orderBy('id');
    }
}
