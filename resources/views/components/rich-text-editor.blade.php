@props(['name' => 'body_html', 'value' => ''])
@php($safeValue = app(\App\Support\ContentSanitizer::class)->sanitize((string) $value))
<div data-rich-text class="rounded border">
    <div class="flex flex-wrap gap-1 border-b bg-zinc-50 p-2 dark:bg-zinc-900">
        @foreach(['bold'=>'太字','italic'=>'斜体','insertUnorderedList'=>'箇条書き','insertOrderedList'=>'番号','formatBlock'=>'見出し','createLink'=>'リンク','removeFormat'=>'書式解除'] as $command=>$label)
            <button type="button" data-command="{{ $command }}" class="rounded border bg-white px-2 py-1 text-sm dark:bg-zinc-800">{{ $label }}</button>
        @endforeach
    </div>
    <div contenteditable="true" class="rich-text-content min-h-64 p-4 outline-none" role="textbox" aria-multiline="true">{!! $safeValue !!}</div>
    <input type="hidden" name="{{ $name }}" value="{{ $safeValue }}">
</div>
