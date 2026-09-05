<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\CommunityPost;
use App\Models\Diary;
use App\Models\User;
use App\Support\ContentAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AttachmentPolicy
{
    public function view(?User $user, Attachment $attachment): bool
    {
        if ($attachment->status !== 'ready') {
            return false;
        }
        $member = app(ContentAccess::class)->member($user);
        if ($member && ($user?->id === $attachment->uploaded_by_user_id || $user?->role === 'admin')) {
            return true;
        }
        $links = DB::table('attachables')->where('attachment_id', $attachment->id)->get();
        foreach ($links as $link) {
            $parent = match ($link->attachable_type) {
                'diary' => Diary::find($link->attachable_id),
                'community_post' => CommunityPost::find($link->attachable_id),
                default => null,
            };
            if ($parent !== null && Gate::forUser($user)->allows('view', $parent)) {
                return true;
            }
        }

        // Unattached uploads stay private unless an administrator publishes them as a site image.
        return $attachment->visibility === 'public' && (
            DB::table('banners')->where('image_attachment_id', $attachment->id)->where('is_enabled', true)->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->exists()
            || DB::table('site_assets')->where('attachment_id', $attachment->id)->exists()
        );
    }
}
