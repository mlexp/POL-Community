<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $uploaded_by_user_id
 * @property string $disk
 * @property string $path
 * @property string $path_hash
 * @property string $original_name
 * @property string $mime_type
 * @property string $extension
 * @property int $size_bytes
 * @property int $width
 * @property int $height
 * @property string $sha256
 * @property string $visibility
 * @property string $status
 */
class Attachment extends Model
{
    use HasUlidPrimaryKey, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }
}
