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

    /**
     * Entry point for the business-application flow.
     *
     * Behaviour:
     *   • Existing DRAFT or NEEDS_REVISION app   → resume it
     *   • Existing PENDING / UNDER_REVIEW app    → reject with an error
     *   • No live app                            → create a new draft
     *
     * The PENDING/UNDER_REVIEW branch is the one-at-a-time guard: a
     * user cannot have two applications in the review pipeline. They
     * can, however, abandon a draft and start a fresh one — that is
     * the DRAFT branch.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        abort_if(! $user, 403);

        // ── Guard: block if a submission is already under review ──
        $hasLiveApplication = $user->businessApplications()
            ->whereIn('status', [
                BusinessApplication::STATUS_PENDING,
                BusinessApplication::STATUS_UNDER_REVIEW,
            ])
            ->exists();

        if ($hasLiveApplication) {
            return redirect()
                ->route('profile')
                ->with('error', 'You already have an application awaiting review. '
                              . 'Wait for a decision before starting another.');
        }

        // ── Resume an editable draft if one exists ────────────────
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

        $replacedPaths = [];

        try {
            DB::transaction(function () use ($application, $validated, $request, &$replacedPaths): void {
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