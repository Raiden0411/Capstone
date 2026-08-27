<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Use JSON casting so string values remain strings.
     * Do NOT use `array` cast.
     */
    protected $casts = [
        'value' => 'json',
    ];

    public static function getValue(string $key, $default = null)
    {
        $setting = static::query()
            ->where('key', $key)
            ->first();

        return $setting ? $setting->value : $default;
    }

    public static function setValue(string $key, $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    public static function forget(string $key): void
    {
        static::query()->where('key', $key)->delete();
    }
}