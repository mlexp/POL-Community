<x-layouts::app title="バナー管理">
<main class="max-w-5xl p-6">
<h1 class="mb-4 text-2xl font-semibold">バナー管理</h1>
@include('admin._nav')
@foreach(collect([null])->concat($banners) as $banner)
<form method="POST" enctype="multipart/form-data" action="{{ $banner?route('admin.banners.update',$banner->id):route('admin.banners.store') }}" class="banner-form mb-6 space-y-4 rounded border p-4">
@csrf
@if($banner)@method('PUT')@endif
<h2 class="font-semibold">{{ $banner?'バナー編集':'バナー追加' }}</h2>
<label class="block">管理名<input class="mt-1 w-full rounded border p-2" name="name" value="{{ $banner?->name }}" required></label>
<label class="block">バナー画像<select class="image-select mt-1 w-full rounded border p-2" name="image_attachment_id"><option value="">登録済み画像を選択</option>@foreach($images as $image)<option value="{{ $image->id }}" data-preview="{{ route('images.show',[$image->id,'thumbnail']) }}" @selected($banner?->image_attachment_id===$image->id)>{{ $image->original_name }}</option>@endforeach</select></label>
<label class="block">新しくアップロード<input class="image-file mt-1 block w-full cursor-pointer rounded border bg-white text-sm file:mr-4 file:cursor-pointer file:border-0 file:bg-zinc-800 file:px-4 file:py-2 file:text-white hover:file:bg-zinc-700" type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
<img class="image-preview hidden max-h-40 rounded border object-contain" alt="バナープレビュー">
<label class="block">代替テキスト<input class="mt-1 w-full rounded border p-2" name="alt_text" value="{{ $banner?->alt_text }}" required></label>
<label class="block">リンク先（HTTPS）<input class="mt-1 w-full rounded border p-2" type="url" name="destination_url" value="{{ $banner?->destination_url }}" required></label>
<label class="block">表示場所<select class="mt-1 w-full rounded border p-2" name="placement">@foreach(['home_header'=>'トップ上部','home_sidebar'=>'トップ右側','home_footer'=>'トップ下部'] as $v=>$label)<option value="{{ $v }}" @selected($banner?->placement===$v)>{{ $label }}</option>@endforeach</select></label>
<label class="block">表示順<input class="mt-1 w-full rounded border p-2" type="number" min="0" max="65535" name="sort_order" value="{{ $banner?->sort_order ?? 0 }}"></label>
<label class="flex gap-2"><input type="hidden" name="rel_sponsored" value="0"><input type="checkbox" name="rel_sponsored" value="1" @checked($banner?->rel_sponsored)>このバナーを広告として扱う</label>
<label class="flex gap-2"><input type="hidden" name="is_enabled" value="0"><input type="checkbox" name="is_enabled" value="1" @checked($banner?->is_enabled)>このバナーを表示する</label>
<div class="flex items-center gap-4">
<button class="w-[120px] rounded bg-zinc-900 p-2 text-white">{{ $banner?'保存':'追加' }}</button>
@if($banner)
<x-preserve-updated-at />
<button type="submit" form="banner-delete-{{ $banner->id }}" class="ml-auto rounded border border-red-700 px-3 py-1 text-red-700">削除</button>
@endif
</div>
</form>
@if($banner)
<form id="banner-delete-{{ $banner->id }}" class="hidden" method="POST" action="{{ route('admin.banners.destroy',$banner->id) }}">
@csrf
@method('DELETE')
</form>
@endif
@endforeach
<script>document.querySelectorAll('.banner-form').forEach(form=>{const select=form.querySelector('.image-select'),file=form.querySelector('.image-file'),preview=form.querySelector('.image-preview'),show=src=>{preview.src=src;preview.classList.toggle('hidden',!src)};select.addEventListener('change',()=>show(select.selectedOptions[0]?.dataset.preview||''));file.addEventListener('change',()=>show(file.files[0]?URL.createObjectURL(file.files[0]):''));show(select.selectedOptions[0]?.dataset.preview||'')})</script>
</main>
</x-layouts::app>
