<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'json',
    ];

    public static function getValue(string $key, $default = null)
    {
        return Cache::remember(
            "site_setting_{$key}",
            now()->addHours(6),
            fn () => static::query()->where('key', $key)->value('value') ?? $default
        );
    }

    public static function setValue(string $key, $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        Cache::forget("site_setting_{$key}");
    }

    /**
     * Forget the cached value for a key, without deleting the DB row.
     */
    public static function forget(string $key): void
    {
        Cache::forget("site_setting_{$key}");
    }

    /**
     * Delete the DB row and its cache entry.
     */
    public static function deleteKey(string $key): void
    {
        static::query()->where('key', $key)->delete();
        Cache::forget("site_setting_{$key}");
    }
}