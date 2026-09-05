<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /** @return array<string, array<int, ValidationRule|array<mixed>|string>> */
    protected function profileRules(?string $userId = null): array
    {
        return [
            'display_name' => $this->displayNameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /** @return array<int, ValidationRule|array<mixed>|string> */
    protected function displayNameRules(): array
    {
        return ['required', 'string', 'max:100'];
    }

    /** @return array<int, ValidationRule|array<mixed>|string> */
    protected function emailRules(?string $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:254',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
