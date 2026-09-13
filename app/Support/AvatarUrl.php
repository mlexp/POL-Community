<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AvatarUrl
{
    public function character(object $character): string
    {
        $attachmentId = data_get($character, 'avatar_attachment_id');
        if (is_string($attachmentId) && $attachmentId !== '') {
            return route('images.show', [$attachmentId, 'thumbnail']);
        }
        $characterId = data_get($character, 'id');
        $path = DB::table('ffxi_profiles')->join('ffxi_face_types', 'ffxi_profiles.face_type_id', '=', 'ffxi_face_types.id')->where('ffxi_profiles.character_id', is_string($characterId) ? $characterId : '')->value('ffxi_face_types.image_path');

        return asset($path ?: 'assets/default_avatar_image.png');
    }

    public function user(User $user): string
    {
        return ! empty($user->avatar_attachment_id) ? route('images.show', [$user->avatar_attachment_id, 'thumbnail']) : asset('assets/default_avatar_image.png');
    }

    public function face(object $face): string
    {
        return asset((string) (data_get($face, 'image_path') ?: 'assets/default_avatar_image.png'));
    }
}
