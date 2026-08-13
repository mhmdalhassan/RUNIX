<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-editable system settings (admin/settings) — a plain key/value
 * store rather than fixed columns, so the Settings page can grow to new
 * fields later without another migration. Currently just `whatsapp_number`;
 * get()/set() are the only two operations anything needs.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public static function get(string $key, ?string $default = null): ?string
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
