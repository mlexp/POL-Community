<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function update(User $actor, User $subject): bool
    {
        return $actor->role === 'admin' && $actor->status === 'active';
    }
}
