<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $diary_id
 * @property string|null $user_id
 */
#[Fillable(['diary_id', 'user_id', 'parent_id', 'author_name_snapshot', 'body_html', 'status'])]
class DiaryComment extends Model
{
    use HasUlidPrimaryKey, SoftDeletes;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
