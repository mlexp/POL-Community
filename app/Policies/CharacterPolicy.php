<?php

namespace App\Policies;

use App\Models\Character;
use App\Models\User;

class CharacterPolicy
{
    public function view(User $user, Character $character): bool
    {
        return $character->user_id === $user->id || $user->role === 'admin' || $character->visibility !== 'private';
    }

    public function update(User $user, Character $character): bool
    {
        return $character->user_id === $user->id || $user->role === 'admin';
    }

    public function delete(User $user, Character $character): bool
    {
        return $this->update($user, $character);
    }
}
