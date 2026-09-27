<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    /**
     * Pre-seed the platform's standard roles before every database test.
     *
     * WHY: `RefreshDatabase` resets the schema between tests — the
     * `roles` table is empty at the start of every method. Several code
     * paths assume the standard roles exist (observers that dispatch to
     * super-admins, policies that gate on `admin`, seeders). Without
     * pre-seeding, those lookups throw RoleDoesNotExist and the test
     * fails on setup rather than on the behaviour under test.
     *
     * `findOrCreate` is idempotent — safe if a test also creates them.
     *
     * Only runs for tests that use a database concern; unit tests that
     * never touch the DB are skipped.
     */
    protected function setUp(): void
    {
        $this->purgePoisonedCaches();

        parent::setUp();

        $this->seedStandardRoles();
    }

    /**
     * Purge caches that poison the test suite when stale.
     *
     * TWO KNOWN POISONS:
     *
     * 1. bootstrap/cache/config.php
     *    If `php artisan config:cache` has ever been run, the cached
     *    file wins over phpunit.xml's APP_ENV=testing override.
     *    Laravel thinks it is in production. RefreshDatabase then tries
     *    to run migrate:fresh, which prompts "Are you sure?" — no test
     *    can answer, so every DB test fails with:
     *        BadMethodCallException: Received Mockery_1_Illuminate_
     *        Console_OutputStyle::askQuestion(), but no expectations
     *        were specified
     *
     * 2. storage/framework/views/livewire/classes/*.php
     *    Livewire 4 compiles each SFC into a cached PHP class keyed by
     *    file hash. If the underlying .blade.php was edited but the
     *    cache was not purged, tests run against the OLD compiled class.
     *    Symptom: 13 Livewire tests fail with "null does not match
     *    expected" or "Component did not perform a redirect" — the
     *    stale class has the pre-edit behaviour, not the fresh source.
     *
     * MUST run BEFORE parent::setUp() — the framework boots, reads
     * config, and resolves compiled paths during that call. Purging
     * after is too late.
     */
    protected function purgePoisonedCaches(): void
    {
        $base = __DIR__ . '/..';

        // ─── Poison 1: config cache ───
        $configCache = $base . '/bootstrap/cache/config.php';
        if (file_exists($configCache)) {
            @unlink($configCache);
        }

        // ─── Poison 2: compiled Livewire SFCs ───
        $livewireDirs = [
            $base . '/storage/framework/views/livewire/classes',
            $base . '/storage/framework/views/livewire/views',
        ];

        foreach ($livewireDirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            foreach (glob($dir . '/*.php') as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * The four roles the platform uses across all actors:
     *   - super-admin  → platform staff
     *   - admin        → business owner
     *   - employee     → tenant employee
     *   - tourist      → public end user
     */
    protected function seedStandardRoles(): void
    {
        $traits = class_uses_recursive(static::class);

        $usesDatabase = array_key_exists(RefreshDatabase::class, $traits)
            || array_key_exists(DatabaseMigrations::class, $traits)
            || array_key_exists(DatabaseTransactions::class, $traits);

        if (! $usesDatabase) {
            return;
        }

        // Defensive — if the migrations haven't run for some reason, skip
        // rather than crash. This surfaces as the test failing with a
        // more meaningful error (missing table) instead of a stack trace
        // inside setUp().
        if (! Schema::hasTable('roles')) {
            return;
        }

        foreach (['super-admin', 'admin', 'employee', 'tourist'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        // Spatie caches the roles table in-memory per process. When
        // RefreshDatabase rolls back, the cache holds stale entries from
        // the previous test. Clearing it forces a fresh read against the
        // re-seeded table on this test's first lookup.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}