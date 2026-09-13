<x-layouts::app title="コミュニティカテゴリ">
<main class="max-w-6xl p-6">
<h1 class="mb-4 text-2xl font-semibold">コミュニティカテゴリ</h1>
@include('admin._nav')
@foreach($categories->prepend(null) as $category)
<form class="my-5" method="POST" action="{{ $category?route('admin.community-categories.update',$category->id):route('admin.community-categories.store') }}">
@csrf
@if($category)@method('PUT')@endif
<fieldset class="rounded border p-4">
@if(!$category)<legend class="px-1 font-semibold">カテゴリ追加</legend>@endif
<div @class(['grid items-end gap-3', 'md:grid-cols-8' => $category, 'md:grid-cols-7' => !$category])>
<label>名前<input name="name" class="mt-1 block w-full rounded border p-1" value="{{ $category?->name }}" required></label>
<label>スラッグ<input name="slug" class="mt-1 block w-full rounded border p-1" value="{{ $category?->slug }}" required></label>
<label class="md:col-span-2">説明<input name="description" class="mt-1 block w-full rounded border p-1" value="{{ $category?->description }}"></label>
<label>並び順<input type="number" class="mt-1 block w-full rounded border p-1" name="sort_order" min="0" max="65535" value="{{ $category?->sort_order ?? 0 }}" required></label>
<label class="flex h-9 items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($category?->is_active ?? true)>表示</label>
<button class="h-9 w-[120px] rounded border">{{ $category ? '保存' : '追加' }}</button>
@if($category)
    @if($category->slug === 'uncategorized')
        <span class="justify-self-end text-right text-sm text-zinc-500">システムカテゴリのため削除できません</span>
    @elseif((int) $category->thread_count > 0)
        <span class="justify-self-end text-right text-sm text-zinc-500">使用中（{{ $category->thread_count }}件）のため削除できません</span>
    @else
        <button type="submit" form="community-category-delete-{{ $category->id }}" class="h-9 justify-self-end rounded border border-red-700 px-3 py-1 text-red-700">削除</button>
    @endif
@endif
</div>
</fieldset>
</form>
@if($category)
    @if($category->slug !== 'uncategorized' && (int) $category->thread_count === 0)
        <form id="community-category-delete-{{ $category->id }}" class="hidden" method="POST" action="{{ route('admin.community-categories.destroy', $category->id) }}" onsubmit="return confirm('このカテゴリを削除しますか？')">
            @csrf
            @method('DELETE')
        </form>
    @endif
    @error('category_'.$category->id)<p class="-mt-3 mb-5 text-right text-sm text-red-700">{{ $message }}</p>@enderror
@endif
@endforeach
</main>
</x-layouts::app>
