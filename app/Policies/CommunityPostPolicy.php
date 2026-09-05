<?php

namespace App\Policies;

use App\Models\CommunityPost;
use App\Models\CommunityThread;
use App\Models\User;
use App\Support\ContentAccess;
use Illuminate\Support\Facades\Gate;

class CommunityPostPolicy
{
    public function view(?User $user, CommunityPost $post): bool
    {
        $thread = CommunityThread::find($post->thread_id);

        return $thread !== null && $post->status === 'visible' && Gate::forUser($user)->allows('view', $thread);
    }

    public function update(User $user, CommunityPost $post): bool
    {
        return app(ContentAccess::class)->member($user) && ($user->id === $post->user_id || $user->role === 'admin') && $this->view($user, $post);
    }

    public function delete(User $user, CommunityPost $post): bool
    {
        return $this->update($user, $post);
    }
}
