<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string|null $character_id
 * @property string $title
 * @property string $body_html
 * @property string $visibility
 * @property string $status
 * @property Carbon|null $published_at
 * @property bool $comments_enabled
 */
#[Fillable(['user_id', 'character_id', 'title', 'body_json', 'body_html', 'excerpt', 'visibility', 'status', 'published_at', 'comments_enabled'])]
class Diary extends Model
{
    use HasUlidPrimaryKey, SoftDeletes;

    protected function casts(): array
    {
        return ['body_json' => 'array', 'published_at' => 'datetime', 'comments_enabled' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Character, $this> */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
