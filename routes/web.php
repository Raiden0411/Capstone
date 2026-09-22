<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Public\BookingPaymentController;
use App\Http\Controllers\Public\BookingReceiptController;
use App\Http\Controllers\Public\MapSatelliteStyleController;
use App\Http\Controllers\Public\RegisterBusinessController;
use App\Http\Controllers\Public\RouteController;
use App\Http\Controllers\Tenant\BookingController as TenantBookingController;
use App\Http\Controllers\Tenant\PaymentCallbackController;
use App\Http\Controllers\UserModeController;
use App\Http\Middleware\BlockIfDeletionPending;
use App\Http\Middleware\IsSuperAdmin;
use App\Http\Middleware\IsTenantAdmin;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────────
// PUBLIC — no auth. Every query on a BelongsToTenant model
// MUST call ->withoutGlobalScope(TenantScope::class), or guests
// and signed-in users both see wrong data.
// ─────────────────────────────────────────────────────────────

Route::livewire('/', 'public::pages.index')->name('home');
Route::livewire('/about', 'public::pages.about')->name('about');
Route::livewire('/privacy-policy', 'public::pages.privacy-policy')->name('privacy.policy');
Route::livewire('/explore/map', 'public::pages.explore-map')->name('explore.map');

// Server-side OSRM proxy — browser never contacts OSRM directly.
Route::get('/api/route', RouteController::class)
    ->middleware('throttle:60,1')
    ->name('api.route');

// MapLibre satellite style JSON. Extracted from inline closure for route:cache.
Route::get('/map/satellite-style', MapSatelliteStyleController::class)
    ->name('map.satellite.style');

// Health check for uptime monitors. Extracted from inline closure for route:cache.
Route::get('/health', HealthController::class)->name('health');

// Public tenant pages — all queries need withoutGlobalScope.
Route::livewire('/business/{slug}', 'public::pages.tenant-show')->name('tenant.show');
Route::livewire('/business/{slug}/offerings', 'public::pages.business-offerings')->name('business.offerings');
Route::livewire('/tourist-spots', 'public::pages.tourist-spots')->name('tourist-spots.index');

// Events. /events/{event} is a legacy redirect shim kept for old bookmarks.
Route::livewire('/events', 'public::pages.events')->name('events');
Route::redirect('/events/{event}', '/events')
    ->whereNumber('event')
    ->name('event.show');

// ─────────────────────────────────────────────────────────────
// AUTH — route-level rate limiters protect page refreshes.
// The real credential brute-force guards live inside each SFC.
// ─────────────────────────────────────────────────────────────

Route::livewire('/login', 'public::auth.login-form')
    ->middleware('throttle:auth.login.ip')
    ->name('login');

Route::livewire('/register', 'public::auth.register')
    ->middleware('throttle:auth.register.ip')
    ->name('register');

Route::livewire('/forgot-password', 'public::auth.forgot-password')
    ->middleware('throttle:auth.password.request.ip')
    ->name('password.request');

// Email clients preview the link, so no route-level throttle here —
// the token is one-time-use and the broker validates it.
Route::livewire('/reset-password/{token}', 'public::auth.reset-password')
    ->where('token', '[A-Za-z0-9]+')
    ->name('password.reset');

// Extracted from inline closure for route:cache.
Route::post('/logout', LogoutController::class)->name('logout');

// ─────────────────────────────────────────────────────────────
// AUTHENTICATED — TOURIST / APPLICANT
// Requires auth, NOT a tenant. Every mutation needs its own
// per-action auth guard — route middleware doesn't run on Livewire updates.
// ─────────────────────────────────────────────────────────────

Route::middleware(['auth'])->group(function () {

    Route::livewire('/profile', 'public::pages.profile')->name('profile');

    // Reachable by any authenticated user; the SFC picks the flow by role.
    Route::livewire('/profile/delete', 'public::pages.delete-account')
        ->name('account.delete');

    // Dual-role accounts (business owners) only — controller re-checks canSwitchModes().
    Route::post('/switch-mode', [UserModeController::class, 'switch'])
        ->name('mode.switch');

    // ── Bookings ────────────────────────────────────────────
    Route::livewire('/booking/create/{publicproperty}', 'public::pages.create-booking')
        ->name('booking.create');

    Route::livewire('/my-bookings', 'public::pages.my-bookings')->name('my-bookings');

    Route::get('/booking/receipt/{booking}', [BookingReceiptController::class, 'show'])
        ->whereNumber('booking')
        ->name('booking.receipt');

    Route::livewire('/booking/payment/processing/{bookingId}', 'public::pages.payment-processing')
        ->whereNumber('bookingId')
        ->name('booking.payment.processing');

    Route::get('/booking/payment/success/{booking}', [BookingPaymentController::class, 'success'])
        ->whereNumber('booking')
        ->name('booking.payment.success');

    Route::get('/booking/payment/cancel/{booking}', [BookingPaymentController::class, 'cancel'])
        ->whereNumber('booking')
        ->name('booking.payment.cancel');

    // ── Business registration (KYB) ─────────────────────────
    // Order matters: /register-business BEFORE /register-business/{application}.
    // POST/PUT/submit ownership is enforced in RegisterBusinessController.
    Route::livewire('/register-business', 'public::pages.register-business')
        ->name('register_business');

    Route::post('/register-business', [RegisterBusinessController::class, 'store'])
        ->name('register_business.start');

    Route::livewire('/register-business/{application}', 'public::pages.edit-business-application')
        ->whereNumber('application')
        ->name('register_business.edit');

    Route::put('/register-business/{application}', [RegisterBusinessController::class, 'update'])
        ->whereNumber('application')
        ->name('register_business.update');

    Route::post('/register-business/{application}/documents', [RegisterBusinessController::class, 'uploadDocument'])
        ->whereNumber('application')
        ->name('register_business.documents.upload');

    Route::post('/register-business/{application}/submit', [RegisterBusinessController::class, 'submit'])
        ->whereNumber('application')
        ->name('register_business.submit');
});

// ─────────────────────────────────────────────────────────────
// SUPER ADMIN — prefix /platform, name superadmin.*
// Every SFC also re-checks hasRole('super-admin') in mount/hydrate.
// /create and /export are registered BEFORE sibling /{id} wildcards.
// ─────────────────────────────────────────────────────────────

Route::prefix('platform')
    ->name('superadmin.')
    ->middleware([Authenticate::class, IsSuperAdmin::class])
    ->group(function () {

        // ── Dashboard / Analytics / Health / Profile ─────────
        Route::livewire('/dashboard', 'superadmin::pages.dashboard.dashboard-page')
            ->name('dashboard');

        Route::livewire('/analytics', 'superadmin::pages.analytics.platform-analytics')
            ->name('analytics');

        // Source data from slow_query_aggregates; populated by slow-queries:harvest.
        Route::livewire('/health/queries', 'superadmin::pages.health.slow-query-dashboard')
            ->name('health.queries');

        Route::livewire('/profile', 'superadmin::pages.profile.edit-profile')
            ->name('profile');

        // ── Users ─────────────────────────────────────────────
        // Export is a plain GET — Livewire can't stream responses.
        Route::livewire('/users', 'superadmin::pages.user.view-user')
            ->name('users.index');

        Route::livewire('/users/create', 'superadmin::pages.user.create-user')
            ->name('users.create');

        Route::get('/users/export', \App\Http\Controllers\Superadmin\UserExportController::class)
            ->name('users.export');

        Route::livewire('/users/{user}/edit', 'superadmin::pages.user.edit-user')
            ->whereNumber('user')
            ->name('users.edit');

        // ── Tenants ───────────────────────────────────────────
        Route::livewire('/tenants', 'superadmin::pages.tenant.view-tenant')
            ->name('tenants.index');

        Route::livewire('/tenants/create', 'superadmin::pages.tenant.create-tenant')
            ->name('tenants.create');

        Route::get('/tenants/export', \App\Http\Controllers\Superadmin\TenantExportController::class)
            ->name('tenants.export');

        Route::livewire('/tenants/{tenant}/preview', 'superadmin::pages.tenant.preview-tenant')
            ->whereNumber('tenant')
            ->name('tenants.preview');

        Route::livewire('/tenants/{tenant}/edit', 'superadmin::pages.tenant.edit-tenant')
            ->whereNumber('tenant')
            ->name('tenants.edit');

        // ── Roles ─────────────────────────────────────────────
        Route::livewire('/roles', 'superadmin::pages.role.view-role')
            ->name('roles.index');

        Route::livewire('/roles/create', 'superadmin::pages.role.create-role')
            ->name('roles.create');

        Route::get('/roles/export', \App\Http\Controllers\Superadmin\RoleExportController::class)
            ->name('roles.export');

        Route::livewire('/roles/{role}/edit', 'superadmin::pages.role.edit-role')
            ->whereNumber('role')
            ->name('roles.edit');

        // ── Tenant Types ──────────────────────────────────────
        Route::livewire('/tenant-types', 'superadmin::pages.tenant-type.view-type')
            ->name('tenant-types.index');

        Route::livewire('/tenant-types/create', 'superadmin::pages.tenant-type.create-type')
            ->name('tenant-types.create');

        Route::livewire('/tenant-types/{type}/edit', 'superadmin::pages.tenant-type.edit-type')
            ->whereNumber('type')
            ->name('tenant-types.edit');

        // ── Map Markers ───────────────────────────────────────
        Route::livewire('/map-markers', 'superadmin::pages.map-marker.manage-map-markers')
            ->name('map-markers.index');

        Route::livewire('/marker-categories', 'superadmin::pages.map-marker.manage-marker-categories')
            ->name('marker-categories.index');

        // ── Homepage / About editors ──────────────────────────
        Route::livewire('/homepage-editor', 'superadmin::pages.homepage.homepage-editor')
            ->name('homepage.editor');

        Route::livewire('/about-editor', 'superadmin::pages.homepage.about-editor')
            ->name('about.editor');

        // ── Events ────────────────────────────────────────────
        Route::livewire('/events', 'superadmin::pages.event.view-event')
            ->name('events.index');

        Route::livewire('/events/create', 'superadmin::pages.event.create-event')
            ->name('events.create');

        Route::livewire('/events/{event}/edit', 'superadmin::pages.event.edit-event')
            ->whereNumber('event')
            ->name('events.edit');

        // ── KYB review queue ──────────────────────────────────
        // Approve / reject / request-revision are actions inside the show SFC.
        Route::livewire('/business-applications', 'superadmin::pages.business-application.view-business-application')
            ->name('business-applications.index');

        Route::livewire('/business-applications/{application}', 'superadmin::pages.business-application.show-business-application')
            ->whereNumber('application')
            ->name('business-applications.show');

        // ── Account deletion review queue ─────────────────────
        Route::livewire('/deletion-requests', 'superadmin::pages.deletion-request.view-deletion-requests')
            ->name('deletion-requests.index');

        Route::livewire('/deletion-requests/{request}', 'superadmin::pages.deletion-request.show-deletion-request')
            ->whereNumber('request')
            ->name('deletion-requests.show');

        // Approve / reject use plain form POSTs — the show SFC renders both forms.
        Route::post('/deletion-requests/{deletionRequest}/approve', [
            \App\Http\Controllers\Superadmin\DeletionRequestController::class, 'approve'
        ])
            ->whereNumber('deletionRequest')
            ->name('deletion-requests.approve');

        Route::post('/deletion-requests/{deletionRequest}/reject', [
            \App\Http\Controllers\Superadmin\DeletionRequestController::class, 'reject'
        ])
            ->whereNumber('deletionRequest')
            ->name('deletion-requests.reject');
    });

// ─────────────────────────────────────────────────────────────
// TENANT ADMIN — prefix /admin, name tenant.*
// Middleware: Authenticate → IsTenantAdmin → BlockIfDeletionPending.
// IsTenantAdmin guarantees tenant_id AND (admin role OR ≥1 permission).
// Livewire updates bypass route middleware — every SFC mutation must
// call $this->requirePermission('...').
// The deletion-pending page is registered FIRST so the middleware's
// redirect target resolves.
// ─────────────────────────────────────────────────────────────

Route::prefix('admin')
    ->name('tenant.')
    ->middleware([
        Authenticate::class,
        IsTenantAdmin::class,
        BlockIfDeletionPending::class,
    ])
    ->group(function () {

        // Only reachable page while a deletion request is pending.
        Route::livewire('/account/deletion-pending', 'tenant::pages.settings.deletion-pending')
            ->name('account.deletion-pending');

        // ── Dashboards — reachable by any tenant user ─────────
        Route::livewire('/dashboard', 'tenant::pages.dashboard.dashboard-page')
            ->name('dashboard');

        Route::livewire('/employee-dashboard', 'tenant::pages.employee.dashboard')
            ->name('employee.dashboard');

        // Self-service profile — outside the admin-only role gate.
        Route::livewire('/account', 'tenant::pages.settings.account-profile')
            ->name('account.index');

        // ── Analytics ─────────────────────────────────────────
        Route::middleware('permission:view analytics')->group(function () {
            Route::livewire('/analytics', 'tenant::pages.analytics.dashboard')
                ->name('analytics.index');
        });

        // ── Bookings ──────────────────────────────────────────
        // /create BEFORE /{booking}; DELETE goes through a controller for streaming redirect.
        Route::middleware('permission:create bookings')->group(function () {
            Route::livewire('/bookings/create', 'tenant::pages.booking.create-booking')
                ->name('bookings.create');
        });

        Route::middleware('permission:view bookings')->group(function () {
            Route::livewire('/bookings', 'tenant::pages.booking.view-booking')
                ->name('bookings.index');

            Route::livewire('/bookings/history', 'tenant::pages.booking.history')
                ->name('bookings.history');

            Route::livewire('/bookings/{booking}', 'tenant::pages.booking.show-booking')
                ->whereNumber('booking')
                ->name('bookings.show');
        });

        Route::middleware('permission:edit bookings')->group(function () {
            Route::livewire('/bookings/{booking}/edit', 'tenant::pages.booking.edit-booking')
                ->whereNumber('booking')
                ->name('bookings.edit');
        });

        Route::middleware('permission:delete bookings')->group(function () {
            Route::delete('/bookings/{booking}', [TenantBookingController::class, 'destroy'])
                ->whereNumber('booking')
                ->name('bookings.destroy');
        });

        // ── Payments ──────────────────────────────────────────
        Route::middleware('permission:view payments')->group(function () {
            Route::livewire('/payments', 'tenant::pages.payment.view-payment')
                ->name('payments.index');
        });

        Route::middleware('permission:manage payments')->group(function () {
            Route::livewire('/payments/create/{booking}', 'tenant::pages.payment.create-payment')
                ->whereNumber('booking')
                ->name('payments.create');

            // Extracted from inline closures for route:cache.
            Route::get('/payments/success/{booking}', [PaymentCallbackController::class, 'success'])
                ->whereNumber('booking')
                ->name('payments.success');

            Route::get('/payments/cancel/{booking}', [PaymentCallbackController::class, 'cancel'])
                ->whereNumber('booking')
                ->name('payments.cancel');
        });

        // ── Properties & Property Types ───────────────────────
        // Every PropertyType SFC must guard against editing GLOBAL types
        // (tenant_id IS NULL) — those belong to the super-admin.
        Route::middleware('permission:view properties')->group(function () {
            Route::livewire('/properties', 'tenant::pages.property.view-property')
                ->name('properties.index');

            Route::livewire('/property-types', 'tenant::pages.property-type.view-type')
                ->name('property-types.index');
        });

        Route::middleware('permission:manage properties')->group(function () {
            Route::livewire('/properties/create', 'tenant::pages.property.create-property')
                ->name('properties.create');

            Route::livewire('/properties/{property}/edit', 'tenant::pages.property.edit-property')
                ->whereNumber('property')
                ->name('properties.edit');

            Route::livewire('/property-types/create', 'tenant::pages.property-type.create-type')
                ->name('property-types.create');

            Route::livewire('/property-types/{type}/edit', 'tenant::pages.property-type.edit-type')
                ->whereNumber('type')
                ->name('property-types.edit');
        });

        // ── Services ──────────────────────────────────────────
        Route::middleware('permission:view services')->group(function () {
            Route::livewire('/services', 'tenant::pages.service.view-service')
                ->name('services.index');
        });

        Route::middleware('permission:manage services')->group(function () {
            Route::livewire('/services/create', 'tenant::pages.service.create-service')
                ->name('services.create');

            Route::livewire('/services/{service}/edit', 'tenant::pages.service.edit-service')
                ->whereNumber('service')
                ->name('services.edit');
        });

        // ── Employees ─────────────────────────────────────────
        Route::middleware('permission:view employees')->group(function () {
            Route::livewire('/employees', 'tenant::pages.employee.view-employee')
                ->name('employees.index');
        });

        Route::middleware('permission:manage employees')->group(function () {
            Route::livewire('/employees/create', 'tenant::pages.employee.create-employee')
                ->name('employees.create');

            Route::livewire('/employees/{employee}/edit', 'tenant::pages.employee.edit-employee')
                ->whereNumber('employee')
                ->name('employees.edit');
        });

        // ── Events ────────────────────────────────────────────
        Route::middleware('permission:view events')->group(function () {
            Route::livewire('/events', 'tenant::pages.event.view-event')
                ->name('events.index');
        });

        Route::middleware('permission:manage events')->group(function () {
            Route::livewire('/events/create', 'tenant::pages.event.create-event')
                ->name('events.create');

            Route::livewire('/events/{event}/edit', 'tenant::pages.event.edit-event')
                ->whereNumber('event')
                ->name('events.edit');
        });

        // ── Admin-only (role gate, not permission) ────────────
        // {index} wildcard avoids colliding with Spatie's Role binding.
        Route::middleware('role:admin|super-admin')->group(function () {
            Route::livewire('/settings', 'tenant::pages.settings.business-profile')
                ->name('settings.index');

            Route::livewire('/roles', 'tenant::pages.role.view-role')
                ->name('roles.index');

            Route::livewire('/roles/create', 'tenant::pages.role.create-role')
                ->name('roles.create');

            Route::livewire('/roles/{index}/edit', 'tenant::pages.role.edit-role')
                ->whereNumber('index')
                ->name('roles.edit');
        });
    });