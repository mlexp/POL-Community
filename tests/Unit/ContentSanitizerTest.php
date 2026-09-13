<?php

namespace Tests\Unit;

use App\Support\ContentSanitizer;
use Tests\TestCase;

class ContentSanitizerTest extends TestCase
{
    public function test_it_preserves_safe_multibyte_html_longer_than_twenty_thousand_bytes(): void
    {
        $html = str_repeat('日本語<br>', 3000);

        $sanitized = app(ContentSanitizer::class)->sanitize($html);

        $this->assertNotSame('', $sanitized);
        $this->assertStringContainsString('日本語', $sanitized);
    }

    public function test_it_preserves_supported_inline_text_formatting(): void
    {
        $html = '<p><u>下線</u><s>取消</s><sup>上付き</sup><sub>下付き</sub><big>大</big><small>小</small></p>';

        $sanitized = app(ContentSanitizer::class)->sanitize($html);

        $this->assertSame($html, $sanitized);
    }

    public function test_it_preserves_editor_alignment_and_palette_attributes_but_removes_styles(): void
    {
        $html = '<p align="center"><span data-text-color="FF0000" data-background-color="FFD1D1" style="position: fixed">色付き</span></p>';

        $sanitized = app(ContentSanitizer::class)->sanitize($html);

        $this->assertStringContainsString('align="center"', $sanitized);
        $this->assertStringContainsString('data-text-color="FF0000"', $sanitized);
        $this->assertStringContainsString('data-background-color="FFD1D1"', $sanitized);
        $this->assertStringNotContainsString('style=', $sanitized);
    }

    public function test_it_removes_active_html_and_dangerous_urls(): void
    {
        $html = '<script>alert(1)</script><iframe src="https://evil.example"></iframe><object data="x"></object><embed src="x"><img src=x onerror=alert(1)><form action="https://evil.example"><input autofocus onfocus=alert(1)></form><a href="javascript:alert(1)" onclick="alert(1)">危険</a><p style="position:fixed">本文</p>';

        $sanitized = app(ContentSanitizer::class)->sanitize($html);

        foreach (['script', 'iframe', 'object', 'embed', '<img', '<form', '<input', 'javascript:', 'onclick', 'onerror', 'onfocus', 'style='] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $sanitized);
        }
        $this->assertStringContainsString('危険', $sanitized);
        $this->assertStringContainsString('<p>本文</p>', $sanitized);
    }
}
