<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Switches a user's active business pointer.
 *
 * ── Invariants ─────────────────────────────────────────────────
 *
 *   1. Only owners/admins of the target business may switch to it.
 *      Verified against `business_memberships` (the durable pivot),
 *      not against Spatie's role relation — the pivot is the
 *      authoritative record.
 *
 *   2. `users.tenant_id` is the active pointer. It is the SINGLE
 *      column every SFC in the app reads to decide its current
 *      tenant. This service is the only writer.
 *
 *   3. `business_memberships.is_active` mirrors `users.tenant_id`
 *      for UI convenience. Both rows update inside the same
 *      transaction, so they never disagree.
 *
 *   4. Super-admins do NOT switch. They operate at the platform
 *      level (team_id = 0) and have no tenant context to point at.
 */
class BusinessSwitcherService
{
    /**
     * @throws RuntimeException when the user is not permitted to
     *                          switch to the target business.
     */
    public function switchTo(User $user, Tenant $target): void
    {
        // ── Platform-level accounts have no tenant context ──
        if ($user->hasRole('super-admin')) {
            throw new RuntimeException('Super-admins do not switch between businesses.');
        }

        // ── Ownership check via the durable pivot ────────────
        if (! $user->ownsBusiness($target->id)) {
            throw new RuntimeException('You do not have access to this business.');
        }

        // ── No-op when already on the target ─────────────────
        if ($user->tenant_id === $target->id) {
            return;
        }

        DB::transaction(function () use ($user, $target): void {
            // Lock the user's full pivot set so a concurrent switch
            // from another tab cannot race the is_active flips.
            $user->businessMemberships()
                ->lockForUpdate()
                ->get();

            $user->businessMemberships()
                ->where('tenant_id', '!=', $target->id)
                ->update(['is_active' => false]);

            $user->businessMemberships()
                ->where('tenant_id', $target->id)
                ->update(['is_active' => true]);

            $user->update(['tenant_id' => $target->id]);
        });
    }
}