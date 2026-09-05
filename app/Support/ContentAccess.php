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
        return $this->visibility(DB::table('diaries')->whereNull('deleted_at')->where('status', 'published')->where('published_at', '<=', now()), $user);
    }

    public function threads(?User $user): Builder
    {
        return $this->visibility(DB::table('community_threads')->whereNull('deleted_at')->whereIn('status', ['open', 'closed']), $user);
    }

    public function announcements(?User $user): Builder
    {
        return $this->visibility(DB::table('announcements')->whereNull('deleted_at')->where('status', 'published')->where('published_at', '<=', now()), $user);
    }
}
