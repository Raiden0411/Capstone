<?php

namespace Tests\Feature;

use App\Models\BusinessApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BusinessApplication model behavior.
 *
 * The model derives two canonical fields on save — tin_canonical and
 * business_registration_number_canonical — used to detect duplicate
 * businesses without relying on user-formatted input. The scopes
 * let the review queue filter without ad-hoc where() chains.
 */
class BusinessApplicationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_tin_canonical_is_derived_on_save(): void
    {
        $user = User::factory()->create();

        $application = BusinessApplication::create([
            'user_id'    => $user->id,
            'status'     => BusinessApplication::STATUS_DRAFT,
            'tin_number' => '123-456-789-000',
        ]);

        $this->assertSame('123456789000', $application->fresh()->tin_canonical);
    }

    public function test_registration_number_canonical_is_uppercased_and_stripped(): void
    {
        $user = User::factory()->create();

        $application = BusinessApplication::create([
            'user_id'                      => $user->id,
            'status'                       => BusinessApplication::STATUS_DRAFT,
            'business_registration_number' => 'cs 2024 1234567',
        ]);

        $this->assertSame(
            'CS20241234567',
            $application->fresh()->business_registration_number_canonical
        );
    }

    public function test_pending_review_scope_covers_pending_and_under_review(): void
    {
        $user = User::factory()->create();

        BusinessApplication::create([
            'user_id' => $user->id,
            'status'  => BusinessApplication::STATUS_PENDING,
        ]);
        BusinessApplication::create([
            'user_id' => $user->id,
            'status'  => BusinessApplication::STATUS_UNDER_REVIEW,
        ]);
        BusinessApplication::create([
            'user_id' => $user->id,
            'status'  => BusinessApplication::STATUS_DRAFT,
        ]);

        $this->assertSame(2, BusinessApplication::pendingReview()->count());
    }
}