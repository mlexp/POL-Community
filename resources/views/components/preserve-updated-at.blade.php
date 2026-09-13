@if(auth()->user()?->role === 'admin')
<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="preserve_updated_at" value="1" @checked(old('preserve_updated_at'))> 更新日時を維持する</label>
@endif
