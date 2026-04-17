<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Middleware\Authenticate;
use App\Http\Middleware\IsSuperAdmin;
use App\Http\Middleware\IsTenantAdmin;

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
    
    // Property Types
    Route::livewire('/property-types', 'superadmin::pages.property-type.view-type')->name('property-types.index');
    Route::livewire('/property-types/create', 'superadmin::pages.property-type.create-type')->name('property-types.create');
    Route::livewire('/property-types/{type}/edit', 'superadmin::pages.property-type.edit-type')->name('property-types.edit');

    // Tenant Types (Business Categories)
    Route::livewire('/tenant-types', 'superadmin::tenant-type.view-type')->name('tenant-types.index');
    Route::livewire('/tenant-types/create', 'superadmin::tenant-type.create-type')->name('tenant-types.create');
    Route::livewire('/tenant-types/{type}/edit', 'superadmin::tenant-type.edit-type')->name('tenant-types.edit');

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

    // Bookings
    Route::livewire('/bookings', 'tenant::pages.booking.view-booking')->name('bookings.index');
    Route::livewire('/bookings/create', 'tenant::pages.booking.create-booking')->name('bookings.create');
    Route::livewire('/bookings/{id}/edit', 'tenant::pages.booking.edit-booking')->name('bookings.edit');

    // Customers
    Route::livewire('/customers', 'tenant::pages.customer.view-customer')->name('customers.index');
    Route::livewire('/customers/create', 'tenant::pages.customer.create-customer')->name('customers.create');
    Route::livewire('/customers/{customer}/edit', 'tenant::pages.customer.edit-customer')->name('customers.edit');

    // Employees
    Route::livewire('/employees', 'tenant::pages.employee.view-employee')->name('employees.index');
    Route::livewire('/employees/create', 'tenant::pages.employee.create-employee')->name('employees.create');
    Route::livewire('/employees/{employee}/edit', 'tenant::pages.employee.edit-employee')->name('employees.edit');

    // Properties
    Route::livewire('/properties', 'tenant::pages.property.view-property')->name('properties.index');
    Route::livewire('/properties/create', 'tenant::pages.property.create-property')->name('properties.create');
    Route::livewire('/properties/{property}/edit', 'tenant::pages.property.edit-property')->name('properties.edit');

    // Property Types
    Route::livewire('/property-types', 'tenant::pages.property-type.view-type')->name('property-types.index');
    Route::livewire('/property-types/create', 'tenant::pages.property-type.create-type')->name('property-types.create');
    Route::livewire('/property-types/{type}/edit', 'tenant::pages.property-type.edit-type')->name('property-types.edit');

    // Services
    Route::livewire('/services', 'tenant::pages.service.view-service')->name('services.index');
    Route::livewire('/services/create', 'tenant::pages.service.create-service')->name('services.create');
    Route::livewire('/services/{service}/edit', 'tenant::pages.service.edit-service')->name('services.edit');

    // Financials
    Route::livewire('/payments', 'tenant::pages.payment.view-payment')->name('payments.index');
    Route::livewire('/payments/create', 'tenant::pages.payment.create-payment')->name('payments.create');
    Route::livewire('/payments/{payment}/edit', 'tenant::pages.payment.edit-payment')->name('payments.edit');
    Route::livewire('/transactions', 'tenant::pages.transaction.view-transaction')->name('transactions.index');

   Route::livewire('/roles', 'tenant::pages.role.view-role')->name('roles.index');
Route::livewire('/roles/create', 'tenant::pages.role.create-role')->name('roles.create');
Route::livewire('/roles/{index}/edit', 'tenant::pages.role.edit-role')->name('roles.edit');
});