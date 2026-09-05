<?php

namespace App\Policies;

use App\Models\DiaryComment;
use App\Models\User;

class DiaryCommentPolicy
{
    public function delete(User $user, DiaryComment $comment): bool
    {
        return $comment->user_id === $user->id || $user->role === 'admin';
    }
}
