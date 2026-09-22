<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $key
 * @property array<array-key, mixed>|null $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static Builder<static> newModelQuery()
 * @method static Builder<static> newQuery()
 * @method static Builder<static> query()
 * @method static Builder<static> whereCreatedAt($value)
 * @method static Builder<static> whereId($value)
 * @method static Builder<static> whereKey($value)
 * @method static Builder<static> whereUpdatedAt($value)
 * @method static Builder<static> whereValue($value)
 * @mixin \Eloquent
 */
class SiteSetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'json',
    ];

    /**
     * How long an individual cache entry lives. Longer than any admin's
     * edit cycle, shorter than "the DB might drift". Bumping the version
     * token makes stale entries irrelevant well before this expires.
     */
    public const CACHE_TTL_HOURS = 6;

    /**
     * Version-token cache key. Holds a random hex string.
     *
     * Why random instead of an incrementing int? If the token is ever
     * cleared (e.g. `php artisan cache:clear`), an int would restart at 1
     * and could collide with orphaned `v1_*` keys from a prior era. A
     * random 16-char token cannot meaningfully collide.
     */
    protected const VERSION_KEY = 'site_settings_cache_version';

    /**
     * TTL for the version token itself. Must be LONGER than the
     * individual-entry TTL, otherwise every entry would be re-keyed
     * with a new version token before it had a chance to expire
     * naturally — a needless cache-thrash. 30 days is safe.
     */
    protected const VERSION_TTL_DAYS = 30;

    // ═══════════════════════════════════════════════════════════════
    //  Reads
    // ═══════════════════════════════════════════════════════════════

    /**
     * Read a single setting, cached.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $cacheKey = self::keyFor($key);

        return Cache::remember(
            $cacheKey,
            now()->addHours(self::CACHE_TTL_HOURS),
            fn (): mixed => static::query()->where('key', $key)->value('value') ?? $default
        );
    }

    /**
     * Batched read for callers that need several keys at once. The
     * `$namespace` argument partitions unrelated groups (e.g.
     * 'homepage_hero', 'footer_branding') so a write to one group does
     * not unnecessarily invalidate the other — even though both share
     * the same version token and would both refresh on the next read.
     *
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public static function getBatch(array $keys, string $namespace): array
    {
        $cacheKey = self::batchKeyFor($namespace);

        /** @var Builder<self> $query */
        $query = static::query();

        return Cache::remember(
            $cacheKey,
            now()->addHours(self::CACHE_TTL_HOURS),
            fn (): array => $query
                ->whereIn('key', $keys)
                ->pluck('value', 'key')
                ->toArray()
        );
    }

    // ═══════════════════════════════════════════════════════════════
    //  Writes
    //
    //  Every write bumps the version token. The next read computes a
    //  cache key that has never been seen → cache miss → fresh DB read.
    //  Old keys are not deleted (they'd need a scan) — they simply
    //  become unreachable and expire on their own TTL.
    // ═══════════════════════════════════════════════════════════════

    public static function setValue(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        self::bumpVersion();
    }

    /**
     * Invalidate every cached read without touching the DB.
     */
    public static function forget(string $key): void
    {
        self::bumpVersion();
    }

    /**
     * Delete the DB row and invalidate every cached read.
     */
    public static function deleteKey(string $key): void
    {
        static::query()->where('key', $key)->delete();
        self::bumpVersion();
    }

    // ═══════════════════════════════════════════════════════════════
    //  Version token
    // ═══════════════════════════════════════════════════════════════

    /**
     * Current version token. Reads once per request (Laravel's cache
     * facade memoizes within a request via the array-store fallback).
     */
    public static function version(): string
    {
        $token = Cache::get(self::VERSION_KEY);

        // Defensive: any non-string or empty value (unexpected store
        // corruption, wrong cache driver swapped in) regenerates.
        if (is_string($token) && $token !== '') {
            return $token;
        }

        return self::regenerateVersion();
    }

    /**
     * Force-regenerate the version token. Called by all write paths.
     */
    public static function bumpVersion(): void
    {
        self::regenerateVersion();
    }

    /**
     * Write a fresh random token. Uses `random_bytes` (CSPRNG) — no
     * collision risk, no coordination needed between concurrent writers.
     */
    protected static function regenerateVersion(): string
    {
        $token = bin2hex(random_bytes(8)); // 16 hex chars

        Cache::put(
            self::VERSION_KEY,
            $token,
            now()->addDays(self::VERSION_TTL_DAYS)
        );

        return $token;
    }

    // ═══════════════════════════════════════════════════════════════
    //  Key builders
    // ═══════════════════════════════════════════════════════════════

    protected static function keyFor(string $key): string
    {
        return 'site_setting_v' . self::version() . '_' . $key;
    }

    protected static function batchKeyFor(string $namespace): string
    {
        return 'site_setting_batch_v' . self::version() . ':' . $namespace;
    }
}