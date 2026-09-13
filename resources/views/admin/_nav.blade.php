<nav class="mb-6 flex flex-nowrap gap-1 overflow-x-auto whitespace-nowrap rounded border-b text-sm">
@foreach(['admin.dashboard'=>'概要','admin.settings'=>'サイト設定','admin.banners'=>'バナー','admin.users'=>'ユーザー','admin.content'=>'投稿検索','admin.diary-categories'=>'日記カテゴリ','admin.community-categories'=>'コミュニティカテゴリ','admin.announcements'=>'更新情報','admin.feeds'=>'RSS','admin.images'=>'画像ファイル'] as $route=>$label)<a class="rounded-t px-3 py-2 hover:bg-zinc-100 {{ request()->routeIs($route) ? 'bg-zinc-200 font-semibold' : '' }}" href="{{ route($route) }}">{{ $label }}</a>@endforeach
</nav>
@if(session('status'))<div class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-green-900">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-red-900"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
