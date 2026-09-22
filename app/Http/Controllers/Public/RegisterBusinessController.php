<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\BusinessApplication;
use App\Services\BusinessApplicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class RegisterBusinessController extends Controller
{
    public function __construct(
        protected BusinessApplicationService $service,
    ) {}

    public function store(Request $request)
    {
        $user = Auth::user();
        abort_if(! $user, 403);

        /** @var BusinessApplication|null $application */
        $application = $user->businessApplications()
            ->whereIn('status', [
                BusinessApplication::STATUS_DRAFT,
                BusinessApplication::STATUS_NEEDS_REVISION,
            ])
            ->latest()
            ->first();

        if (! $application) {
            $application = BusinessApplication::create([
                'user_id' => $user->id,
                'status'  => BusinessApplication::STATUS_DRAFT,
            ]);
        }

        return redirect()->route('register_business.edit', ['application' => $application->id]);
    }

    public function update(Request $request, BusinessApplication $application)
    {
        $this->authorizeApplication($application);
        abort_unless($application->isEditable(), 400, 'Application cannot be edited.');

        $validated = $request->validate([
            'business_name'                => 'required|string|min:3|max:255',
            'business_type'                => ['required', Rule::in(BusinessApplication::BUSINESS_TYPES)],
            'business_registration_number' => ['nullable', 'string', 'min:4', 'max:50', 'regex:/^[A-Za-z0-9\-]+$/'],
            'tin_number'                   => ['nullable', 'string', 'regex:/^\d{3}[-\s]?\d{3}[-\s]?\d{3}(?:[-\s]?\d{3})?$/'],
            'owner_full_name'              => 'required|string|min:3|max:255',
            'owner_id_type'                => ['nullable', 'string', 'max:50'],
            'owner_id_number'              => 'nullable|string|max:100',
            'contact_email'                => 'required|email|max:255',
            'contact_phone'                => ['required', 'string', 'max:20', 'regex:/^(09|\+639)\d{9}$/'],
            'address'                      => 'nullable|string|max:255',
            'barangay'                     => 'nullable|string|max:255',
            'city'                         => 'nullable|string|max:255',
            'province'                     => 'nullable|string|max:255',
            'type_of_tenant_id'            => 'nullable|exists:type_of_tenants,id',
        ]);

        $validated['business_name'] = trim($validated['business_name']);

        if (! empty($validated['business_registration_number'])) {
            $validated['business_registration_number'] = strtoupper(trim($validated['business_registration_number']));
        } else {
            $validated['business_registration_number'] = null;
        }

        if (! empty($validated['tin_number'])) {
            $validated['tin_number'] = trim($validated['tin_number']);
        } else {
            $validated['tin_number'] = null;
        }

        $application->update($validated);

        return back()->with('message', 'Application saved.');
    }

    public function uploadDocument(Request $request, BusinessApplication $application)
    {
        $this->authorizeApplication($application);
        abort_unless($application->isEditable(), 400, 'Application cannot be edited.');

        $validated = $request->validate([
            'document_type'   => ['required', Rule::in(array_merge(
                BusinessApplication::REQUIRED_DOCUMENTS,
                BusinessApplication::OPTIONAL_DOCUMENTS
            ))],
            'file'            => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'document_number' => 'nullable|string|max:100',
            'issued_at'       => 'nullable|date',
            'expires_at'      => 'nullable|date|after:issued_at',
        ]);

        // Paths of any replaced documents, collected inside the transaction
        // and drained AFTER commit. Filesystem operations are not
        // transactional: deleting files inside the transaction would leave
        // a soft-deleted row pointing at a missing file if the transaction
        // rolled back (which it does whenever attachDocument throws — e.g.
        // a storage failure). Deferring to post-commit means we only ever
        // delete files whose replacement actually succeeded.
        $replacedPaths = [];

        try {
            DB::transaction(function () use ($application, $validated, $request, &$replacedPaths): void {
                // Soft-delete previous versions of this doc type. The rows
                // are retained (SoftDeletes on BusinessDocument) so the
                // audit trail shows "document_type X was replaced on date Y"
                // — the model metadata (document_number, issued_at,
                // expires_at, verification_status, timestamps) survives.
                //
                // Uses ->where() instead of ->ofType() so PHPStan can
                // resolve the call chain without a custom scope annotation.
                $application->documents()
                    ->where('document_type', $validated['document_type'])
                    ->get()
                    ->each(function ($doc) use (&$replacedPaths): void {
                        foreach (['stored_path', 'watermarked_path'] as $field) {
                            $path = $doc->{$field} ?? null;
                            if (is_string($path) && $path !== '') {
                                $replacedPaths[] = $path;
                            }
                        }
                        $doc->delete();
                    });

                /** @var \App\Models\User $currentUser */
                $currentUser = Auth::user();

                $this->service->attachDocument(
                    $application,
                    $currentUser,
                    $validated['document_type'],
                    $request->file('file'),
                    [
                        'document_number' => $validated['document_number'] ?? null,
                        'issued_at'       => $validated['issued_at']       ?? null,
                        'expires_at'      => $validated['expires_at']      ?? null,
                    ],
                );
            });
        } catch (Throwable $e) {
            Log::error('KYB document upload failed', [
                'application_id' => $application->id,
                'document_type'  => $validated['document_type'],
                'error'          => $e->getMessage(),
            ]);

            return back()->with('error', 'Document upload failed. Please try again.');
        }

        // Post-commit file cleanup. Runs only if the transaction
        // succeeded. A failure here is logged but does NOT roll back the
        // upload — the new version is already committed to DB + disk; a
        // leftover old file is a bounded disk-usage concern, not a
        // data-integrity one.
        if (!empty($replacedPaths)) {
            $disk = Storage::disk('public');

            foreach ($replacedPaths as $path) {
                if (! $disk->exists($path)) {
                    continue;
                }

                try {
                    $disk->delete($path);
                } catch (Throwable $e) {
                    Log::warning('Failed to delete replaced KYB doc file', [
                        'path'  => $path,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return back()->with('message', 'Document uploaded and watermarked.');
    }

    public function submit(BusinessApplication $application)
    {
        $this->authorizeApplication($application);
        abort_unless($application->isEditable(), 400, 'Application cannot be edited.');

        try {
            $this->service->submit($application);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('profile')
            ->with('message', 'Your business application was submitted for review.');
    }

    protected function authorizeApplication(BusinessApplication $application): void
    {
        abort_unless($application->user_id === Auth::id(), 403);
    }
}