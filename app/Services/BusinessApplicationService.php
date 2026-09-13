<?php

namespace App\Services;

use App\Models\BusinessApplication;
use App\Models\BusinessDocument;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class BusinessApplicationService
{
    public function __construct(
        protected KybVerificationService $kyb,
        protected DocumentWatermarkService $watermark,
    ) {}

    /**
     * Attach an uploaded file, watermark it, and record the document row.
     * File is cleaned up if the DB write fails.
     *
     * All metadata (mime, size, hash) is read from the STORED copy on the
     * public disk — never from the Livewire temp file, which may already
     * have been cleaned up by the time we reach the DB transaction.
     */
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
            // Filename is a client-provided value — usually safe even on
            // a Livewire temp file, but still guard against exotic failures.
            $originalFilename = 'document';
            try {
                $originalFilename = (string) $uploadedFile->getClientOriginalName();
            } catch (\Throwable) {
                // fall back to generic name
            }

            // 1) Store FIRST. This copies the temp file to stable storage.
            $storedPath = $uploadedFile->store("kyb-documents/{$application->id}", 'public');

            if (!$storedPath) {
                throw new RuntimeException('Failed to store the uploaded file.');
            }

            // 2) Everything else reads from the STORED copy — never the temp file.
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
            if ($fileSize < 0) {
                $fileSize = 0;
            }

            $fileHash = @hash_file('sha256', $storedFullPath);
            if (!is_string($fileHash) || $fileHash === '') {
                $fileHash = null;
            }

            // 3) Watermark (best-effort — service returns null on failure).
            $watermarked = $this->watermark->watermark(
                $storedPath,
                config('app.name', 'Victorias Tourism')
            );

            // 4) Persist.
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
            // Clean up any files that were written before the failure.
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

        // Cross-application uniqueness. Throws RuntimeException with a
        // specific message when a collision is found. See the method
        // below for scope and rationale.
        $this->assertUniqueBusinessIdentifiers($application);

        DB::transaction(function () use ($application): void {
            $application->update([
                'status'       => BusinessApplication::STATUS_PENDING,
                'submitted_at' => now(),
            ]);
        });

        $this->kyb->verify($application->fresh(['documents']));

        return true;
    }

    public function approve(BusinessApplication $application, User $reviewer): Tenant
    {
        return DB::transaction(function () use ($application, $reviewer) {
            // Lock + re-read the application to prevent concurrent approvals
            // producing two tenants from one application.
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

            $applicant = $locked->user;
            if ($applicant) {
                $applicant->update([
                    'tenant_id'   => $tenant->id,
                    'active_mode' => User::MODE_BUSINESS,
                ]);

                if (!$applicant->hasRole('admin')) {
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

    /**
     * Refuse submission if this TIN or Registration No. is already
     * attached to a live application (pending / under review / approved).
     *
     * Drafts and rejected applications are intentionally excluded —
     * otherwise a legitimate re-application would be blocked by a
     * stale draft left behind by the same applicant.
     *
     * @throws RuntimeException when a collision is found.
     */
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