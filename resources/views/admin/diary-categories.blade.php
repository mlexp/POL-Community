<x-layouts::app title="日記カテゴリ">
<main class="max-w-6xl p-6">
<h1 class="mb-4 text-2xl font-semibold">日記カテゴリ</h1>
@include('admin._nav')
<form method="POST" action="{{ route('admin.diary-categories.store') }}" class="mb-8 grid items-end gap-3 rounded border p-4 md:grid-cols-6">
@csrf
<label>名称<input class="mt-1 w-full rounded border p-2" name="name" required></label>
<label>スラッグ<input class="mt-1 w-full rounded border p-2" name="slug" required></label>
<label class="md:col-span-2">説明<input class="mt-1 w-full rounded border p-2" name="description"></label>
<label>並び順<input class="mt-1 w-full rounded border p-2" type="number" min="0" max="65535" name="sort_order" value="0"></label>
<button class="h-10 w-[120px] rounded bg-zinc-900 p-2 text-white">追加</button>
</form>
@foreach($categories as $category)
<div class="mb-4 border-t pt-3">
<form method="POST" action="{{ route('admin.diary-categories.update',$category->id) }}" class="grid items-end gap-3 md:grid-cols-7">
@csrf
@method('PUT')
<label>名称<input class="mt-1 w-full rounded border p-1" name="name" value="{{ $category->name }}"></label>
<label>スラッグ<input class="mt-1 w-full rounded border p-1" name="slug" value="{{ $category->slug }}"></label>
<label class="md:col-span-2">説明<input class="mt-1 w-full rounded border p-1" name="description" value="{{ $category->description }}"></label>
<label>並び順<input class="mt-1 w-full rounded border p-1" type="number" min="0" max="65535" name="sort_order" value="{{ $category->sort_order }}"></label>
<button class="h-9 w-[120px] rounded border">保存</button>
@if($category->slug !== 'uncategorized')
<button type="submit" form="diary-category-delete-{{ $category->id }}" class="h-9 justify-self-end rounded border border-red-700 px-3 py-1 text-red-700">削除</button>
@endif
</form>
@if($category->slug !== 'uncategorized')
<form id="diary-category-delete-{{ $category->id }}" class="hidden" method="POST" action="{{ route('admin.diary-categories.destroy',$category->id) }}" onsubmit="return confirm('削除しますか？')">
@csrf
@method('DELETE')
</form>
@endif
</div>
@endforeach
</main>
</x-layouts::app>
