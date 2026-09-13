<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class JapaneseValidationTranslationTest extends TestCase
{
    public function test_password_validation_messages_are_available_in_japanese(): void
    {
        app()->setLocale('ja');

        $validator = Validator::make(
            ['password' => 'lowercasepassword'],
            ['password' => Password::min(12)->mixedCase()],
        );

        $this->assertSame(
            'パスワードには、英大文字と英小文字をそれぞれ1文字以上含めてください。',
            $validator->errors()->first('password'),
        );
        $this->assertSame(':attributeには、英字を1文字以上含めてください。', __('validation.password.letters'));
        $this->assertSame(':attributeには、数字を1文字以上含めてください。', __('validation.password.numbers'));
        $this->assertSame(':attributeには、記号を1文字以上含めてください。', __('validation.password.symbols'));
        $this->assertSame('入力された:attributeは漏洩した可能性があります。別の:attributeを指定してください。', __('validation.password.uncompromised'));
    }
}
