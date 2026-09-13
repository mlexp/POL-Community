<?php

namespace App\Policies;

use App\Models\Diary;
use App\Models\User;
use App\Support\ContentAccess;

class DiaryPolicy
{
    public function view(?User $user, Diary $diary): bool
    {
        if (! User::query()->whereKey($diary->user_id)->exists()) {
            return false;
        }
        $member = app(ContentAccess::class)->member($user);
        if ($member && ($user?->id === $diary->user_id || $user?->role === 'admin')) {
            return true;
        }

        return $diary->status === 'published' && $diary->published_at !== null && $diary->published_at->lessThanOrEqualTo(now()) && ($diary->visibility === 'public' || ($diary->visibility === 'members' && $member));
    }

    public function update(User $user, Diary $diary): bool
    {
        return app(ContentAccess::class)->member($user) && ($diary->user_id === $user->id || $user->role === 'admin');
    }

    public function delete(User $user, Diary $diary): bool
    {
        return $this->update($user, $diary);
    }
}
