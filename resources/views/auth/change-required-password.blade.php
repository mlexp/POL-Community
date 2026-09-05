<x-layouts::auth title="パスワード変更">
    <div class="flex flex-col gap-6"><x-auth-header title="パスワード変更が必要です" description="続行する前に、新しいパスワードへ変更してください。" />
    <form method="POST" action="{{ route('password.change-required.update') }}" class="space-y-5">@csrf @method('PUT')
        <flux:input name="current_password" type="password" label="現在のパスワード" required autofocus />
        <flux:input name="password" type="password" label="新しいパスワード" required />
        <flux:input name="password_confirmation" type="password" label="新しいパスワード（確認）" required />
        <flux:button variant="primary" type="submit" class="w-full">変更して続行</flux:button>
    </form></div>
</x-layouts::auth>
