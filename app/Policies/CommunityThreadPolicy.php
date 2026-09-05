<?php

namespace App\Policies;

use App\Models\CommunityThread;
use App\Models\User;
use App\Support\ContentAccess;
use App\Support\SiteSettings;

class CommunityThreadPolicy
{
    public function view(?User $user, CommunityThread $thread): bool
    {
        if ($this->owner($user, $thread)) {
            return true;
        }

        return in_array($thread->status, ['open', 'closed'], true) && ($thread->visibility === 'public' || ($thread->visibility === 'members' && app(ContentAccess::class)->member($user)));
    }

    public function create(User $user): bool
    {
        return app(ContentAccess::class)->member($user) && app(SiteSettings::class)->boolean('community.posting_enabled', true);
    }

    public function reply(User $user, CommunityThread $thread): bool
    {
        return $this->create($user) && $this->view($user, $thread) && $thread->status === 'open';
    }

    public function update(User $user, CommunityThread $thread): bool
    {
        return $this->owner($user, $thread);
    }

    public function delete(User $user, CommunityThread $thread): bool
    {
        return $this->owner($user, $thread);
    }

    private function owner(?User $user, CommunityThread $thread): bool
    {
        return app(ContentAccess::class)->member($user) && ($user?->id === $thread->created_by_user_id || $user?->role === 'admin');
    }
}
