<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TypeOfTenant;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Account deletion guards.
 *
 * The service refuses three categories of caller:
 *   • Super-admins (platform owners can't self-delete).
 *   • Business owners (they must go through the review workflow).
 *   • Users with active bookings (unless force is passed).
 *
 * These guards protect against accidental data loss and are the first
 * line of defence in the deletion-request review flow.
 */
class AccountDeletionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('super-admin', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('tourist', 'web');
    }

    public function test_super_admin_cannot_be_self_deleted(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $service = app(AccountDeletionService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Super-admin accounts cannot be self-deleted');

        $service->deleteTouristAccount($admin);
    }

    public function test_business_owner_cannot_use_the_tourist_deletion_path(): void
    {
        $type = TypeOfTenant::create(['type' => 'Resort']);

        $tenant = Tenant::create([
            'name'              => 'Test Resort',
            'slug'              => 'test-resort',
            'type_of_tenant_id' => $type->id,
            'address'           => '123 Test St',
            'contact_number'    => '09000000000',
            'email'             => 'resort@example.com',
        ]);

        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        $owner->assignRole('admin');

        $service = app(AccountDeletionService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('business owner');

        $service->deleteTouristAccount($owner);
    }

    public function test_plain_tourist_is_deleted_successfully(): void
    {
        $tourist = User::factory()->create(['tenant_id' => null]);
        $tourist->assignRole('tourist');

        $id = $tourist->id;

        $service = app(AccountDeletionService::class);
        $service->deleteTouristAccount($tourist, force: true);

        $this->assertDatabaseMissing('users', ['id' => $id]);
    }
}