<?php

namespace Tests\Feature\Business;

use App\Models\BusinessMembership;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Pivot inserts go directly into model_has_roles — NOT through
 * $user->assignRole(). The User override pins team context to
 * $user->tenant_id, which would fight the test's intent to write
 * pivots at two different teams. Direct inserts are unambiguous.
 */
function giveRoleAtTeam(User $user, int $teamId, string $roleName): void
{
    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

    DB::table('model_has_roles')->insertOrIgnore([
        'role_id'    => $role->id,
        'model_type' => User::class,
        'model_id'   => $user->id,
        'team_id'    => $teamId,
    ]);
}

function makeOwnerWithTwoBusinesses(): array
{
    $owner = User::factory()->create();

    $a = Tenant::factory()->create(['name' => 'Alpha Resort', 'slug' => 'alpha-resort']);
    $b = Tenant::factory()->create(['name' => 'Beta Tours',   'slug' => 'beta-tours']);

    BusinessMembership::create([
        'user_id'   => $owner->id,
        'tenant_id' => $a->id,
        'role'      => BusinessMembership::ROLE_OWNER,
        'is_active' => true,
        'joined_at' => now(),
    ]);

    BusinessMembership::create([
        'user_id'   => $owner->id,
        'tenant_id' => $b->id,
        'role'      => BusinessMembership::ROLE_OWNER,
        'is_active' => false,
        'joined_at' => now(),
    ]);

    // Admin role at both teams
    giveRoleAtTeam($owner, $a->id, 'admin');
    giveRoleAtTeam($owner, $b->id, 'admin');

    // Active pointer starts at A
    $owner->update(['tenant_id' => $a->id]);

    return [$owner, $a, $b];
}

it('owner can switch to another business they own', function () {
    [$owner, $a, $b] = makeOwnerWithTwoBusinesses();

    $this->actingAs($owner->fresh())
        ->post(route('tenant.businesses.switch', $b->id))
        ->assertRedirect(route('tenant.dashboard'));

    expect((int) $owner->fresh()->tenant_id)->toBe((int) $b->id);

    $this->assertDatabaseHas('business_memberships', [
        'user_id'   => $owner->id,
        'tenant_id' => $b->id,
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('business_memberships', [
        'user_id'   => $owner->id,
        'tenant_id' => $a->id,
        'is_active' => false,
    ]);
});

it('owner cannot switch to a business they do not belong to', function () {
    [$owner, $a] = makeOwnerWithTwoBusinesses();

    $other = Tenant::factory()->create(['name' => 'Not Mine', 'slug' => 'not-mine']);

    $this->actingAs($owner->fresh())
        ->from(route('tenant.businesses.index'))
        ->post(route('tenant.businesses.switch', $other->id))
        ->assertRedirect(route('tenant.businesses.index'))
        ->assertSessionHas('error');

    expect((int) $owner->fresh()->tenant_id)->toBe((int) $a->id);
});

it('employee cannot reach the switch endpoint', function () {
    $tenant = Tenant::factory()->create(['name' => 'Employer Co', 'slug' => 'employer-co']);

    $employee = User::factory()->create(['tenant_id' => $tenant->id]);

    BusinessMembership::create([
        'user_id'   => $employee->id,
        'tenant_id' => $tenant->id,
        'role'      => BusinessMembership::ROLE_EMPLOYEE,
        'is_active' => true,
        'joined_at' => now(),
    ]);

    // No admin role for the employee — middleware `role:admin|super-admin`
    // rejects the request before it reaches the controller.
    $other = Tenant::factory()->create(['name' => 'Somewhere Else', 'slug' => 'somewhere-else']);

    $this->actingAs($employee)
        ->post(route('tenant.businesses.switch', $other->id))
        ->assertForbidden();

    expect((int) $employee->fresh()->tenant_id)->toBe((int) $tenant->id);
});

it('super-admin cannot reach the switch endpoint through the tenant group', function () {
    $super = User::factory()->create();
    giveRoleAtTeam($super, 0, 'super-admin');

    $other = Tenant::factory()->create(['name' => 'Any Co', 'slug' => 'any-co']);

    // Super-admin has no tenant_id → IsTenantAdmin middleware bails
    // with a 403 before the request reaches the controller.
    $this->actingAs($super)
        ->post(route('tenant.businesses.switch', $other->id))
        ->assertForbidden();
});

it('switching to the current business is a no-op', function () {
    [$owner, $a] = makeOwnerWithTwoBusinesses();

    $this->actingAs($owner->fresh())
        ->post(route('tenant.businesses.switch', $a->id))
        ->assertRedirect(route('tenant.dashboard'));

    expect((int) $owner->fresh()->tenant_id)->toBe((int) $a->id);

    $this->assertDatabaseHas('business_memberships', [
        'user_id'   => $owner->id,
        'tenant_id' => $a->id,
        'is_active' => true,
    ]);
});

it('unauthenticated users are redirected to login', function () {
    $tenant = Tenant::factory()->create(['name' => 'Ghost Co', 'slug' => 'ghost-co']);

    $this->post(route('tenant.businesses.switch', $tenant->id))
        ->assertRedirect(route('login'));
});