@if($errors->any())<ul role="alert" class="my-4 list-disc rounded border border-red-500 p-4 pl-8">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
