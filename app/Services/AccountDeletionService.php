<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AccountDeletionService
{
    public function deleteTouristAccount(User $user, bool $force = false): void
    {
        if ($user->hasRole('super-admin')) {
            throw new RuntimeException(
                'Super-admin accounts cannot be self-deleted. Contact the platform owner.'
            );
        }

        if ($user->isBusinessOwner()) {
            throw new RuntimeException(
                'This account is a business owner. Business deletion requires superadmin approval.'
            );
        }

        if (! $force) {
            $this->assertNoActiveBookings($user);
        }

        $userId = $user->id;

        DB::transaction(function () use ($user, $userId): void {
            $this->cleanupUserFiles($user);
            $this->cleanupUserSessions($user);
            $this->cleanupPasswordResetTokens($user);
            $this->cleanupPersonalAccessTokens($user);
            $this->cleanupEmployeeAvatarFiles($user);

            User::destroy($userId);
        });
    }

    public function deleteBusinessOnly(User $user, bool $force = false): void
    {
        if (! $user->tenant_id) {
            throw new RuntimeException('This user has no business account to delete.');
        }

        $tenant = Tenant::query()->find($user->tenant_id);

        if (! $force && $tenant) {
            $this->assertNoActiveBookingsForTenant($tenant);
        }

        $tenantId = $tenant?->id;

        DB::transaction(function () use ($user, $tenant, $tenantId): void {
            $user->update(['tenant_id' => null]);

            if ($tenant && $tenantId) {
                $this->cleanupTenantFiles($tenant);
                $this->cleanupTenantRecords($tenant);
                Tenant::destroy($tenantId);
            }

            $this->detachBusinessRole($user);
        });
    }

    public function deleteBusinessAndUser(User $user, bool $force = false): void
    {
        if (! $user->tenant_id) {
            $this->deleteTouristAccount($user, $force);
            return;
        }

        $tenant = Tenant::query()->find($user->tenant_id);

        if (! $force) {
            $this->assertNoActiveBookings($user);
            if ($tenant) {
                $this->assertNoActiveBookingsForTenant($tenant);
            }
        }

        $userId   = $user->id;
        $tenantId = $tenant?->id;

        DB::transaction(function () use ($user, $tenant, $userId, $tenantId): void {
            $user->update(['tenant_id' => null]);

            if ($tenant && $tenantId) {
                $this->cleanupTenantFiles($tenant);
                $this->cleanupTenantRecords($tenant);
                Tenant::destroy($tenantId);
            }

            $this->cleanupUserFiles($user);
            $this->cleanupUserSessions($user);
            $this->cleanupPasswordResetTokens($user);
            $this->cleanupPersonalAccessTokens($user);
            $this->cleanupEmployeeAvatarFiles($user);

            User::destroy($userId);
        });
    }

    // ═══════════════════════════════════════════════════════
    // Guards
    // ═══════════════════════════════════════════════════════

    protected function assertNoActiveBookings(User $user): void
    {
        $count = $user->bookings()
            ->whereNotIn('status', Booking::RELEASING_STATUSES)
            ->count();

        if ($count > 0) {
            throw new RuntimeException(
                "You have {$count} active booking(s). Please cancel or complete them before deleting your account."
            );
        }
    }

    protected function assertNoActiveBookingsForTenant(Tenant $tenant): void
    {
        $count = $tenant->bookings()
            ->whereNotIn('status', Booking::RELEASING_STATUSES)
            ->count();

        if ($count > 0) {
            throw new RuntimeException(
                "Your business has {$count} active booking(s). Please resolve them before deleting the business."
            );
        }
    }

    // ═══════════════════════════════════════════════════════
    // File cleanup
    // ═══════════════════════════════════════════════════════

    protected function cleanupUserFiles(User $user): void
    {
        $disk = Storage::disk('public');

        if ($user->avatar && $disk->exists($user->avatar)) {
            $disk->delete($user->avatar);
        }

        // KYB documents uploaded by this user.
        $documents = $user->businessDocuments()->get();
        foreach ($documents as $doc) {
            if ($doc->stored_path && $disk->exists($doc->stored_path)) {
                $disk->delete($doc->stored_path);
            }
            if ($doc->watermarked_path && $disk->exists($doc->watermarked_path)) {
                $disk->delete($doc->watermarked_path);
            }
        }

        // KYB application assets — logo, cover, owner photo.
        $applications = $user->businessApplications()->get();
        foreach ($applications as $app) {
            foreach (['logo_path', 'cover_photo_path', 'owner_avatar_path'] as $field) {
                $path = $app->{$field} ?? null;
                if (is_string($path) && $path !== '' && $disk->exists($path)) {
                    $disk->delete($path);
                }
            }
        }
    }

    protected function cleanupTenantFiles(Tenant $tenant): void
    {
        $disk = Storage::disk('public');

        if ($tenant->logo && $disk->exists($tenant->logo)) {
            $disk->delete($tenant->logo);
        }

        // Property images.
        $properties = $tenant->properties()->with('images')->get();
        foreach ($properties as $property) {
            foreach ($property->images as $img) {
                if ($img->image_path && $disk->exists($img->image_path)) {
                    $disk->delete($img->image_path);
                }
            }
        }

        // Tenant cover photo stored as a TenantSetting value.
        /** @var TenantSetting|null $setting */
        $setting = $tenant->settings()
            ->where('key', 'spot_cover')
            ->first();

        if ($setting && is_array($setting->value)) {
            foreach (['cover_photo_path', 'photo_path', 'path'] as $key) {
                $path = $setting->value[$key] ?? null;
                if (is_string($path) && $path !== '' && $disk->exists($path)) {
                    $disk->delete($path);
                }
            }
        }

        // Event images. Explicit scope bypass — do not depend on ambient
        // auth state to return the events.
        $events = Event::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->get();

        foreach ($events as $event) {
            if ($event->image_path && $disk->exists($event->image_path)) {
                $disk->delete($event->image_path);
            }
        }
    }

    // ═══════════════════════════════════════════════════════
    // Row cleanup
    // ═══════════════════════════════════════════════════════
    //
    // The schema declares cascadeOnDelete on every tenant FK, but on the
    // current live database the events.tenant_id FK does not cascade —
    // orphaned rows were observed after Tenant::destroy(). We delete
    // explicitly inside the same transaction so the result is deterministic
    // regardless of the underlying constraint state.
    //
    // If more orphaned tables are found, prefer fixing the FK via migration
    // over growing this list forever. See the optional migration block at
    // the bottom of the accompanying message.

    protected function cleanupTenantRecords(Tenant $tenant): void
    {
        Event::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->delete();
    }

    // ═══════════════════════════════════════════════════════
    // Non-cascading table cleanups
    // ═══════════════════════════════════════════════════════

    protected function cleanupUserSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    protected function cleanupPasswordResetTokens(User $user): void
    {
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
    }

    protected function cleanupPersonalAccessTokens(User $user): void
    {
        if (DB::getSchemaBuilder()->hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', $user->getMorphClass())
                ->where('tokenable_id', $user->id)
                ->delete();
        }
    }

    protected function cleanupEmployeeAvatarFiles(User $user): void
    {
        $disk = Storage::disk('public');

        /** @var Employee|null $employee */
        $employee = $user->employee;
        if ($employee && $employee->avatar && $disk->exists($employee->avatar)) {
            $disk->delete($employee->avatar);
        }
    }

    protected function detachBusinessRole(User $user): void
    {
        if ($user->hasRole('admin')) {
            $user->removeRole('admin');
        }

        if ($user->active_mode === User::MODE_BUSINESS) {
            $user->update(['active_mode' => User::MODE_TOURIST]);
        }
    }
}