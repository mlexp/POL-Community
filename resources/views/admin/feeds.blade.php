<x-layouts::app title="RSS管理">
<main class="p-6">
<h1 class="mb-4 text-2xl font-semibold">RSS管理</h1>
@include('admin._nav')
<x-content-errors />

<form method="POST" action="{{ route('admin.feeds.store') }}" class="mb-8">
@csrf
<fieldset class="rounded border p-4">
<legend class="px-1 font-semibold">RSSを追加</legend>
<div class="grid items-end gap-2 md:grid-cols-7">
<label>名称<input class="mt-1 block w-full rounded border p-2" name="name" required></label>
<label class="md:col-span-2">URL<input class="mt-1 block w-full rounded border p-2" type="url" name="url" required></label>
<label>公開範囲<select class="mt-1 block w-full rounded border p-2" name="visibility"><option value="public">公開</option><option value="members">メンバーのみ</option></select></label>
<label>取得間隔（分）<input class="mt-1 block w-full rounded border p-2" type="number" name="fetch_interval_minutes" value="60" min="5" max="10080" required></label>
<label class="flex h-10 items-center gap-2"><input type="hidden" name="is_enabled" value="0"><input type="checkbox" name="is_enabled" value="1">有効</label>
<button class="h-10 w-[120px] rounded bg-zinc-900 px-4 text-white">追加</button>
</div>
</fieldset>
</form>

@foreach($feeds as $feed)
<p class="text-sm">最終取得: {{ $feed->last_fetched_at ?? '未取得' }} · 最終成功: {{ $feed->last_success_at ?? 'なし' }}</p>
@if($feed->last_error)<p class="text-sm text-red-600">{{ $feed->last_error }}</p>@endif
<form method="POST" action="{{ route('admin.feeds.update',$feed->id) }}" class="mb-3 grid items-end gap-2 border-t py-3 md:grid-cols-8">
@csrf
@method('PUT')
<label>名称<input class="mt-1 block w-full rounded border p-2" name="name" value="{{ $feed->name }}" required></label>
<label class="md:col-span-2">URL<input class="mt-1 block w-full rounded border p-2" type="url" name="url" value="{{ $feed->url }}" required></label>
<label>公開範囲<select class="mt-1 block w-full rounded border p-2" name="visibility">@foreach(['public'=>'公開','members'=>'メンバーのみ'] as $value=>$label)<option value="{{ $value }}" @selected($feed->visibility===$value)>{{ $label }}</option>@endforeach</select></label>
<label>取得間隔（分）<input class="mt-1 block w-full rounded border p-2" type="number" name="fetch_interval_minutes" value="{{ $feed->fetch_interval_minutes }}" min="5" max="10080" required></label>
<div class="flex items-end gap-3 md:col-span-2">
<label class="flex h-10 items-center gap-2"><input type="hidden" name="is_enabled" value="0"><input type="checkbox" name="is_enabled" value="1" @checked($feed->is_enabled)>有効</label>
<button class="h-10 w-[120px] rounded border px-4">保存</button>
</div>
<button type="submit" form="feed-delete-{{ $feed->id }}" class="h-10 justify-self-end rounded border border-red-700 px-3 py-1 text-red-700">削除</button>
</form>
<form id="feed-delete-{{ $feed->id }}" class="hidden" method="POST" action="{{ route('admin.feeds.destroy',$feed->id) }}" onsubmit="return confirm('削除しますか？')">
@csrf
@method('DELETE')
</form>
@endforeach
</main>
</x-layouts::app>
