<?php

return [
    'password' => [
        'letters' => ':attributeには、英字を1文字以上含めてください。',
        'mixed' => ':attributeには、英大文字と英小文字をそれぞれ1文字以上含めてください。',
        'numbers' => ':attributeには、数字を1文字以上含めてください。',
        'symbols' => ':attributeには、記号を1文字以上含めてください。',
        'uncompromised' => '入力された:attributeは漏洩した可能性があります。別の:attributeを指定してください。',
    ],

    'attributes' => [
        'password' => 'パスワード',
        'password_confirmation' => 'パスワード（確認）',
    ],
];
