<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Middleware\Authenticate;
use App\Http\Middleware\IsSuperAdmin;
use App\Http\Middleware\IsTenantAdmin;
use App\Models\Booking;

/*
|--------------------------------------------------------------------------
| Public & Guest Routes
|--------------------------------------------------------------------------
*/
Route::livewire('/', 'public::pages.index')->name('home');
Route::livewire('/about', 'public::pages.about')->name('about');
Route::livewire('/bookings', 'public::pages.bookings')->name('public.bookings');
Route::livewire('/learn-more', 'public::pages.learnmore')->name('learnmore');
Route::livewire('/menu-list', 'public::pages.menu-list')->name('menu-list');
Route::livewire('/reservation', 'public::pages.reservation')->name('reservation');
Route::livewire('/register-business', 'public::pages.register-business')->name('register_business');

// Explore Map (public interactive map)
Route::livewire('/explore/map', 'public::pages.explore-map')->name('explore.map');

// Tenant Profile / Details (public view of a business)
Route::livewire('/business/{slug}', 'public::pages.tenant-show')->name('tenant.show');

// Tenant Offerings (accommodations & services for a business)
Route::livewire('/business/{slug}/offerings', 'public::pages.business-offerings')->name('business.offerings');

// Tourist Spot Details Profile (Public View)
Route::livewire('/destination/{id}', 'public::pages.tourist-spot-details')->name('destination.details');

// Auth
Route::livewire('/login', 'public::auth.login')->name('login');
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
    // NEW: Tourist bookings history and management
    Route::livewire('/my-bookings', 'public::pages.my-bookings')->name('my-bookings');
});

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
});


// ==========================================
// 2. TENANT ADMIN ROUTES (Business Level)
// ==========================================
Route::prefix('admin')->name('tenant.')->middleware([Authenticate::class, IsTenantAdmin::class])->group(function () {
    // Dashboard & Settings
    Route::livewire('/dashboard', 'tenant::pages.dashboard.dashboard-page')->name('dashboard');
    Route::livewire('/settings', 'tenant::pages.settings.business-profile')->name('settings.index');
    Route::livewire('/gallery', 'tenant::pages.settings.gallery')->name('settings.gallery');
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

    // Customers – only the creation form is kept
    Route::livewire('/customers/create', 'tenant::pages.customer.create-customer')->name('customers.create');

    // Employees
    Route::livewire('/employees', 'tenant::pages.employee.view-employee')->name('employees.index');
    Route::livewire('/employees/create', 'tenant::pages.employee.create-employee')->name('employees.create');
    Route::livewire('/employees/{employee}/edit', 'tenant::pages.employee.edit-employee')->name('employees.edit');

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

    // PayMongo Payment Routes
    Route::get('/payments/success/{booking}', function (Booking $booking) {
        return redirect()->route('tenant.bookings.show', $booking)
            ->with('message', 'Payment completed! Processing may take a moment.');
    })->name('payments.success');

    Route::get('/payments/cancel/{booking}', function (Booking $booking) {
        return redirect()->route('tenant.bookings.show', $booking)
            ->with('error', 'Payment was cancelled.');
    })->name('payments.cancel');

    // Custom Roles (Tenant)
    Route::livewire('/roles', 'tenant::pages.role.view-role')->name('roles.index');
    Route::livewire('/roles/create', 'tenant::pages.role.create-role')->name('roles.create');
    Route::livewire('/roles/{index}/edit', 'tenant::pages.role.edit-role')->name('roles.edit');

    // Analytics Dashboard
    Route::livewire('/analytics', 'tenant::pages.analytics.dashboard')->name('analytics.index');
});