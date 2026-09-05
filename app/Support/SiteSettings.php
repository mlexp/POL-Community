<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class SiteSettings
{
    public function get(string $key, mixed $default = null): mixed
    {
        $value = DB::table('site_settings')->where('key', $key)->value('value');

        if ($value === null) {
            return $default;
        }

        return is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : $value;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }
}
