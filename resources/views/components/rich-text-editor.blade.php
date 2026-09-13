@props(['name' => 'body_html', 'value' => ''])
@php
    $safeValue = app(\App\Support\ContentSanitizer::class)->sanitize((string) $value);
    $toolbarGroups = [
        [['command' => 'formatBlock', 'label' => '見出し', 'icon' => 'title_24dp_1F1F1F.svg']],
        [
            ['command' => 'justifyRight', 'label' => '右寄せ', 'icon' => 'format_align_right_24dp_1F1F1F.svg'],
            ['command' => 'justifyCenter', 'label' => '中央寄せ', 'icon' => 'format_align_center_24dp_1F1F1F.svg'],
            ['command' => 'justifyLeft', 'label' => '左寄せ', 'icon' => 'format_align_left_24dp_1F1F1F.svg'],
        ],
        [
            ['command' => 'fontSize', 'label' => '文字を大きく', 'value' => '5', 'icon' => 'text_increase_24dp_1F1F1F.svg'],
            ['command' => 'fontSize', 'label' => '文字を小さく', 'value' => '2', 'icon' => 'text_decrease_24dp_1F1F1F.svg'],
        ],
        [
            ['command' => 'bold', 'label' => '太字', 'icon' => 'format_bold_24dp_1F1F1F.svg'],
            ['command' => 'italic', 'label' => '斜体', 'icon' => 'format_italic_24dp_1F1F1F.svg'],
            ['command' => 'underline', 'label' => 'アンダーライン', 'icon' => 'format_underlined_24dp_1F1F1F.svg'],
            ['command' => 'strikeThrough', 'label' => '取り消し線', 'icon' => 'strikethrough_s_24dp_1F1F1F.svg'],
        ],
        [
            ['command' => 'superscript', 'label' => '上付き', 'icon' => 'superscript_24dp_1F1F1F.svg'],
            ['command' => 'subscript', 'label' => '下付き', 'icon' => 'subscript_24dp_1F1F1F.svg'],
        ],
        [
            ['command' => 'foreColor', 'label' => '文字色', 'icon' => 'format_color_text_24dp_1F1F1F.svg', 'colors' => ['FFFFFF','000000','C0C0C0','808080','FF0000','800000','FFFF00','808000','00FF00','008000','00FFFF','008080','0000FF','000080','FF00FF','800080']],
            ['command' => 'hiliteColor', 'label' => '背景色', 'icon' => 'format_color_fill_24dp_1F1F1F.svg', 'colors' => ['FFD1D1','FFD1E8','FFD1FF','E8D1FF','D1D1FF','D1E8FF','D1FFFF','D1FFE8','D1FFD1','E8FFD1','FFFFD1','FFE8D1']],
        ],
        [
            ['command' => 'insertUnorderedList', 'label' => '箇条書き', 'icon' => 'format_list_bulleted_24dp_1F1F1F.svg'],
            ['command' => 'insertOrderedList', 'label' => '番号', 'icon' => 'format_list_numbered_24dp_1F1F1F.svg'],
        ],
        [['command' => 'removeFormat', 'label' => '書式解除', 'icon' => 'format_clear_24dp_1F1F1F.svg']],
        [
            ['command' => 'createLink', 'label' => 'リンク', 'icon' => 'insert_link_24dp_1F1F1F.svg'],
            ['command' => 'unlink', 'label' => 'リンク解除', 'icon' => 'link_off_24dp_1F1F1F.svg'],
        ],
    ];
@endphp
<div data-rich-text class="rounded border">
    <div class="flex flex-wrap items-stretch gap-1 border-b bg-zinc-50 p-2 dark:bg-zinc-900">
        @foreach($toolbarGroups as $group)
            <div class="flex flex-wrap gap-1">
                @foreach($group as $item)
                    @if(isset($item['colors']))
                        <details data-rich-text-palette class="relative">
                            <summary class="cursor-pointer list-none rounded border bg-white p-1 dark:bg-zinc-800" title="{{ $item['label'] }}">
                                <img class="h-6 w-6 dark:invert" src="{{ asset('assets/editor/'.$item['icon']) }}" alt="{{ $item['label'] }}">
                            </summary>
                            <div class="absolute z-10 mt-1 grid w-36 grid-cols-4 gap-1 rounded border bg-white p-2 shadow dark:bg-zinc-900">
                                @foreach($item['colors'] as $color)
                                    <button type="button" data-rich-text-swatch data-command="{{ $item['command'] }}" data-value="#{{ $color }}" data-swatch="{{ $color }}" class="h-7 w-7 shrink-0 rounded border border-zinc-400" aria-label="{{ $item['label'] }} #{{ $color }}" title="#{{ $color }}"></button>
                                @endforeach
                            </div>
                        </details>
                    @else
                        <button type="button" data-rich-text-control data-command="{{ $item['command'] }}" @isset($item['value']) data-value="{{ $item['value'] }}" @endisset class="rounded border bg-white p-1 dark:bg-zinc-800" title="{{ $item['label'] }}">
                            <img class="h-6 w-6 dark:invert" src="{{ asset('assets/editor/'.$item['icon']) }}" alt="{{ $item['label'] }}">
                        </button>
                    @endif
                @endforeach
            </div>
            @unless($loop->last)
                <span role="separator" aria-orientation="vertical" class="mx-1 w-px self-stretch bg-zinc-300 dark:bg-zinc-600"></span>
            @endunless
        @endforeach
    </div>
    <div contenteditable="true" class="rich-text-content min-h-64 p-4 outline-none" role="textbox" aria-multiline="true">{!! $safeValue !!}</div>
    <input type="hidden" name="{{ $name }}" value="{{ $safeValue }}">
</div>
