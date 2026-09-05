<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /** @param array<string, string> $input */
    public function create(array $input): User
    {
        $settings = app(SiteSettings::class);
        if (! $settings->boolean('registration.enabled', true)) {
            throw ValidationException::withMessages(['email' => '現在、新規登録を受け付けていません。']);
        }

        Validator::make($input, [
            ...$this->profileRules(),
            'login_id' => ['required', 'string', 'alpha_dash', 'max:64', Rule::unique(User::class, 'login_id')],
            'password' => $this->passwordRules(),
        ])->validate();

        $requiresApproval = $settings->boolean('registration.require_admin_approval', true);

        return User::create([
            'login_id' => Str::lower($input['login_id']),
            'display_name' => $input['display_name'],
            'email' => Str::lower($input['email']),
            'password' => $input['password'],
            'password_reset_required' => false,
            'role' => 'member',
            'status' => $requiresApproval ? 'pending' : 'active',
        ]);
    }
}
