<?php

namespace Tests\Unit;

use Tests\TestCase;

class JapanesePasswordTranslationTest extends TestCase
{
    public function test_password_reset_messages_are_available_in_japanese(): void
    {
        app()->setLocale('ja');

        $this->assertSame('パスワードを変更しました。', __('passwords.reset'));
        $this->assertSame('パスワード再設定用のリンクをメールで送信しました。', __('passwords.sent'));
        $this->assertSame('しばらく待ってから、もう一度お試しください。', __('passwords.throttled'));
        $this->assertSame('このパスワード再設定リンクは無効です。', __('passwords.token'));
        $this->assertSame('このメールアドレスに一致するユーザーが見つかりません。', __('passwords.user'));
    }
}
