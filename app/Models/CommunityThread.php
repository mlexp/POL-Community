<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property int $category_id
 * @property string $created_by_user_id
 * @property string $title
 * @property string $visibility
 * @property string $status
 * @property string $is_pinned
 * @property string $last_posted_at
 * @property int $post_count
 */
class CommunityThread extends Model
{
    use HasUlidPrimaryKey, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_pinned' => 'boolean', 'last_posted_at' => 'datetime'];
    }
}
