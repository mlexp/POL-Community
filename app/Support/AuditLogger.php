<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditLogger
{
    /** @param array<string, mixed>|null $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(Request $request, string $action, Model|string|null $subject = null, ?array $before = null, ?array $after = null): void
    {
        DB::table('audit_logs')->insert([
            'id' => (string) Str::ulid(),
            'actor_user_id' => $request->user()?->getAuthIdentifier(),
            'action' => $action,
            'subject_type' => $subject instanceof Model ? $subject::class : ($subject === null ? null : $subject),
            'subject_id' => $subject instanceof Model ? (string) $subject->getKey() : null,
            'before_json' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'after_json' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'ip_hash' => $request->ip() === null ? null : hash_hmac('sha256', $request->ip(), (string) config('app.key')),
            'created_at' => now(),
        ]);
    }
}
