@auth
<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>

@else
<x-public-layout :title="$title ?? null">{{ $slot }}</x-public-layout>
@endauth
