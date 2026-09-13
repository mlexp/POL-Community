<x-layouts::app title="更新情報">
<main class="max-w-5xl p-6">
<h1 class="mb-5 text-2xl font-bold">更新情報</h1>
@include('admin._nav')
@foreach(collect([null])->concat($announcements->items()) as $item)
<form class="my-5" method="POST" action="{{ $item?route('admin.announcements.update',$item->id):route('admin.announcements.store') }}">
@csrf
@if($item)@method('PUT')@endif
<fieldset class="space-y-4 rounded border p-4">
@if(!$item)<legend class="px-1 font-semibold">更新情報を追加</legend>@endif
<label class="block">タイトル<input name="title" class="mt-1 block w-full rounded border p-2" value="{{ $item?->title }}" required></label>
<label class="block">概要<textarea name="summary" maxlength="500" class="mt-1 block w-full rounded border p-2">{{ $item?->summary }}</textarea></label>
<label class="block">詳細URL<input type="url" name="url" class="mt-1 block w-full rounded border p-2" value="{{ $item?->url }}"></label>
<div class="grid gap-4 md:grid-cols-3">
<label>公開範囲<select name="visibility" class="mt-1 block w-full rounded border p-2">@foreach(['public'=>'公開','members'=>'メンバーのみ','private'=>'非公開'] as $value=>$label)<option value="{{ $value }}" @selected($item?->visibility===$value)>{{ $label }}</option>@endforeach</select></label>
<label>状態<select name="status" class="mt-1 block w-full rounded border p-2">@foreach(['draft'=>'下書き','published'=>'公開','archived'=>'アーカイブ'] as $value=>$label)<option value="{{ $value }}" @selected($item?->status===$value)>{{ $label }}</option>@endforeach</select></label>
<label>公開日時（UTC）<input type="datetime-local" name="published_at" class="mt-1 block w-full rounded border p-2" value="{{ $item?->published_at?\Carbon\CarbonImmutable::parse($item->published_at)->format('Y-m-d\TH:i'):'' }}"></label>
</div>
<div class="flex flex-wrap items-center gap-4">
<button class="w-[120px] rounded border px-4 py-2">{{ $item ? '保存' : '追加' }}</button>
@if($item)<x-preserve-updated-at /><button type="submit" form="announcement-delete-{{ $item->id }}" class="ml-auto rounded border border-red-700 px-3 py-1 text-red-700">削除</button>@endif
</div>
</fieldset>
</form>
@if($item)
<form id="announcement-delete-{{ $item->id }}" class="hidden" method="POST" action="{{ route('admin.announcements.destroy',$item->id) }}">
@csrf
@method('DELETE')
</form>
@endif
@endforeach
{{ $announcements->links() }}
</main>
</x-layouts::app>
