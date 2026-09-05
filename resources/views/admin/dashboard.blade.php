<x-layouts::app title="管理">
    <main class="p-6"><h1 class="mb-4 text-2xl font-semibold">管理</h1>@include('admin._nav')
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded border p-4"><div class="text-sm text-zinc-500">承認待ち</div><div class="text-3xl">{{ $pendingUsers }}</div></div>
            <div class="rounded border p-4"><div class="text-sm text-zinc-500">RSSソース</div><div class="text-3xl">{{ $feedSources }}</div></div>
            <div class="rounded border p-4"><div class="text-sm text-zinc-500">バナー</div><div class="text-3xl">{{ $banners }}</div></div>
        </div>
    </main>
</x-layouts::app>
