<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class UpdateTimestamp
{
    public function preserve(Request $request): bool
    {
        return $request->user()?->role === 'admin' && $request->boolean('preserve_updated_at');
    }

    /** @param array<string, mixed> $attributes */
    public function update(Request $request, Model $model, array $attributes): void
    {
        $model->timestamps = ! $this->preserve($request);
        try {
            $model->update($attributes);
        } finally {
            $model->timestamps = true;
        }
    }

    public function value(Request $request, mixed $current): mixed
    {
        return $this->preserve($request) ? $current : now();
    }
}
