<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Middleware\Authenticate;
use App\Http\Middleware\IsSuperAdmin;
use App\Http\Middleware\IsTenantAdmin;
use App\Models\Booking;
use App\Http\Controllers\Auth\LoginController;
use App\Scopes\TenantScope;

/*
|--------------------------------------------------------------------------
| Public & Guest Routes
|--------------------------------------------------------------------------
*/
Route::livewire('/', 'public::pages.index')->name('home');
Route::livewire('/about', 'public::pages.about')->name('about');
Route::livewire('/contact', 'public::pages.learnmore')->name('learnmore');

Route::livewire('/register-business', 'public::pages.register-business')->name('register_business');

// Explore Map (public interactive map)
Route::livewire('/explore/map', 'public::pages.explore-map')->name('explore.map');

// Satellite style for MapLibre (used by Explore Map satellite toggle)
Route::get('/map/satellite-style', function () {
    return response()->json([
        'version' => 8,
        'sources' => [
            'satellite' => [
                'type' => 'raster',
                'tiles' => [
                    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                ],
                'tileSize' => 256,
                'maxzoom' => 19,
                'attribution' => 'Tiles &copy; Esri — Source: Esri, Maxar, Earthstar Geographics',
            ],
        ],
        'layers' => [
            [
                'id' => 'satellite-layer',
                'type' => 'raster',
                'source' => 'satellite',
                'minzoom' => 0,
                'maxzoom' => 22,
            ],
        ],
    ], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
})->name('map.satellite.style');

// Tenant Profile / Details (public view of a business)
Route::livewire('/business/{slug}', 'public::pages.tenant-show')->name('tenant.show');

// Tenant Offerings (accommodations & services for a business)
Route::livewire('/business/{slug}/offerings', 'public::pages.business-offerings')->name('business.offerings');

// Tourist Spot Details Profile (Public View)
Route::livewire('/destination/{id}', 'public::pages.tourist-spot-details')->name('destination.details');

// ★ Events & Fiestas (Public)
Route::livewire('/events', 'public::pages.events')->name('events');

// Event direct link redirect
Route::get('/events/{event}', function ($event) {
    return redirect()->route('events', ['event' => $event]);
})->name('event.show');

// Auth
Route::livewire('/profile', 'public::pages.profile')->name('profile');

// --- Traditional login routes (replaces broken Livewire login) ---
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);

Route::livewire('/register', 'public::auth.register')->name('register');
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

// Authenticated Customer Routes (Booking, My Bookings, etc.)
Route::middleware(['auth'])->group(function () {
    Route::livewire('/booking/create/{publicproperty}', 'public::pages.create-booking')->name('booking.create');
    Route::livewire('/my-bookings', 'public::pages.my-bookings')->name('my-bookings');

    // ★ Receipt route (printable)
    Route::get('/booking/receipt/{booking}', function ($bookingId) {
        // Fetch booking WITHOUT the TenantScope so tourists can see their own bookings
        $booking = Booking::withoutGlobalScope(TenantScope::class)->findOrFail($bookingId);

        // Ensure the authenticated user owns this booking
        if (Auth::id() !== $booking->user_id) {
            abort(403);
        }

        // Load relationships without global scopes (for tourist)
        $booking->loadMissing([
            'items' => fn($q) => $q->withoutGlobalScope(TenantScope::class),
            'items.property' => fn($q) => $q->withoutGlobalScope(TenantScope::class),
            'items.property.tenant' => fn($q) => $q->withoutGlobalScope(TenantScope::class),
            'services' => fn($q) => $q->withoutGlobalScope(TenantScope::class),
            'services.service' => fn($q) => $q->withoutGlobalScope(TenantScope::class),
            'payments' => fn($q) => $q->withoutGlobalScope(TenantScope::class),
        ]);

        return view('public.pages.booking-receipt', [
            'booking' => $booking,
            'property' => $booking->items->first()->property ?? null,
            'tenant' => $booking->items->first()->property->tenant ?? null,
        ]);
    })->name('booking.receipt');
});

// ★ Public payment callbacks (PayMongo redirects for tourists)
Route::get('/booking/payment/success/{booking}', function ($bookingId) {
    $booking = Booking::withoutGlobalScope(TenantScope::class)->findOrFail($bookingId);

    if (Auth::id() !== $booking->user_id) {
        abort(403);
    }

    return redirect()->route('my-bookings')->with('message', 'Payment successful! Your booking is updated.');
})->name('booking.payment.success');

Route::get('/booking/payment/cancel/{booking}', function ($bookingId) {
    $booking = Booking::withoutGlobalScope(TenantScope::class)->findOrFail($bookingId);

    if (Auth::id() !== $booking->user_id) {
        abort(403);
    }

    if ($booking->status === Booking::STATUS_PENDING) {
        $booking->update(['status' => Booking::STATUS_CANCELLED]);
    }

    // ★ Fixed: use withoutGlobalScope() when retrieving the first booking item
    $propertyId = $booking->items()
        ->withoutGlobalScope(TenantScope::class)
        ->first()
        ->property_id ?? null;

    if ($propertyId) {
        return redirect()->route('booking.create', ['publicproperty' => $propertyId])
            ->with('error', 'Payment was cancelled. The temporary booking has been cancelled.');
    }

    return redirect()->route('my-bookings')->with('error', 'Payment cancelled.');
})->name('booking.payment.cancel');

/*
|--------------------------------------------------------------------------
| Application Routes (Separated by Concern)
|--------------------------------------------------------------------------
*/

// ==========================================
// 1. SUPER ADMIN ROUTES (Platform Level)
// ==========================================
Route::prefix('platform')->name('superadmin.')->middleware([Authenticate::class, IsSuperAdmin::class])->group(function () {
    // Dashboard
    Route::livewire('/dashboard', 'superadmin::pages.dashboard.dashboard-page')->name('dashboard');

    // Analytics
    Route::livewire('/analytics', 'superadmin::pages.analytics.platform-analytics')->name('analytics');

    Route::livewire('/profile', 'superadmin::pages.profile.edit-profile')->name('profile');

    // Global Platform Users
    Route::livewire('/users', 'superadmin::pages.user.view-user')->name('users.index');
    Route::livewire('/users/create', 'superadmin::pages.user.create-user')->name('users.create');
    Route::livewire('/users/{user}/edit', 'superadmin::pages.user.edit-user')->name('users.edit');

    // Tenants
    Route::livewire('/tenants', 'superadmin::pages.tenant.view-tenant')->name('tenants.index');
    Route::livewire('/tenants/create', 'superadmin::pages.tenant.create-tenant')->name('tenants.create');
    Route::livewire('/tenants/{tenant}/preview', 'superadmin::pages.tenant.preview-tenant')->name('tenants.preview');
    Route::livewire('/tenants/{tenant}/edit', 'superadmin::pages.tenant.edit-tenant')->name('tenants.edit');

    // Roles
    Route::livewire('/roles', 'superadmin::pages.role.view-role')->name('roles.index');
    Route::livewire('/roles/create', 'superadmin::pages.role.create-role')->name('roles.create');
    Route::livewire('/roles/{role}/edit', 'superadmin::pages.role.edit-role')->name('roles.edit');

    // Tenant Types (Business Categories)
    Route::livewire('/tenant-types', 'superadmin::pages.tenant-type.view-type')->name('tenant-types.index');
    Route::livewire('/tenant-types/create', 'superadmin::pages.tenant-type.create-type')->name('tenant-types.create');
    Route::livewire('/tenant-types/{type}/edit', 'superadmin::pages.tenant-type.edit-type')->name('tenant-types.edit');

    // Map Markers (Master Map Control)
    Route::livewire('/map-markers', 'superadmin::pages.map-marker.manage-map-markers')->name('map-markers.index');

    // ★ Homepage Editor
    Route::livewire('/homepage-editor', 'superadmin::pages.homepage.homepage-editor')->name('homepage.editor');
    Route::livewire('/about-editor', 'superadmin::pages.homepage.about-editor')->name('about.editor');

    // ★ Events Management (Super Admin)
    Route::livewire('/events', 'superadmin::pages.event.view-event')->name('events.index');
    Route::livewire('/events/create', 'superadmin::pages.event.create-event')->name('events.create');
    Route::livewire('/events/{event}/edit', 'superadmin::pages.event.edit-event')->name('events.edit');
});


// ==========================================
// 2. TENANT ADMIN ROUTES (Business Level)
// ==========================================
Route::prefix('admin')->name('tenant.')->middleware([
    Authenticate::class,
    IsTenantAdmin::class,
])->group(function () {
    // Dashboard & Settings
    Route::livewire('/dashboard', 'tenant::pages.dashboard.dashboard-page')->name('dashboard');
    Route::livewire('/settings', 'tenant::pages.settings.business-profile')->name('settings.index');
    Route::livewire('/tourist-spot', 'tenant::pages.settings.tourist-spot-overview')->name('settings.overview');

    // Bookings
    Route::livewire('/bookings', 'tenant::pages.booking.view-booking')->name('bookings.index');
    Route::livewire('/bookings/create', 'tenant::pages.booking.create-booking')->name('bookings.create');
    Route::livewire('/bookings/{booking}/edit', 'tenant::pages.booking.edit-booking')->name('bookings.edit');
    Route::livewire('/bookings/history', 'tenant::pages.booking.history')->name('bookings.history');
    // Booking show page
    Route::get('/bookings/{booking}', function (Booking $booking) {
        return view('tenant.pages.booking.show-booking', ['booking' => $booking]);
    })->name('bookings.show');
    // Booking delete route
    Route::delete('/bookings/{booking}', function (Booking $booking) {
        $booking->forceDelete();
        return redirect()->route('tenant.bookings.index')->with('message', 'Booking deleted successfully.');
    })->name('bookings.destroy');

    // Customers – only the creation form is kept
    Route::livewire('/customers/create', 'tenant::pages.customer.create-customer')->name('customers.create');

    // Employees
    Route::livewire('/employees', 'tenant::pages.employee.view-employee')->name('employees.index');
    Route::livewire('/employees/create', 'tenant::pages.employee.create-employee')->name('employees.create');
    Route::livewire('/employees/{employee}/edit', 'tenant::pages.employee.edit-employee')->name('employees.edit');
    Route::livewire('/employee-dashboard', 'tenant::pages.employee.dashboard')->name('employee.dashboard');

    // Properties
    Route::livewire('/properties', 'tenant::pages.property.view-property')->name('properties.index');
    Route::livewire('/properties/create', 'tenant::pages.property.create-property')->name('properties.create');
    Route::livewire('/properties/{property}/edit', 'tenant::pages.property.edit-property')->name('properties.edit');

    // Property Types (tenant only)
    Route::livewire('/property-types', 'tenant::pages.property-type.view-type')->name('property-types.index');
    Route::livewire('/property-types/create', 'tenant::pages.property-type.create-type')->name('property-types.create');
    Route::livewire('/property-types/{type}/edit', 'tenant::pages.property-type.edit-type')->name('property-types.edit');

    // Services
    Route::livewire('/services', 'tenant::pages.service.view-service')->name('services.index');
    Route::livewire('/services/create', 'tenant::pages.service.create-service')->name('services.create');
    Route::livewire('/services/{service}/edit', 'tenant::pages.service.edit-service')->name('services.edit');

    // Financials
    Route::livewire('/payments', 'tenant::pages.payment.view-payment')->name('payments.index');
    Route::livewire('/payments/create/{booking}', 'tenant::pages.payment.create-payment')->name('payments.create');
    Route::livewire('/payments/{payment}/edit', 'tenant::pages.payment.edit-payment')->name('payments.edit');

    // PayMongo Payment Routes (admin) – now redirect to Payments index
    Route::get('/payments/success/{booking}', function (Booking $booking) {
        return redirect()->route('tenant.payments.index')
            ->with('message', 'Payment completed! The payment record has been updated.');
    })->name('payments.success');

    Route::get('/payments/cancel/{booking}', function (Booking $booking) {
        return redirect()->route('tenant.payments.index')
            ->with('error', 'Payment was cancelled.');
    })->name('payments.cancel');

    // Custom Roles (Tenant)
    Route::livewire('/roles', 'tenant::pages.role.view-role')->name('roles.index');
    Route::livewire('/roles/create', 'tenant::pages.role.create-role')->name('roles.create');
    Route::livewire('/roles/{index}/edit', 'tenant::pages.role.edit-role')->name('roles.edit');

    // Analytics Dashboard
    Route::livewire('/analytics', 'tenant::pages.analytics.dashboard')->name('analytics.index');

    // ★ Events Management (Tenant Admin)
    Route::livewire('/events', 'tenant::pages.event.view-event')->name('events.index');
    Route::livewire('/events/create', 'tenant::pages.event.create-event')->name('events.create');
    Route::livewire('/events/{event}/edit', 'tenant::pages.event.edit-event')->name('events.edit');
});