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

Route::livewire('/register-business', 'public::pages.register-business')->name('register_business');

Route::livewire('/explore/map', 'public::pages.explore-map')->name('explore.map');

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

// Public business profile page (tenant.show)
Route::livewire('/business/{slug}', 'public::pages.tenant-show')->name('tenant.show');
Route::livewire('/business/{slug}/offerings', 'public::pages.business-offerings')->name('business.offerings');

// NEW: Tourist Spots listing page
Route::livewire('/tourist-spots', 'public::pages.tourist-spots')->name('tourist-spots.index');

Route::livewire('/events', 'public::pages.events')->name('events');
Route::get('/events/{event}', function ($event) {
    return redirect()->route('events');
})->name('event.show');

Route::livewire('/profile', 'public::pages.profile')->name('profile');

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);

Route::livewire('/register', 'public::auth.register')->name('register');
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('login');
})->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::livewire('/booking/create/{publicproperty}', 'public::pages.create-booking')->name('booking.create');
    Route::livewire('/my-bookings', 'public::pages.my-bookings')->name('my-bookings');

    Route::get('/booking/receipt/{booking}', function ($bookingId) {
        $booking = Booking::withoutGlobalScope(TenantScope::class)->findOrFail($bookingId);
        if (Auth::id() !== $booking->user_id) {
            abort(403);
        }
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

    // Processing page – renamed route parameter
    Route::livewire('/booking/payment/processing/{bookingId}', 'public::pages.payment-processing')->name('booking.payment.processing');

    // Success route: redirect to processing page
    Route::get('/booking/payment/success/{booking}', function ($bookingId) {
        $booking = Booking::withoutGlobalScope(TenantScope::class)->findOrFail($bookingId);

        if (Auth::id() !== $booking->user_id) {
            abort(403);
        }

        return redirect()->route('booking.payment.processing', ['bookingId' => $booking->id]);
    })->name('booking.payment.success');

    Route::get('/booking/payment/cancel/{booking}', function ($bookingId) {
        $booking = Booking::withoutGlobalScope(TenantScope::class)->findOrFail($bookingId);

        if (Auth::id() !== $booking->user_id) {
            abort(403);
        }

        if ($booking->status === Booking::STATUS_PENDING) {
            $booking->update(['status' => Booking::STATUS_CANCELLED]);
        }

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
});

/*
|--------------------------------------------------------------------------
| Super Admin Routes
|--------------------------------------------------------------------------
*/
Route::prefix('platform')->name('superadmin.')->middleware([Authenticate::class, IsSuperAdmin::class])->group(function () {
    Route::livewire('/dashboard', 'superadmin::pages.dashboard.dashboard-page')->name('dashboard');
    Route::livewire('/analytics', 'superadmin::pages.analytics.platform-analytics')->name('analytics');
    Route::livewire('/profile', 'superadmin::pages.profile.edit-profile')->name('profile');

    Route::livewire('/users', 'superadmin::pages.user.view-user')->name('users.index');
    Route::livewire('/users/create', 'superadmin::pages.user.create-user')->name('users.create');
    Route::livewire('/users/{user}/edit', 'superadmin::pages.user.edit-user')->name('users.edit');

    Route::livewire('/tenants', 'superadmin::pages.tenant.view-tenant')->name('tenants.index');
    Route::livewire('/tenants/create', 'superadmin::pages.tenant.create-tenant')->name('tenants.create');
    Route::livewire('/tenants/{tenant}/preview', 'superadmin::pages.tenant.preview-tenant')->name('tenants.preview'); // ★ ADDED
    Route::livewire('/tenants/{tenant}/edit', 'superadmin::pages.tenant.edit-tenant')->name('tenants.edit');

    Route::livewire('/roles', 'superadmin::pages.role.view-role')->name('roles.index');
    Route::livewire('/roles/create', 'superadmin::pages.role.create-role')->name('roles.create');
    Route::livewire('/roles/{role}/edit', 'superadmin::pages.role.edit-role')->name('roles.edit');

    Route::livewire('/tenant-types', 'superadmin::pages.tenant-type.view-type')->name('tenant-types.index');
    Route::livewire('/tenant-types/create', 'superadmin::pages.tenant-type.create-type')->name('tenant-types.create');
    Route::livewire('/tenant-types/{type}/edit', 'superadmin::pages.tenant-type.edit-type')->name('tenant-types.edit');

    Route::livewire('/map-markers', 'superadmin::pages.map-marker.manage-map-markers')->name('map-markers.index');
    Route::livewire('/marker-categories', 'superadmin::pages.map-marker.manage-marker-categories')->name('marker-categories.index');

    Route::livewire('/homepage-editor', 'superadmin::pages.homepage.homepage-editor')->name('homepage.editor');
    Route::livewire('/about-editor', 'superadmin::pages.homepage.about-editor')->name('about.editor');

    Route::livewire('/events', 'superadmin::pages.event.view-event')->name('events.index');
    Route::livewire('/events/create', 'superadmin::pages.event.create-event')->name('events.create');
    Route::livewire('/events/{event}/edit', 'superadmin::pages.event.edit-event')->name('events.edit');
});

/*
|--------------------------------------------------------------------------
| Tenant Admin Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('tenant.')->middleware([
    Authenticate::class,
    IsTenantAdmin::class,
])->group(function () {
    Route::livewire('/dashboard', 'tenant::pages.dashboard.dashboard-page')->name('dashboard');
    Route::livewire('/settings', 'tenant::pages.settings.business-profile')->name('settings.index');

    Route::livewire('/bookings', 'tenant::pages.booking.view-booking')->name('bookings.index');
    Route::livewire('/bookings/create', 'tenant::pages.booking.create-booking')->name('bookings.create');
    Route::livewire('/bookings/{booking}/edit', 'tenant::pages.booking.edit-booking')->name('bookings.edit');
    Route::livewire('/bookings/history', 'tenant::pages.booking.history')->name('bookings.history');
    Route::get('/bookings/{booking}', function (Booking $booking) {
        return view('tenant.pages.booking.show-booking', ['booking' => $booking]);
    })->name('bookings.show');
    Route::delete('/bookings/{booking}', function (Booking $booking) {
        $booking->forceDelete();
        return redirect()->route('tenant.bookings.index')->with('message', 'Booking deleted successfully.');
    })->name('bookings.destroy');

    Route::livewire('/employees', 'tenant::pages.employee.view-employee')->name('employees.index');
    Route::livewire('/employees/create', 'tenant::pages.employee.create-employee')->name('employees.create');
    Route::livewire('/employees/{employee}/edit', 'tenant::pages.employee.edit-employee')->name('employees.edit');
    Route::livewire('/employee-dashboard', 'tenant::pages.employee.dashboard')->name('employee.dashboard');

    Route::livewire('/properties', 'tenant::pages.property.view-property')->name('properties.index');
    Route::livewire('/properties/create', 'tenant::pages.property.create-property')->name('properties.create');
    Route::livewire('/properties/{property}/edit', 'tenant::pages.property.edit-property')->name('properties.edit');

    Route::livewire('/property-types', 'tenant::pages.property-type.view-type')->name('property-types.index');
    Route::livewire('/property-types/create', 'tenant::pages.property-type.create-type')->name('property-types.create');
    Route::livewire('/property-types/{type}/edit', 'tenant::pages.property-type.edit-type')->name('property-types.edit');

    Route::livewire('/services', 'tenant::pages.service.view-service')->name('services.index');
    Route::livewire('/services/create', 'tenant::pages.service.create-service')->name('services.create');
    Route::livewire('/services/{service}/edit', 'tenant::pages.service.edit-service')->name('services.edit');

    Route::livewire('/payments', 'tenant::pages.payment.view-payment')->name('payments.index');
    Route::livewire('/payments/create/{booking}', 'tenant::pages.payment.create-payment')->name('payments.create');

    Route::get('/payments/success/{booking}', function (Booking $booking) {
        return redirect()->route('tenant.payments.index')
            ->with('message', 'Payment completed! The payment record has been updated.');
    })->name('payments.success');
    Route::get('/payments/cancel/{booking}', function (Booking $booking) {
        return redirect()->route('tenant.payments.index')
            ->with('error', 'Payment was cancelled.');
    })->name('payments.cancel');

    Route::livewire('/roles', 'tenant::pages.role.view-role')->name('roles.index');
    Route::livewire('/roles/create', 'tenant::pages.role.create-role')->name('roles.create');
    Route::livewire('/roles/{index}/edit', 'tenant::pages.role.edit-role')->name('roles.edit');

    Route::livewire('/analytics', 'tenant::pages.analytics.dashboard')->name('analytics.index');

    Route::livewire('/events', 'tenant::pages.event.view-event')->name('events.index');
    Route::livewire('/events/create', 'tenant::pages.event.create-event')->name('events.create');
    Route::livewire('/events/{event}/edit', 'tenant::pages.event.edit-event')->name('events.edit');
});