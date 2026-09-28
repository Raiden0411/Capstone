<?php

namespace App\Services;

use App\Mail\BusinessApplicationApproved;
use App\Mail\BusinessApplicationNeedsRevision;
use App\Mail\BusinessApplicationRejected;
use App\Mail\BusinessApplicationSubmitted;
use App\Models\BusinessApplication;
use App\Models\BusinessDocument;
use App\Models\BusinessMembership;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Traits\HandlesImageUploads;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class BusinessApplicationService
{
    use HandlesImageUploads;

    public function __construct(
        protected KybVerificationService $kyb,
        protected DocumentWatermarkService $watermark,
        protected SuperadminNotificationService $notifications,
    ) {}

    public function attachDocument(
        BusinessApplication $application,
        User $user,
        string $documentType,
        UploadedFile $uploadedFile,
        array $extra = []
    ): BusinessDocument {
        $storedPath  = null;
        $watermarked = null;

        try {
            $originalFilename = 'document';
            try {
                $originalFilename = (string) $uploadedFile->getClientOriginalName();
            } catch (\Throwable) {
                // fall back to generic name
            }

            $storedPath = $this->storeImage(
                $uploadedFile,
                "kyb-documents/{$application->id}",
                'public',
                'kyb-document',
            );

            if ($storedPath === null) {
                throw new RuntimeException('Failed to store the uploaded file.');
            }

            $disk           = Storage::disk('public');
            $storedFullPath = $disk->path($storedPath);

            if (!is_file($storedFullPath)) {
                throw new RuntimeException('Stored file is not readable at ' . $storedFullPath);
            }

            $mime = 'application/octet-stream';
            if (function_exists('mime_content_type')) {
                $detected = @mime_content_type($storedFullPath);
                if (is_string($detected) && $detected !== '') {
                    $mime = $detected;
                }
            }

            $fileSize = (int) @filesize($storedFullPath);

            $fileHash = @hash_file('sha256', $storedFullPath);
            if (!is_string($fileHash)) {
                $fileHash = null;
            }

            $watermarked = $this->watermark->watermark(
                $storedPath,
                config('app.name', 'Victorias Tourism')
            );

            return DB::transaction(function () use (
                $application,
                $user,
                $documentType,
                $extra,
                $originalFilename,
                $mime,
                $storedPath,
                $watermarked,
                $fileSize,
                $fileHash
            ) {
                return BusinessDocument::create([
                    'business_application_id' => $application->id,
                    'user_id'                 => $user->id,
                    'document_type'           => $documentType,
                    'original_filename'       => $originalFilename,
                    'stored_path'             => $storedPath,
                    'watermarked_path'        => $watermarked,
                    'mime_type'               => $mime,
                    'file_size'               => $fileSize,
                    'file_hash'               => $fileHash,
                    'document_number'         => $extra['document_number'] ?? null,
                    'issued_at'               => $extra['issued_at']       ?? null,
                    'expires_at'              => $extra['expires_at']      ?? null,
                    'verification_status'     => BusinessDocument::STATUS_PENDING,
                    'watermarked_at'          => $watermarked ? now() : null,
                ]);
            });
        } catch (\Throwable $e) {
            if ($storedPath && Storage::disk('public')->exists($storedPath)) {
                Storage::disk('public')->delete($storedPath);
            }
            if ($watermarked && Storage::disk('public')->exists($watermarked)) {
                Storage::disk('public')->delete($watermarked);
            }

            Log::error('KYB attachDocument failed', [
                'application_id' => $application->id,
                'document_type'  => $documentType,
                'error'          => $e->getMessage(),
                'exception'      => get_class($e),
                'file'           => $e->getFile(),
                'line'           => $e->getLine(),
            ]);

            throw $e;
        }
    }

    public function submit(BusinessApplication $application): bool
    {
        $application->loadMissing('documents');

        if (!$application->isReadyForSubmission()) {
            throw new RuntimeException(
                'Please complete all required business details and upload every required document before submitting.'
            );
        }

        $this->assertUniqueBusinessIdentifiers($application);

        DB::transaction(function () use ($application): void {
            $application->update([
                'status'       => BusinessApplication::STATUS_PENDING,
                'submitted_at' => now(),
            ]);
        });

        $this->notifications->flush();

        $this->kyb->verify($application->fresh(['documents']));

        $fresh = $application->fresh(['user', 'documents']);

        $this->safeMail(function () use ($fresh) {
            /** @var BusinessApplication $fresh */
            /** @var User|null $applicant */
            $applicant = $fresh->user;
            $to = $fresh->contact_email ?: $applicant?->email;
            if ($to) {
                Mail::to($to)->send(new BusinessApplicationSubmitted($fresh));
            }
        }, 'submit-applicant', $fresh->id);

        $this->safeMail(function () use ($fresh) {
            /** @var EloquentCollection<int, User> $admins */
            $admins = User::role('super-admin')->get(['id', 'name', 'email']);
            foreach ($admins as $admin) {
                Mail::to($admin->email)->send(
                    new BusinessApplicationSubmitted($fresh, forAdmin: true)
                );
            }
        }, 'submit-admin', $fresh->id);

        return true;
    }

    public function approve(BusinessApplication $application, User $reviewer): Tenant
    {
        $tenant = DB::transaction(function () use ($application, $reviewer) {
            /** @var BusinessApplication|null $locked */
            $locked = BusinessApplication::query()
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                throw new RuntimeException('Application not found.');
            }

            if (!in_array($locked->status, [
                BusinessApplication::STATUS_PENDING,
                BusinessApplication::STATUS_UNDER_REVIEW,
            ], true)) {
                throw new RuntimeException('This application is no longer reviewable.');
            }

            $locked->loadMissing('documents');

            if (!$locked->hasAllRequiredDocuments()) {
                throw new RuntimeException(
                    'This application does not have all required documents on file. '
                  . 'Request a revision instead of approving.'
                );
            }

            $slug = $this->uniqueSlug($locked->business_name ?? 'business');

            /** @var BusinessDocument|null $permitDoc */
            $permitDoc = $locked->documents
                ->firstWhere('document_type', BusinessDocument::TYPE_MAYORS_PERMIT);

            $tenant = Tenant::create([
                'name'              => $locked->business_name,
                'slug'              => $slug,
                'type_of_tenant_id' => $locked->type_of_tenant_id,
                'address'           => $locked->address ?? '',
                'barangay'          => $locked->barangay,
                'email'             => $locked->contact_email,
                'contact_number'    => $locked->contact_phone,
                'coordinates'       => $locked->coordinates,
                'logo'              => $locked->logo_path,
                'is_active'         => true,
                'is_recommended'    => false,
                'verified_at'       => now(),
                'permit_expires_at' => $permitDoc?->expires_at,
            ]);

            TenantSetting::create([
                'tenant_id' => $tenant->id,
                'key'       => 'business_info',
                'value'     => [
                    'description'   => $locked->metadata['description']   ?? null,
                    'opening_hours' => $locked->metadata['opening_hours'] ?? null,
                    'barangay'      => $locked->barangay,
                    'city'          => $locked->city,
                    'province'      => $locked->province,
                ],
            ]);

            /** @var User|null $applicant */
            $applicant = $locked->user;

            if ($applicant) {
                // ── 1a: durable pivot row ────────────────────────
                //
                // This is the AUTHORITATIVE record that this user is
                // an owner of this business. `users.tenant_id` below
                // is the ACTIVE pointer — separate concern.
                //
                // is_active is true ONLY when this is the applicant's
                // first business. An existing owner approving a second
                // business does NOT get their active context switched —
                // they keep operating wherever they were.
                $isFirstBusiness = $applicant->businessMemberships()->count() === 0;

                BusinessMembership::create([
                    'user_id'   => $applicant->id,
                    'tenant_id' => $tenant->id,
                    'role'      => BusinessMembership::ROLE_OWNER,
                    'is_active' => $isFirstBusiness,
                    'joined_at' => now(),
                ]);

                // ── 1a: preserve the existing owner's active tenant ──
                $updates = [];

                if ($locked->owner_avatar_path) {
                    $updates['avatar'] = $locked->owner_avatar_path;
                }

                if (! $applicant->tenant_id) {
                    // First business — light up the active pointer so
                    // every existing SFC that reads `user->tenant_id`
                    // sees the new business immediately.
                    $updates['tenant_id']   = $tenant->id;
                    $updates['active_mode'] = User::MODE_BUSINESS;
                }

                if (! empty($updates)) {
                    $applicant->update($updates);
                }

                // ── Spatie role ──────────────────────────────────
                // Pre-1b: global role assignment. 1b replaces this with
                // a team-scoped assignment keyed on $tenant->id.
                if (! $applicant->hasRole('admin')) {
                    $applicant->assignRole('admin');
                }
            }

            $locked->update([
                'status'             => BusinessApplication::STATUS_APPROVED,
                'approved_tenant_id' => $tenant->id,
                'reviewed_at'        => now(),
                'reviewed_by'        => $reviewer->id,
                'rejection_reason'   => null,
            ]);

            $locked->documents()->update([
                'verification_status' => BusinessDocument::STATUS_VERIFIED,
            ]);

            return $tenant;
        });

        $this->notifications->flush();

        $fresh = $application->fresh(['user']);

        $this->safeMail(function () use ($fresh, $tenant) {
            /** @var BusinessApplication $fresh */
            /** @var User|null $applicant */
            $applicant = $fresh->user;
            $to = $fresh->contact_email ?: $applicant?->email;
            if ($to) {
                Mail::to($to)->send(new BusinessApplicationApproved($fresh, $tenant));
            }
        }, 'approve-applicant', $fresh->id);

        return $tenant;
    }

    public function reject(BusinessApplication $application, User $reviewer, string $reason): void
    {
        DB::transaction(function () use ($application, $reviewer, $reason): void {
            $application->update([
                'status'           => BusinessApplication::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'reviewed_at'      => now(),
                'reviewed_by'      => $reviewer->id,
            ]);
        });

        $this->notifications->flush();

        $fresh = $application->fresh(['user']);

        $this->safeMail(function () use ($fresh, $reason) {
            /** @var BusinessApplication $fresh */
            /** @var User|null $applicant */
            $applicant = $fresh->user;
            $to = $fresh->contact_email ?: $applicant?->email;
            if ($to) {
                Mail::to($to)->send(new BusinessApplicationRejected($fresh, $reason));
            }
        }, 'reject-applicant', $fresh->id);
    }

    public function requestRevision(BusinessApplication $application, User $reviewer, string $notes): void
    {
        DB::transaction(function () use ($application, $reviewer, $notes): void {
            $application->update([
                'status'         => BusinessApplication::STATUS_NEEDS_REVISION,
                'revision_notes' => $notes,
                'reviewed_at'    => now(),
                'reviewed_by'    => $reviewer->id,
            ]);
        });

        $this->notifications->flush();

        $fresh = $application->fresh(['user']);

        $this->safeMail(function () use ($fresh, $notes) {
            /** @var BusinessApplication $fresh */
            /** @var User|null $applicant */
            $applicant = $fresh->user;
            $to = $fresh->contact_email ?: $applicant?->email;
            if ($to) {
                Mail::to($to)->send(new BusinessApplicationNeedsRevision($fresh, $notes));
            }
        }, 'revision-applicant', $fresh->id);
    }

    protected function safeMail(callable $callback, string $context, int $applicationId): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::warning('KYB mail dispatch failed', [
                'context'        => $context,
                'application_id' => $applicationId,
                'error'          => $e->getMessage(),
                'exception'      => get_class($e),
            ]);
        }
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'business';
        $slug = $base;
        $i    = 2;

        while (DB::table('tenants')->where('slug', '=', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    protected function assertUniqueBusinessIdentifiers(BusinessApplication $application): void
    {
        $tinCanonical = $application->tin_canonical;
        $regCanonical = $application->business_registration_number_canonical;

        if (!$tinCanonical && !$regCanonical) {
            return;
        }

        $liveStatuses = [
            BusinessApplication::STATUS_PENDING,
            BusinessApplication::STATUS_UNDER_REVIEW,
            BusinessApplication::STATUS_APPROVED,
        ];

        /** @var BusinessApplication|null $collision */
        $collision = BusinessApplication::query()
            ->whereKeyNot($application->getKey())
            ->whereIn('status', $liveStatuses)
            ->where(function ($q) use ($tinCanonical, $regCanonical): void {
                if ($tinCanonical) {
                    $q->orWhere('tin_canonical', $tinCanonical);
                }
                if ($regCanonical) {
                    $q->orWhere('business_registration_number_canonical', $regCanonical);
                }
            })
            ->first(['id', 'tin_canonical', 'business_registration_number_canonical']);

        if (!$collision) {
            return;
        }

        $tinTaken = $tinCanonical && $collision->tin_canonical === $tinCanonical;
        $regTaken = $regCanonical && $collision->business_registration_number_canonical === $regCanonical;

        $field = match (true) {
            $tinTaken && $regTaken => 'TIN and Registration Number',
            $tinTaken              => 'TIN',
            $regTaken              => 'Registration Number',
            default                => 'business identifier',
        };

        throw new RuntimeException(
            "A business with this {$field} is already registered on the platform. "
          . 'If you believe this is a mistake, please contact support.'
        );
    }
}