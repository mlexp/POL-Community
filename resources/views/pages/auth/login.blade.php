<x-layouts::auth title="ログイン">
    <div class="flex flex-col gap-6">
        <x-auth-header title="ログイン" description="ログインIDまたはメールアドレスとパスワードを入力してください" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        {{-- @chisel-passkeys --}}
        <x-passkey-verify label="パスキーでログイン" loading-label="認証中…" separator="またはパスワードでログイン" />
        {{-- @end-chisel-passkeys --}}

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Login ID or Email -->
            <flux:input
                name="login"
                label="ログインIDまたはメールアドレス"
                :value="old('login')"
                type="text"
                required
                autofocus
                autocomplete="username"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    label="パスワード"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="パスワード"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        パスワードをお忘れですか？
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" label="ログイン状態を保持する" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    ログイン
                </flux:button>
            </div>
        </form>

        {{-- @chisel-registration --}}
        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>アカウントをお持ちでないですか？</span>
            <flux:link :href="route('register')" wire:navigate>新規登録</flux:link>
        </div>
        {{-- @end-chisel-registration --}}
    </div>
</x-layouts::auth>
