<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $thread_id
 * @property string $user_id
 * @property string $parent_id
 * @property string $author_name_snapshot
 * @property string $body_html
 * @property string $status
 */
class CommunityPost extends Model
{
    use HasUlidPrimaryKey, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }
}
