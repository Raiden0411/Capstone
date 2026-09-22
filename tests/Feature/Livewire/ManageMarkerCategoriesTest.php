<?php

use App\Models\SiteSetting;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    Auth::guard('web')->login($admin);
});

it('loads existing categories from site settings', function () {
    SiteSetting::setValue('marker_categories', [
        ['key' => 'cafe', 'label' => 'Cafe', 'color' => '#ff0000', 'icon_path' => null],
    ]);

    // SFC mount() normalizes each category — the assertion must match
    // the normalized shape (includes icon_svg + is_active).
    Livewire::test('superadmin::pages.map-marker.manage-marker-categories')
        ->assertSet('categories', [
            [
                'key'       => 'cafe',
                'label'     => 'Cafe',
                'color'     => '#ff0000',
                'icon_path' => null,
                'icon_svg'  => null,
                'is_active' => true,
            ],
        ]);
});

it('adds a new category and persists to site settings', function () {
    Livewire::test('superadmin::pages.map-marker.manage-marker-categories')
        ->set('newKey', 'restaurant')
        ->set('newLabel', 'Restaurant')
        ->set('newColor', '#00ff00')
        ->call('addCategory')
        ->assertHasNoErrors();

    $categories = SiteSetting::getValue('marker_categories', []);

    expect($categories)->toHaveCount(1);
    expect($categories[0]['key'])->toBe('restaurant');
    expect($categories[0]['label'])->toBe('Restaurant');
    expect($categories[0]['color'])->toBe('#00ff00');
});

it('updates an existing category and persists', function () {
    SiteSetting::setValue('marker_categories', [
        ['key' => 'cafe', 'label' => 'Cafe', 'color' => '#ff0000', 'icon_path' => null],
    ]);

    Livewire::test('superadmin::pages.map-marker.manage-marker-categories')
        ->set('categories.0.label', 'Coffee Shop')
        ->set('categories.0.color', '#123456')
        ->call('updateCategory', 0)
        ->assertHasNoErrors();

    $categories = SiteSetting::getValue('marker_categories', []);

    expect($categories[0]['label'])->toBe('Coffee Shop');
    expect($categories[0]['color'])->toBe('#123456');
});

it('toggles the active state of a category', function () {
    SiteSetting::setValue('marker_categories', [
        ['key' => 'cafe', 'label' => 'Cafe', 'color' => '#ff0000', 'icon_path' => null, 'is_active' => true],
    ]);

    Livewire::test('superadmin::pages.map-marker.manage-marker-categories')
        ->call('toggleActive', 0);

    $categories = SiteSetting::getValue('marker_categories', []);

    expect($categories[0]['is_active'])->toBeFalse();
});

it('removes a category and persists', function () {
    SiteSetting::setValue('marker_categories', [
        ['key' => 'cafe', 'label' => 'Cafe', 'color' => '#ff0000', 'icon_path' => null],
        ['key' => 'restaurant', 'label' => 'Restaurant', 'color' => '#00ff00', 'icon_path' => null],
    ]);

    Livewire::test('superadmin::pages.map-marker.manage-marker-categories')
        ->call('removeCategory', 0);

    $categories = SiteSetting::getValue('marker_categories', []);

    expect($categories)->toHaveCount(1);
    expect($categories[0]['key'])->toBe('restaurant');
});