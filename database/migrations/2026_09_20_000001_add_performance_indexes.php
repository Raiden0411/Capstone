<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes added by this migration, keyed by table → [index_name => columns].
     *
     * Kept in a property (not hard-coded inline) so up() and down() stay
     * symmetric — a drop is guaranteed to target exactly what was added.
     *
     * Naming convention follows the existing codebase style: short
     * {table_abbrev}_{cols_abbrev}_idx, all under MySQL's 64-char limit.
     */
    private array $indexes = [
        'events' => [
            'events_active_dates_idx' => ['is_active', 'end_date', 'start_date'],
        ],
        'payments' => [
            'payments_paymongo_session_idx' => ['paymongo_session_id'],
            'payments_reference_number_idx' => ['reference_number'],
            'payments_booking_status_idx'   => ['booking_id', 'payment_status'],
        ],
        'bookings' => [
            'bookings_tenant_status_created_idx' => ['tenant_id', 'status', 'created_at'],
            'bookings_user_status_updated_idx'   => ['user_id', 'status', 'updated_at'],
        ],
        'business_applications' => [
            'ba_status_submitted_idx' => ['status', 'submitted_at'],
        ],
        'properties' => [
            'properties_tenant_active_idx' => ['tenant_id', 'is_active'],
        ],
        'services' => [
            'services_tenant_active_idx' => ['tenant_id', 'is_active'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $definitions) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($definitions as $indexName => $columns) {
                if ($this->indexExists($table, $indexName)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName): void {
                    $blueprint->index($columns, $indexName);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $definitions) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($definitions as $indexName => $columns) {
                if (! $this->indexExists($table, $indexName)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
                    $blueprint->dropIndex($indexName);
                });
            }
        }
    }

    /**
     * Driver-aware index existence check.
     *
     * We can't use Schema::hasIndex() because it isn't available in all
     * Laravel versions we support; we hit information_schema directly on
     * MySQL and PRAGMA on SQLite. Anything else (Postgres, SQL Server)
     * falls back to `false` — the migration will attempt the CREATE and
     * let the DB surface a duplicate-index error if one exists, which is
     * no worse than the pre-guard behaviour and keeps the code honest
     * about only supporting the two drivers this project targets.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver     = $connection->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $database = $connection->getDatabaseName();

            $row = $connection->selectOne(
                'SELECT COUNT(*) AS c
                   FROM information_schema.statistics
                  WHERE table_schema = ?
                    AND table_name   = ?
                    AND index_name   = ?',
                [$database, $table, $indexName]
            );

            return ((int) ($row->c ?? 0)) > 0;
        }

        if ($driver === 'sqlite') {
            /** @var array<int, object{name: string}> $rows */
            $rows = $connection->select("PRAGMA index_list('{$table}')");

            foreach ($rows as $row) {
                if (($row->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }
};