<nav class="mb-6 flex flex-wrap gap-3 text-sm">
    <a class="underline" href="{{ route('admin.dashboard') }}">概要</a>
    <a class="underline" href="{{ route('admin.settings') }}">サイト設定</a>
    <a class="underline" href="{{ route('admin.users') }}">ユーザー</a>
    <a class="underline" href="{{ route('admin.diary-categories') }}">日記カテゴリ</a>
    <a class="underline" href="{{ route('admin.feeds') }}">RSS</a>
    <a class="underline" href="{{ route('admin.banners') }}">バナー</a>
</nav>
@if (session('status'))
    <div class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-green-900">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-red-900"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<nav class="mb-6 flex flex-wrap gap-4"><a class="underline" href="{{ route('admin.community-categories') }}">コミュニティカテゴリ</a><a class="underline" href="{{ route('admin.announcements') }}">更新情報</a><a class="underline" href="{{ route('admin.images') }}">サイト画像</a></nav>
