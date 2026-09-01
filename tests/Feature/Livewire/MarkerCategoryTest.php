<?php

use App\Models\Tenant;
use App\Models\SiteSetting;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('adds a new marker category and saves it to site settings', function () {
    $tenant = Tenant::factory()->create();

    Livewire::test('superadmin::pages.tenant.edit-tenant', ['tenant' => $tenant])
        ->set('newCategoryKey', 'test-category')
        ->set('newCategoryLabel', 'Test Category')
        ->set('newCategoryColor', '#ff0000')
        ->call('saveNewCategory')
        ->assertHasNoErrors();

    $categories = SiteSetting::getValue('marker_categories', []);

    expect($categories)->toHaveCount(1);
    expect($categories[0]['key'])->toBe('test-category');
    expect($categories[0]['label'])->toBe('Test Category');
    expect($categories[0]['color'])->toBe('#ff0000');
    expect($categories[0]['icon_svg'])->toBeNull();
});