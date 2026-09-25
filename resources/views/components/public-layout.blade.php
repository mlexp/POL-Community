@props(['title' => null])
<!DOCTYPE html><html lang="ja"><head>@include('partials.head')</head><body class="min-h-screen bg-zinc-50 text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100">
@php
    $siteTitle = app(\App\Support\SiteSettings::class)->get('site.title', 'POL Community');
    $siteLogo = \Illuminate\Support\Facades\DB::table('site_assets')
        ->join('attachments', 'site_assets.attachment_id', '=', 'attachments.id')
        ->where('site_assets.slot', 'site_logo')
        ->whereNull('attachments.deleted_at')
        ->where('attachments.status', 'ready')
        ->where('attachments.visibility', 'public')
        ->value('site_assets.attachment_id');
@endphp
<header class="sticky top-0 z-50 border-b bg-zinc-50 p-5 dark:bg-zinc-900"><div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4"><a class="min-w-0 max-w-full text-xl font-bold" href="{{ route('home') }}">@if($siteLogo)<img class="h-auto max-h-16 max-w-full" src="{{ route('images.show', $siteLogo) }}" alt="{{ $siteTitle }}">@else{{ $siteTitle }}@endif</a><nav class="flex flex-1 flex-wrap items-center justify-end gap-4"><form class="flex min-w-64 flex-1 items-center justify-end" method="GET" action="{{ route('search') }}"><input class="w-full max-w-md rounded-l border p-2" type="search" name="q" maxlength="100" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="日記・コミュニティ・更新情報を検索" aria-label="日記・コミュニティ・更新情報を検索"><button class="whitespace-nowrap rounded-r border border-l-0 px-4 py-2" aria-label="検索">検索</button></form><a href="{{ route('characters.index') }}">キャラクター</a><a href="{{ route('diaries.index') }}">メンバー日記</a><a href="{{ route('community.index') }}">コミュニティ</a>@auth<a class="flex items-center gap-2" href="{{ route('characters.mine') }}"><img class="h-14 w-14 rounded-full object-cover" src="{{ app(\App\Support\AvatarUrl::class)->user(auth()->user()) }}" alt=""><span>{{ auth()->user()->display_name }}</span></a>@else<a href="{{ route('login') }}">ログイン</a>@endauth</nav></div></header>
<main class="mx-auto max-w-6xl p-5">@if(session('status'))<p class="my-3 rounded border p-3">{{ session('status') }}</p>@endif<x-content-errors />{{ $slot }}</main>
<footer class="rich-text-content mx-auto max-w-6xl border-t p-5 text-sm">{!! app(\App\Support\ContentSanitizer::class)->sanitize((string) app(\App\Support\SiteSettings::class)->get('site.footer_text','')) !!}</footer>@fluxScripts</body></html>
