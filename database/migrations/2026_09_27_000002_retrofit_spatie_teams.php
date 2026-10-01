<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retrofit the existing permission tables with team_id columns.
     *
     * WHEN THIS RUNS:
     *   - Fresh install (migrate:fresh with 'teams' => true already in
     *     config): the original 0001_create_permission_tables migration
     *     already created team_id. This migration early-returns.
     *   - Live DB (migrated when 'teams' => false): team_id is missing.
     *     This migration adds, backfills, and rebuilds PKs.
     *
     * SENTINEL:
     *   team_id = 0 means "platform level" — super-admins live here.
     *   The roles table keeps team_id NULLABLE so global roles
     *   (super-admin) can exist once and be referenceable from any team
     *   context via the "role.team_id IS NULL OR role.team_id = ?"
     *   fallback in Spatie's roles() relation.
     *
     *   The pivot tables keep team_id NOT NULL because it is part of the
     *   primary key — MySQL rejects NULL columns in a PK.
     */
    public function up(): void
    {
        // ── Idempotence guard ────────────────────────────────
        // On a fresh install the original permission migration already
        // created team_id. Nothing to do.
        if (Schema::hasColumn('roles', 'team_id')) {
            return;
        }

        $teamKey = config('permission.column_names.team_foreign_key', 'team_id');

        $this->retrofitRoles($teamKey);
        $this->retrofitPivot('model_has_roles', $teamKey, 'role_id');
        $this->retrofitPivot('model_has_permissions', $teamKey, 'permission_id');

        // Spatie caches the role map. Forget it so the first post-
        // migration role lookup re-reads from the (now team-scoped) DB.
        app('cache')
            ->store(config('permission.cache.store') !== 'default'
                ? config('permission.cache.store')
                : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        if (! Schema::hasColumn('roles', 'team_id')) {
            return;
        }

        $teamKey = config('permission.column_names.team_foreign_key', 'team_id');

        // Restore original primary keys first, then drop the column.
        $this->restoreOriginalPivotPk('model_has_permissions', 'permission_id');
        $this->restoreOriginalPivotPk('model_has_roles', 'role_id');

        Schema::table('model_has_permissions', function (Blueprint $t) use ($teamKey): void {
            $t->dropIndex('model_has_permissions_team_foreign_key_index');
            $t->dropColumn($teamKey);
        });

        Schema::table('model_has_roles', function (Blueprint $t) use ($teamKey): void {
            $t->dropIndex('model_has_roles_team_foreign_key_index');
            $t->dropColumn($teamKey);
        });

        Schema::table('roles', function (Blueprint $t) use ($teamKey): void {
            $t->dropUnique('roles_team_id_name_guard_name_unique');
            $t->dropIndex('roles_team_foreign_key_index');
            $t->dropColumn($teamKey);
            $t->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
        });
    }

    // ═════════════════════════════════════════════════════════
    //  Roles table
    // ═════════════════════════════════════════════════════════

    private function retrofitRoles(string $teamKey): void
    {
        Schema::table('roles', function (Blueprint $table) use ($teamKey): void {
            $table->unsignedBigInteger($teamKey)
                ->nullable()
                ->after('id');

            $table->index($teamKey, 'roles_team_foreign_key_index');
        });

        // Drop the original unique index. Guard because on a partial
        // re-run it may already be gone.
        $this->dropIndexIfExists('roles', 'roles_name_guard_name_unique');

        Schema::table('roles', function (Blueprint $table) use ($teamKey): void {
            $table->unique(
                [$teamKey, 'name', 'guard_name'],
                'roles_team_id_name_guard_name_unique'
            );
        });
    }

    // ═════════════════════════════════════════════════════════
    //  Pivot tables (model_has_roles, model_has_permissions)
    // ═════════════════════════════════════════════════════════

    private function retrofitPivot(string $table, string $teamKey, string $pivotKey): void
    {
        // ── 1. Add nullable column ──
        Schema::table($table, function (Blueprint $t) use ($teamKey, $pivotKey): void {
            $t->unsignedBigInteger($teamKey)
                ->nullable()
                ->after($pivotKey);
        });

        // ── 2. Backfill: tenant users → their tenant_id ──
        DB::statement(
            "UPDATE {$table} AS p
               INNER JOIN users AS u
                  ON u.id = p.model_id
                 AND p.model_type = ?
                SET p.{$teamKey} = u.tenant_id
              WHERE u.tenant_id IS NOT NULL
                AND p.{$teamKey} IS NULL",
            [User::class]
        );

        // ── 3. Backfill: everyone else → sentinel 0 (platform) ──
        DB::statement(
            "UPDATE {$table}
                SET {$teamKey} = 0
              WHERE {$teamKey} IS NULL"
        );

        // ── 4. Make the column NOT NULL (it's part of the PK) ──
        DB::statement(
            "ALTER TABLE {$table}
             MODIFY COLUMN {$teamKey} BIGINT UNSIGNED NOT NULL"
        );

        // ── 5. Add index for the Spatie lookup path ──
        Schema::table($table, function (Blueprint $t) use ($teamKey, $table): void {
            $t->index($teamKey, "{$table}_team_foreign_key_index");
        });

        // ── 6. Drop the old primary key, rebuild with team_id first ──
        // MySQL allows DROP + ADD in one statement only when the FK
        // columns (role_id / permission_id) are preserved — which they
        // are, since they stay in the new PK.
        DB::statement("ALTER TABLE {$table} DROP PRIMARY KEY");

        Schema::table($table, function (Blueprint $t) use ($teamKey, $pivotKey, $table): void {
            $t->primary(
                [$teamKey, $pivotKey, 'model_id', 'model_type'],
                "{$table}_role_model_type_primary"
            );
        });
    }

    // ═════════════════════════════════════════════════════════
    //  Helpers
    // ═════════════════════════════════════════════════════════

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $connection = Schema::getConnection();
        $driver     = $connection->getDriverName();

        $exists = false;

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $row = $connection->selectOne(
                'SELECT COUNT(*) AS c
                   FROM information_schema.statistics
                  WHERE table_schema = ?
                    AND table_name   = ?
                    AND index_name   = ?',
                [$connection->getDatabaseName(), $table, $indexName]
            );
            $exists = ((int) ($row->c ?? 0)) > 0;
        } elseif ($driver === 'sqlite') {
            $rows = $connection->select("PRAGMA index_list('{$table}')");
            foreach ($rows as $row) {
                if (($row->name ?? null) === $indexName) {
                    $exists = true;
                    break;
                }
            }
        }

        if ($exists) {
            Schema::table($table, fn (Blueprint $b) => $b->dropIndex($indexName));
        }
    }

    private function restoreOriginalPivotPk(string $table, string $pivotKey): void
    {
        try {
            DB::statement("ALTER TABLE {$table} DROP PRIMARY KEY");
        } catch (\Throwable) {
            // No PK to drop; continue.
        }

        Schema::table($table, function (Blueprint $t) use ($pivotKey, $table): void {
            $t->primary(
                [$pivotKey, 'model_id', 'model_type'],
                "{$table}_role_model_type_primary"
            );
        });
    }
};