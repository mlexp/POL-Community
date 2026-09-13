<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ContentAccess
{
    public function member(?User $user): bool
    {
        return $user !== null && $user->status === 'active' && ! $user->password_reset_required;
    }

    public function visibility(Builder $query, ?User $user, string $column = 'visibility'): Builder
    {
        return $query->whereIn($column, $this->member($user) ? ['public', 'members'] : ['public']);
    }

    public function diaries(?User $user): Builder
    {
        return $this->visibility(DB::table('diaries')->whereNull('diaries.deleted_at')->where('diaries.status', 'published')->where('diaries.published_at', '<=', now())->whereExists(fn ($q) => $q->selectRaw('1')->from('users')->whereColumn('users.id', 'diaries.user_id')->whereNull('users.deleted_at')), $user, 'diaries.visibility');
    }

    public function threads(?User $user): Builder
    {
        return $this->visibility(DB::table('community_threads')->whereNull('community_threads.deleted_at')->whereIn('community_threads.status', ['open', 'closed']), $user, 'community_threads.visibility');
    }

    public function announcements(?User $user): Builder
    {
        return $this->visibility(DB::table('announcements')->whereNull('announcements.deleted_at')->where('announcements.status', 'published')->where('announcements.published_at', '<=', now()), $user, 'announcements.visibility');
    }
}
