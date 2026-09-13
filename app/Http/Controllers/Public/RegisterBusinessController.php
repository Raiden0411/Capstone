<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\BusinessApplication;
use App\Services\BusinessApplicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        abort_if(!$user, 403);

        $application = $user->businessApplications()
            ->whereIn('status', [
                BusinessApplication::STATUS_DRAFT,
                BusinessApplication::STATUS_NEEDS_REVISION,
            ])
            ->latest()
            ->first();

        if (!$application) {
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

        // Normalize exactly like the SFC's payloadForStep(1).
        $validated['business_name'] = trim($validated['business_name']);

        if (!empty($validated['business_registration_number'])) {
            $validated['business_registration_number'] = strtoupper(trim($validated['business_registration_number']));
        } else {
            $validated['business_registration_number'] = null;
        }

        if (!empty($validated['tin_number'])) {
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

        try {
            DB::transaction(function () use ($application, $validated, $request): void {
                // Delete previous versions of this doc type first.
                $application->documents()
                    ->ofType($validated['document_type'])
                    ->get()
                    ->each(fn ($doc) => $doc->delete());

                $this->service->attachDocument(
                    $application,
                    Auth::user(),
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