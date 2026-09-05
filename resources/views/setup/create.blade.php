<x-layouts::auth title="POL Community 初期セットアップ">
    <div class="flex flex-col gap-6">
        <x-auth-header title="初期管理者の作成" description="この画面は初期化完了後に利用できなくなります。" />
        <form method="POST" action="{{ route('setup.store') }}" class="flex flex-col gap-6">
            @csrf
            <flux:input name="setup_token" label="セットアップトークン" type="password" required autocomplete="off" />
            <flux:input name="login_id" label="ログインID" :value="old('login_id')" required autocomplete="username" />
            <flux:input name="display_name" label="表示名" :value="old('display_name')" required autocomplete="name" />
            <flux:input name="email" label="メールアドレス" :value="old('email')" type="email" required autocomplete="email" />
            <flux:input name="password" label="パスワード" type="password" required autocomplete="new-password" viewable />
            <flux:input name="password_confirmation" label="パスワード（確認）" type="password" required autocomplete="new-password" viewable />
            <flux:button type="submit" variant="primary">初期管理者を作成</flux:button>
        </form>
    </div>
</x-layouts::auth>
