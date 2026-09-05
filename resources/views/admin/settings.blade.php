<x-layouts::app title="サイト設定">
<main class="max-w-3xl p-6"><h1 class="mb-4 text-2xl font-semibold">サイト設定</h1>@include('admin._nav')
<form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-4">@csrf @method('PUT')
@php($fields = ['site_title'=>['サイト名','site.title','text'],'site_description'=>['説明','site.description','text'],'site_footer_text'=>['フッター','site.footer_text','text'],'site_timezone'=>['タイムゾーン','site.timezone','text'],'home_announcement_limit'=>['更新情報件数','home.announcement_limit','number'],'home_diary_limit'=>['日記件数','home.diary_limit','number'],'home_feed_item_limit'=>['RSS件数','home.feed_item_limit','number'],'upload_image_max_bytes'=>['画像上限（bytes）','upload.image_max_bytes','number']])
@foreach($fields as $name => [$label,$key,$type])<label class="block"><span class="block text-sm">{{ $label }}</span><input class="w-full rounded border p-2" type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $settings[$key] ?? '') }}" required></label>@endforeach
@foreach(['registration_enabled'=>['登録を許可','registration.enabled'],'registration_require_admin_approval'=>['登録に管理者承認を必要とする','registration.require_admin_approval'],'community_posting_enabled'=>['コミュニティ投稿を許可','community.posting_enabled'],'comments_enabled'=>['コメントを許可','comments.enabled']] as $name => [$label,$key])
<label class="flex gap-2"><input type="hidden" name="{{ $name }}" value="0"><input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $settings[$key] ?? false))>{{ $label }}</label>
@endforeach
<button class="rounded bg-zinc-900 px-4 py-2 text-white">保存</button></form></main></x-layouts::app>
