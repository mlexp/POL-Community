<?php

use App\Concerns\ProfileValidationRules;
/* @chisel-email-verification */
use Illuminate\Contracts\Auth\MustVerifyEmail;
/* @end-chisel-email-verification */
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $display_name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->display_name = Auth::user()->display_name;
        $this->email = Auth::user()->email ?? '';
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));
        $validated['email'] = filled($validated['email']) ? $validated['email'] : null;

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /* @chisel-email-verification */
    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
    /* @end-chisel-email-verification */
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        @if(session('status'))<div class="mb-4 rounded bg-green-50 p-3 text-green-900">{{ session('status') }}</div>@endif
        @php($avatarError = session('avatar_error'))
        <div class="mb-6 flex flex-wrap items-center gap-4"><img class="h-20 w-20 rounded object-cover" src="{{ app(\App\Support\AvatarUrl::class)->user(auth()->user()) }}" alt=""><form class="space-y-2" method="POST" enctype="multipart/form-data" action="{{ route('profile.avatar.update') }}">@csrf<input class="block w-full cursor-pointer rounded border bg-white text-sm file:mr-4 file:cursor-pointer file:border-0 file:bg-zinc-800 file:px-4 file:py-2 file:text-white hover:file:bg-zinc-700" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required><button type="submit" class="rounded border px-3 py-2">画像を設定</button><p class="text-xs text-zinc-500">JPEG・PNG・WebP、上限 {{ number_format(min(app(\App\Support\SiteSettings::class)->get('upload.image_max_bytes',1048576),config('content.image_hard_max_bytes'))/1024) }} KiB</p>@if($avatarError)<p class="text-sm text-red-700">{{ $avatarError }}</p>@endif</form>@if(auth()->user()->avatar_attachment_id)<form method="POST" action="{{ route('profile.avatar.destroy') }}">@csrf @method('DELETE')<button class="rounded bg-red-700 px-3 py-2 text-white hover:bg-red-600">画像を削除</button></form>@endif</div>
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="display_name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" autocomplete="email" />

                {{-- @chisel-email-verification --}}
                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
                {{-- @end-chisel-email-verification --}}
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

            </div>
        </form>

        {{-- @chisel-email-verification --}}
        @if ($this->showDeleteUser)
        {{-- @end-chisel-email-verification --}}
            <livewire:pages::settings.delete-user-form />
        {{-- @chisel-email-verification --}}
        @endif
        {{-- @end-chisel-email-verification --}}
    </x-pages::settings.layout>
</section>
