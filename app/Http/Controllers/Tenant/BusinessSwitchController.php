<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\BusinessSwitcherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Handles the header-dropdown form POST for switching businesses.
 *
 * The Livewire SFC uses the same service directly — this controller
 * exists only so the header dropdown can be a plain HTML form (no
 * Livewire runtime on the header, which loads on every tenant page).
 *
 * ── Authorization layering ───────────────────────────────────────
 *
 *   1. Route group: `auth` + `IsTenantAdmin` + `BlockIfDeletionPending`
 *   2. Route group: `role:admin|super-admin`
 *   3. THIS CONTROLLER: reject super-admins with 403. They bypass
 *      IsTenantAdmin by design (so they can reach other tenant pages
 *      from the platform dashboard) — but switching is a tenant-scoped
 *      action a platform operator must never perform.
 *   4. SERVICE: re-verifies pivot membership via
 *      `BusinessSwitcherService::switchTo()`. Defense in depth — if a
 *      future caller (Livewire SFC, CLI command, queue job) skips the
 *      controller, the service still rejects.
 */
class BusinessSwitchController extends Controller
{
    public function __construct(
        protected BusinessSwitcherService $switcher,
    ) {}

    public function __invoke(Request $request, Tenant $tenant): RedirectResponse
    {
        $user = Auth::user();
        abort_if(! $user, 403);

        // Super-admins operate at platform level (team_id = 0) and have
        // no tenant context to switch to. Reject with a hard 403 — this
        // is an authorization boundary, not a recoverable user error.
        abort_if(
            $user->hasRole('super-admin'),
            403,
            'Super-admins do not switch between businesses.'
        );

        try {
            $this->switcher->switchTo($user, $tenant);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Business switch failed', [
                'user_id'   => $user->id,
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);

            return back()->with('error', 'Could not switch businesses. Please try again.');
        }

        // Full redirect — NOT wire:navigate. The middleware that
        // establishes Spatie team context only runs on a fresh request.
        return redirect()
            ->route('tenant.dashboard')
            ->with('message', "Switched to {$tenant->name}.");
    }
}