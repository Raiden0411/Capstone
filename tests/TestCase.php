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
        parent::setUp();

        $this->seedStandardRoles();
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