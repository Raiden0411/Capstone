<?php

use App\Http\Controllers\Public\BookingPaymentController;
use App\Http\Controllers\Public\BookingReceiptController;
use App\Http\Controllers\Public\RegisterBusinessController;
use App\Http\Controllers\Tenant\BookingController as TenantBookingController;
use App\Http\Controllers\UserModeController;
use App\Http\Middleware\IsSuperAdmin;
use App\Http\Middleware\IsTenantAdmin;
use App\Models\Booking;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::livewire('/', 'public::pages.index')->name('home');
Route::livewire('/about', 'public::pages.about')->name('about');

Route::livewire('/explore/map', 'public::pages.explore-map')->name('explore.map');

Route::get('/map/satellite-style', function () {
    return response()->json([
        'version' => 8,
        'sources' => [
            'satellite' => [
                'type'        => 'raster',
                'tiles'       => [
                    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                ],
                'tileSize'    => 256,
                'maxzoom'     => 19,
                'attribution' => 'Tiles &copy; Esri — Source: Esri, Maxar, Earthstar Geographics',
            ],
        ],
        'layers' => [
            [
                'id'      => 'satellite-layer',
                'type'    => 'raster',
                'source'  => 'satellite',
                'minzoom' => 0,
                'maxzoom' => 22,
            ],
        ],
    ], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
})->name('map.satellite.style');

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();

        return response()->json(['status' => 'ok']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error'], 500);
    }
})->name('health');

Route::livewire('/business/{slug}', 'public::pages.tenant-show')->name('tenant.show');
Route::livewire('/business/{slug}/offerings', 'public::pages.business-offerings')->name('business.offerings');

Route::livewire('/tourist-spots', 'public::pages.tourist-spots')->name('tourist-spots.index');

Route::livewire('/events', 'public::pages.events')->name('events');
Route::redirect('/events/{event}', '/events')
    ->whereNumber('event')
    ->name('event.show');

/*
|--------------------------------------------------------------------------
| Authentication — Livewire SFCs on `layouts.auth`
|
| Login is throttled at the route level by `auth.login.ip` (20 req/min
| per IP — registered in AppServiceProvider::boot()). This caps page
| refreshes and naive scrapers. The real credential brute-force guard
| lives inside the SFC's login() method, which checks a per-email+IP
| key before Auth::attempt() runs — see ⚡login-form.blade.php.
|--------------------------------------------------------------------------
*/

Route::livewire('/login', 'public::auth.login-form')
    ->middleware('throttle:auth.login.ip')
    ->name('login');

Route::livewire('/register', 'public::auth.register')->name('register');

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated Routes (Tourist / Public)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    // Tourist profile
    Route::livewire('/profile', 'public::pages.profile')->name('profile');

    /*
    |----------------------------------------------------------------------
    | Mode switch — dual-role accounts (business owners) only
    |----------------------------------------------------------------------
    */
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

    /*
    |----------------------------------------------------------------------
    | Business Registration (KYB)
    |
    | Route order matters: /register-business (landing) BEFORE
    | /register-business/{application} (edit form), so the literal path
    | is not consumed by the wildcard.
    |----------------------------------------------------------------------
    */

    // Landing — state-aware entry point
    Route::livewire('/register-business', 'public::pages.register-business')
        ->name('register_business');

    // Create or resume a draft (POST action)
    Route::post('/register-business', [RegisterBusinessController::class, 'store'])
        ->name('register_business.start');

    // GET — renders the edit form for a draft / needs-revision application
    Route::livewire('/register-business/{application}', 'public::pages.edit-business-application')
        ->whereNumber('application')
        ->name('register_business.edit');

    // PUT — persists updates
    Route::put('/register-business/{application}', [RegisterBusinessController::class, 'update'])
        ->whereNumber('application')
        ->name('register_business.update');

    // POST — upload a KYB document
    Route::post('/register-business/{application}/documents', [RegisterBusinessController::class, 'uploadDocument'])
        ->whereNumber('application')
        ->name('register_business.documents.upload');

    // POST — submit the application for review
    Route::post('/register-business/{application}/submit', [RegisterBusinessController::class, 'submit'])
        ->whereNumber('application')
        ->name('register_business.submit');
});

/*
|--------------------------------------------------------------------------
| Super Admin Routes
|--------------------------------------------------------------------------
*/

Route::prefix('platform')
    ->name('superadmin.')
    ->middleware([Authenticate::class, IsSuperAdmin::class])
    ->group(function () {
        Route::livewire('/dashboard', 'superadmin::pages.dashboard.dashboard-page')
            ->name('dashboard');
        Route::livewire('/analytics', 'superadmin::pages.analytics.platform-analytics')
            ->name('analytics');
        Route::livewire('/profile', 'superadmin::pages.profile.edit-profile')
            ->name('profile');

        /*
        |------------------------------------------------------------------
        | Users — register /create BEFORE the wildcard /{user}/edit
        |------------------------------------------------------------------
        */
        Route::livewire('/users', 'superadmin::pages.user.view-user')
            ->name('users.index');
        Route::livewire('/users/create', 'superadmin::pages.user.create-user')
            ->name('users.create');
        Route::livewire('/users/{user}/edit', 'superadmin::pages.user.edit-user')
            ->whereNumber('user')
            ->name('users.edit');

        /*
        |------------------------------------------------------------------
        | Tenants — register /create BEFORE the wildcards
        |------------------------------------------------------------------
        */
        Route::livewire('/tenants', 'superadmin::pages.tenant.view-tenant')
            ->name('tenants.index');
        Route::livewire('/tenants/create', 'superadmin::pages.tenant.create-tenant')
            ->name('tenants.create');
        Route::livewire('/tenants/{tenant}/preview', 'superadmin::pages.tenant.preview-tenant')
            ->whereNumber('tenant')
            ->name('tenants.preview');
        Route::livewire('/tenants/{tenant}/edit', 'superadmin::pages.tenant.edit-tenant')
            ->whereNumber('tenant')
            ->name('tenants.edit');

        /*
        |------------------------------------------------------------------
        | Roles — register /create BEFORE the wildcard
        |------------------------------------------------------------------
        */
        Route::livewire('/roles', 'superadmin::pages.role.view-role')
            ->name('roles.index');
        Route::livewire('/roles/create', 'superadmin::pages.role.create-role')
            ->name('roles.create');
        Route::livewire('/roles/{role}/edit', 'superadmin::pages.role.edit-role')
            ->whereNumber('role')
            ->name('roles.edit');

        /*
        |------------------------------------------------------------------
        | Tenant Types — register /create BEFORE the wildcard
        |------------------------------------------------------------------
        */
        Route::livewire('/tenant-types', 'superadmin::pages.tenant-type.view-type')
            ->name('tenant-types.index');
        Route::livewire('/tenant-types/create', 'superadmin::pages.tenant-type.create-type')
            ->name('tenant-types.create');
        Route::livewire('/tenant-types/{type}/edit', 'superadmin::pages.tenant-type.edit-type')
            ->whereNumber('type')
            ->name('tenant-types.edit');

        /*
        |------------------------------------------------------------------
        | Map Markers
        |------------------------------------------------------------------
        */
        Route::livewire('/map-markers', 'superadmin::pages.map-marker.manage-map-markers')
            ->name('map-markers.index');
        Route::livewire('/marker-categories', 'superadmin::pages.map-marker.manage-marker-categories')
            ->name('marker-categories.index');

        /*
        |------------------------------------------------------------------
        | Homepage / About editors
        |------------------------------------------------------------------
        */
        Route::livewire('/homepage-editor', 'superadmin::pages.homepage.homepage-editor')
            ->name('homepage.editor');
        Route::livewire('/about-editor', 'superadmin::pages.homepage.about-editor')
            ->name('about.editor');

        /*
        |------------------------------------------------------------------
        | Events — register /create BEFORE the wildcard
        |------------------------------------------------------------------
        */
        Route::livewire('/events', 'superadmin::pages.event.view-event')
            ->name('events.index');
        Route::livewire('/events/create', 'superadmin::pages.event.create-event')
            ->name('events.create');
        Route::livewire('/events/{event}/edit', 'superadmin::pages.event.edit-event')
            ->whereNumber('event')
            ->name('events.edit');

        /*
        |------------------------------------------------------------------
        | KYB Business Applications — review queue (Livewire SFCs)
        |
        | Approve / reject / request-revision are handled inside the show
        | component as Livewire actions, so no separate routes are needed.
        |------------------------------------------------------------------
        */
        Route::livewire('/business-applications', 'superadmin::pages.business-application.view-business-application')
            ->name('business-applications.index');

        Route::livewire('/business-applications/{application}', 'superadmin::pages.business-application.show-business-application')
            ->whereNumber('application')
            ->name('business-applications.show');
    });

/*
|--------------------------------------------------------------------------
| Tenant Admin Routes (RBAC-enforced via `permission:` middleware)
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->name('tenant.')
    ->middleware([Authenticate::class, IsTenantAdmin::class])
    ->group(function () {

        /*
        |------------------------------------------------------------------
        | Dashboard — accessible to any authenticated employee
        |------------------------------------------------------------------
        */
        Route::livewire('/dashboard', 'tenant::pages.dashboard.dashboard-page')
            ->name('dashboard');
        Route::livewire('/employee-dashboard', 'tenant::pages.employee.dashboard')
            ->name('employee.dashboard');

        /*
        |------------------------------------------------------------------
        | Analytics — gated by permission so custom roles can also be granted
        |------------------------------------------------------------------
        */
        Route::middleware('permission:view analytics')->group(function () {
            Route::livewire('/analytics', 'tenant::pages.analytics.dashboard')
                ->name('analytics.index');
        });

        /*
        |------------------------------------------------------------------
        | Bookings — /create MUST be registered BEFORE /{booking}, otherwise
        | Laravel will try to bind "create" to a Booking ID and 404.
        | We register /create in its own group first, and add ->whereNumber()
        | to the wildcards as defense-in-depth.
        |------------------------------------------------------------------
        */
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

        /*
        |------------------------------------------------------------------
        | Payments
        |------------------------------------------------------------------
        */
        Route::middleware('permission:view payments')->group(function () {
            Route::livewire('/payments', 'tenant::pages.payment.view-payment')
                ->name('payments.index');
        });

        Route::middleware('permission:manage payments')->group(function () {
            Route::livewire('/payments/create/{booking}', 'tenant::pages.payment.create-payment')
                ->whereNumber('booking')
                ->name('payments.create');

            Route::get('/payments/success/{booking}', function (Booking $booking) {
                return redirect()->route('tenant.payments.index')
                    ->with('message', 'Payment completed! The payment record has been updated.');
            })->whereNumber('booking')->name('payments.success');

            Route::get('/payments/cancel/{booking}', function (Booking $booking) {
                return redirect()->route('tenant.payments.index')
                    ->with('error', 'Payment was cancelled.');
            })->whereNumber('booking')->name('payments.cancel');
        });

        /*
        |------------------------------------------------------------------
        | Properties & Property Types — /create BEFORE the wildcards
        |------------------------------------------------------------------
        */
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

        /*
        |------------------------------------------------------------------
        | Services — /create BEFORE the wildcard
        |------------------------------------------------------------------
        */
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

        /*
        |------------------------------------------------------------------
        | Employees — /create BEFORE the wildcard
        |------------------------------------------------------------------
        */
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

        /*
        |------------------------------------------------------------------
        | Events — /create BEFORE the wildcard
        |------------------------------------------------------------------
        */
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

        /*
        |------------------------------------------------------------------
        | Admin-only routes (settings, roles) — role gate, not permission gate
        |------------------------------------------------------------------
        */
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