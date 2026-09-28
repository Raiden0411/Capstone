<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Durable (user → business) membership pivot.
     *
     * WHY THIS EXISTS:
     *
     *   Before this migration the ONLY record of "which business does
     *   this user belong to" was `users.tenant_id` — a single pointer.
     *   That is fundamentally incapable of expressing "one user owns
     *   two businesses" or "one user is an employee of A and an owner
     *   of B".
     *
     *   `business_memberships` is that durable record. `users.tenant_id`
     *   becomes the ACTIVE pointer (which business am I currently
     *   operating in), and the pivot is the source of truth for
     *   membership.
     *
     * BACKFILL:
     *
     *   Every existing user with a tenant_id gets one pivot row.
     *   Role is derived from their Spatie role:
     *     has 'admin'  → role='owner'
     *     otherwise    → role='employee'
     *
     *   All backfilled rows are is_active = true, because pre-1a a user
     *   had exactly one business by construction. Once Phase 3 ships
     *   the switcher, only the row matching `users.tenant_id` remains
     *   active at any moment.
     */
    public function up(): void
    {
        Schema::create('business_memberships', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // 'owner' | 'admin' | 'employee'
            // Owners created the business (or were approved via KYB as
            // the applicant). Admins were added by an owner. Employees
            // are scoped to their single business and never see the
            // switcher (Answer B).
            $table->string('role', 20)->default('owner');

            // The active-context flag. At most one row per user may be
            // true; enforced by the application in Phase 3.
            $table->boolean('is_active')->default(false);

            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tenant_id'], 'bm_user_tenant_unique');
            $table->index(['tenant_id', 'role'],        'bm_tenant_role_idx');
            $table->index(['user_id', 'is_active'],     'bm_user_active_idx');
        });

        // ── Backfill ────────────────────────────────────────────
        // Chunked so a large users table doesn't materialize in one
        // pass. updateOrInsert keeps the migration re-runnable against
        // a DB restored from a mid-migration snapshot.
        DB::table('users')
            ->whereNotNull('tenant_id')
            ->orderBy('id')
            ->chunk(200, function ($users): void {
                $now = now();

                foreach ($users as $user) {
                    $isAdmin = DB::table('model_has_roles')
                        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                        ->where('model_has_roles.model_id', $user->id)
                        ->where('model_has_roles.model_type', User::class)
                        ->where('roles.name', 'admin')
                        ->exists();

                    DB::table('business_memberships')->updateOrInsert(
                        ['user_id' => $user->id, 'tenant_id' => $user->tenant_id],
                        [
                            'role'       => $isAdmin ? 'owner' : 'employee',
                            'is_active'  => true,
                            'joined_at'  => $user->created_at ?? $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_memberships');
    }
};