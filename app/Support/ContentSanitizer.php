<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class ContentSanitizer
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            // The editor limit is character based, while Symfony's default is
            // 20,000 bytes and can cut multibyte text in the middle.
            ->withMaxInputLength(1_000_000)
            ->allowSafeElements()
            ->allowElement('span', ['data-text-color', 'data-background-color'])
            ->blockElement('img')
            ->dropElement('iframe')
            ->dropElement('object')
            ->dropElement('embed')
            ->allowLinkSchemes(['https', 'mailto'])
            ->allowRelativeLinks()
            ->forceAttribute('a', 'rel', 'noopener noreferrer');
        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function sanitize(string $html): string
    {
        return trim($this->sanitizer->sanitize($html));
    }
}
