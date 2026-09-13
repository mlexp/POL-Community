@props(['type','id'])
@php
$media = app(\App\Services\ContentMedia::class)->listing($type,$id);
@endphp
<div class="my-5 grid gap-4 sm:grid-cols-2">
@foreach($media['images'] as $image)<a href="{{ route('images.show',$image->id) }}"><img loading="lazy" class="max-h-96 rounded object-contain" src="{{ route('images.show',[$image->id,'thumbnail']) }}" alt="添付画像"></a>@endforeach
@foreach($media['external'] as $image)<img loading="lazy" referrerpolicy="no-referrer" class="max-h-96 rounded object-contain" src="{{ $image->url }}" alt="外部画像">@endforeach
@foreach($media['videos'] as $video)
@php
$src = null;
try { $src = app(\App\Support\MediaUrl::class)->video($video->original_url)['src']; } catch (\Illuminate\Validation\ValidationException) {}
@endphp
@if($src)<iframe class="aspect-video w-full rounded" src="{{ $src }}" title="{{ $video->provider }} 動画" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="fullscreen; encrypted-media; picture-in-picture" sandbox="allow-scripts allow-same-origin allow-presentation" allowfullscreen></iframe>@endif
@endforeach
</div>
