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

// ── Public pages ─────────────────────────────────────────────

Route::livewire('/', 'public::pages.index')->name('home');
Route::livewire('/about', 'public::pages.about')->name('about');
Route::livewire('/privacy-policy', 'public::pages.privacy-policy')->name('privacy.policy');
Route::livewire('/explore/map', 'public::pages.explore-map')->name('explore.map');

Route::get('/api/route', RouteController::class)
    ->middleware('throttle:60,1')
    ->name('api.route');

Route::get('/map/satellite-style', MapSatelliteStyleController::class)
    ->name('map.satellite.style');

// Throttled: pings the DB on every request. Public endpoint — cheap DoS
// amplifier without a limit. 60/min is generous for a legit uptime check.
Route::get('/health', HealthController::class)
    ->middleware('throttle:60,1')
    ->name('health');

Route::livewire('/business/{slug}', 'public::pages.tenant-show')->name('tenant.show');
Route::livewire('/business/{slug}/offerings', 'public::pages.business-offerings')->name('business.offerings');
Route::livewire('/tourist-spots', 'public::pages.tourist-spots')->name('tourist-spots.index');

Route::livewire('/events', 'public::pages.events')->name('events');
Route::redirect('/events/{event}', '/events')
    ->whereNumber('event')
    ->name('event.show');

// ── Auth (guest) ─────────────────────────────────────────────

Route::livewire('/login', 'public::auth.login-form')
    ->middleware('throttle:auth.login.ip')
    ->name('login');

Route::livewire('/register', 'public::auth.register')
    ->middleware('throttle:auth.register.ip')
    ->name('register');

Route::livewire('/forgot-password', 'public::auth.forgot-password')
    ->middleware('throttle:auth.password.request.ip')
    ->name('password.request');

Route::livewire('/reset-password/{token}', 'public::auth.reset-password')
    ->where('token', '[A-Za-z0-9]+')
    ->name('password.reset');

Route::post('/logout', LogoutController::class)->name('logout');

// ── Tourist / applicant (authenticated) ──────────────────────

Route::middleware(['auth'])->group(function () {

    Route::livewire('/profile', 'public::pages.profile')->name('profile');

    Route::livewire('/profile/delete', 'public::pages.delete-account')
        ->name('account.delete');

    Route::livewire('/notifications', 'public::pages.notifications')
        ->name('notifications.index');

    Route::post('/switch-mode', [UserModeController::class, 'switch'])
        ->name('mode.switch');

    // Bookings
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

    // Business registration (KYB) — throttled to match /register.
    // Both the GET (page render) and POST (draft-create) are abuse
    // surfaces: a looped POST creates draft BusinessApplication rows
    // that consume storage and enumerate in the superadmin queue.
    Route::livewire('/register-business', 'public::pages.register-business')
        ->middleware('throttle:auth.register.ip')
        ->name('register_business');

    Route::post('/register-business', [RegisterBusinessController::class, 'store'])
        ->middleware('throttle:auth.register.ip')
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

// ── Super admin (/platform) ──────────────────────────────────

Route::prefix('platform')
    ->name('superadmin.')
    ->middleware([Authenticate::class, IsSuperAdmin::class])
    ->group(function () {

        // Overview
        Route::livewire('/dashboard', 'superadmin::pages.dashboard.dashboard-page')
            ->name('dashboard');

        Route::livewire('/analytics', 'superadmin::pages.analytics.platform-analytics')
            ->name('analytics');

        Route::livewire('/notifications', 'superadmin::pages.notifications')
            ->name('notifications.index');

        Route::livewire('/health/queries', 'superadmin::pages.health.slow-query-dashboard')
            ->name('health.queries');

        Route::livewire('/profile', 'superadmin::pages.profile.edit-profile')
            ->name('profile');

        // Users
        Route::livewire('/users', 'superadmin::pages.user.view-user')
            ->name('users.index');

        Route::livewire('/users/create', 'superadmin::pages.user.create-user')
            ->name('users.create');

        // Export: throttled. Each hit streams the full user table to CSV —
        // cheap for the operator, expensive if hammered.
        Route::get('/users/export', \App\Http\Controllers\Superadmin\UserExportController::class)
            ->middleware('throttle:10,1')
            ->name('users.export');

        Route::livewire('/users/{user}/edit', 'superadmin::pages.user.edit-user')
            ->whereNumber('user')
            ->name('users.edit');

        // Tenants
        Route::livewire('/tenants', 'superadmin::pages.tenant.view-tenant')
            ->name('tenants.index');

        Route::livewire('/tenants/create', 'superadmin::pages.tenant.create-tenant')
            ->name('tenants.create');

        // Export: throttled.
        Route::get('/tenants/export', \App\Http\Controllers\Superadmin\TenantExportController::class)
            ->middleware('throttle:10,1')
            ->name('tenants.export');

        Route::livewire('/tenants/{tenant}/preview', 'superadmin::pages.tenant.preview-tenant')
            ->whereNumber('tenant')
            ->name('tenants.preview');

        Route::livewire('/tenants/{tenant}/edit', 'superadmin::pages.tenant.edit-tenant')
            ->whereNumber('tenant')
            ->name('tenants.edit');

        // Roles
        Route::livewire('/roles', 'superadmin::pages.role.view-role')
            ->name('roles.index');

        Route::livewire('/roles/create', 'superadmin::pages.role.create-role')
            ->name('roles.create');

        // Export: throttled.
        Route::get('/roles/export', \App\Http\Controllers\Superadmin\RoleExportController::class)
            ->middleware('throttle:10,1')
            ->name('roles.export');

        Route::livewire('/roles/{role}/edit', 'superadmin::pages.role.edit-role')
            ->whereNumber('role')
            ->name('roles.edit');

        // Tenant types
        Route::livewire('/tenant-types', 'superadmin::pages.tenant-type.view-type')
            ->name('tenant-types.index');

        Route::livewire('/tenant-types/create', 'superadmin::pages.tenant-type.create-type')
            ->name('tenant-types.create');

        Route::livewire('/tenant-types/{type}/edit', 'superadmin::pages.tenant-type.edit-type')
            ->whereNumber('type')
            ->name('tenant-types.edit');

        // Map
        Route::livewire('/map-markers', 'superadmin::pages.map-marker.manage-map-markers')
            ->name('map-markers.index');

        Route::livewire('/marker-categories', 'superadmin::pages.map-marker.manage-marker-categories')
            ->name('marker-categories.index');

        // Homepage / About editors
        Route::livewire('/homepage-editor', 'superadmin::pages.homepage.homepage-editor')
            ->name('homepage.editor');

        Route::livewire('/about-editor', 'superadmin::pages.homepage.about-editor')
            ->name('about.editor');

        // Events
        Route::livewire('/events', 'superadmin::pages.event.view-event')
            ->name('events.index');

        Route::livewire('/events/create', 'superadmin::pages.event.create-event')
            ->name('events.create');

        Route::livewire('/events/{event}/edit', 'superadmin::pages.event.edit-event')
            ->whereNumber('event')
            ->name('events.edit');

        // KYB review queue
        Route::livewire('/business-applications', 'superadmin::pages.business-application.view-business-application')
            ->name('business-applications.index');

        Route::livewire('/business-applications/{application}', 'superadmin::pages.business-application.show-business-application')
            ->whereNumber('application')
            ->name('business-applications.show');

        // Account deletion review queue
        Route::livewire('/deletion-requests', 'superadmin::pages.deletion-request.view-deletion-requests')
            ->name('deletion-requests.index');

        // NOTE: parameter name is `{request}` and shadows Laravel's $request.
        // Do NOT rename without also updating the SFC's mount() argument —
        // Livewire binds route params to mount() arguments by NAME.
        Route::livewire('/deletion-requests/{request}', 'superadmin::pages.deletion-request.show-deletion-request')
            ->whereNumber('request')
            ->name('deletion-requests.show');

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

        // Renewal review queue
        Route::livewire('/renewals', 'superadmin::pages.renewal.view-renewals')
            ->name('renewals.index');

        Route::livewire('/renewals/{document}', 'superadmin::pages.renewal.review-renewal')
            ->whereNumber('document')
            ->name('renewals.review');
    });

// ── Tenant admin (/admin) ────────────────────────────────────

Route::prefix('admin')
    ->name('tenant.')
    ->middleware([
        Authenticate::class,
        IsTenantAdmin::class,
        BlockIfDeletionPending::class,
    ])
    ->group(function () {

        Route::livewire('/account/deletion-pending', 'tenant::pages.settings.deletion-pending')
            ->name('account.deletion-pending');

        Route::livewire('/dashboard', 'tenant::pages.dashboard.dashboard-page')
            ->name('dashboard');

        Route::livewire('/employee-dashboard', 'tenant::pages.employee.dashboard')
            ->name('employee.dashboard');

        Route::livewire('/account', 'tenant::pages.settings.account-profile')
            ->name('account.index');

        Route::livewire('/notifications', 'tenant::pages.notifications')
            ->name('notifications.index');

        // Analytics
        Route::middleware('permission:view analytics')->group(function () {
            Route::livewire('/analytics', 'tenant::pages.analytics.dashboard')
                ->name('analytics.index');
        });

        // Bookings
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

        // Payments
        Route::middleware('permission:view payments')->group(function () {
            Route::livewire('/payments', 'tenant::pages.payment.view-payment')
                ->name('payments.index');
        });

        Route::middleware('permission:manage payments')->group(function () {
            Route::livewire('/payments/create/{booking}', 'tenant::pages.payment.create-payment')
                ->whereNumber('booking')
                ->name('payments.create');

            Route::get('/payments/success/{booking}', [PaymentCallbackController::class, 'success'])
                ->whereNumber('booking')
                ->name('payments.success');

            Route::get('/payments/cancel/{booking}', [PaymentCallbackController::class, 'cancel'])
                ->whereNumber('booking')
                ->name('payments.cancel');
        });

        // Properties & property types
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

        // Services
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

        // Employees
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

        // Events
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

        // Admin-only: settings, gallery, documents, roles
        Route::middleware('role:admin|super-admin')->group(function () {
            Route::livewire('/settings', 'tenant::pages.settings.business-profile')
                ->name('settings.index');

            // Photo Gallery — manage the images shown on the public
            // offerings page. Admin-only because a public-facing content
            // surface, not a per-employee operation.
            Route::livewire('/gallery', 'tenant::pages.settings.gallery-manager')
                ->name('gallery.index');

            Route::livewire('/documents', 'tenant::pages.documents.view-documents')
                ->name('documents.index');

            Route::livewire('/documents/{type}/renew', 'tenant::pages.documents.renew-document')
                ->where('type', '[a-z_]+')
                ->name('documents.renew');

            Route::livewire('/roles', 'tenant::pages.role.view-role')
                ->name('roles.index');

            Route::livewire('/roles/create', 'tenant::pages.role.create-role')
                ->name('roles.create');

            Route::livewire('/roles/{index}/edit', 'tenant::pages.role.edit-index')
                ->whereNumber('index')
                ->name('roles.edit');
        });
    });