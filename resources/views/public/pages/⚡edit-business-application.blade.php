<?php

use App\Models\BusinessApplication;
use App\Models\BusinessDocument;
use App\Models\TypeOfTenant;
use App\Services\BusinessApplicationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Layout('layouts.auth')]
#[Title('Business Application')]
class extends Component
{
    use WithFileUploads;

    public BusinessApplication $application;

    #[Url(keep: true)]
    public int $step = 1;

    public const TOTAL_STEPS = 3;

    // ── Step 1: Business ──
    public string $business_name                = '';
    public string $business_type                = '';
    public string $type_of_tenant_id            = '';
    public string $business_registration_number = '';
    public string $tin_number                   = '';

    // ── Step 2: Owner & Contact ──
    public string $owner_full_name = '';
    public string $owner_id_type   = '';
    public string $owner_id_number = '';
    public string $owner_birthdate = '';
    public string $contact_email   = '';
    public string $contact_phone   = '';

    // ── Step 2: Location ──
    public string $address  = '';
    public string $barangay = '';
    public string $city     = '';
    public string $province = '';

    // ── Step 3: Document uploads ──
    /** @var array<string, mixed> */
    public array $uploads = [];

    public string $openMetaFor = '';

    /** @var array<string, array<string, string>> */
    public array $document_meta = [];

    // ── Feedback ──
    public ?string $saveMessage = null;
    public ?string $saveError   = null;
    public ?string $submitError = null;

    public function mount(BusinessApplication $application): void
    {
        abort_unless($application->user_id === Auth::id(), 403);
        abort_unless($application->isEditable(), 403, 'This application is not editable.');

        $this->application = $application;

        $this->business_name                = (string) ($application->business_name ?? '');
        $this->business_type                = (string) ($application->business_type ?? '');
        $this->type_of_tenant_id            = (string) ($application->type_of_tenant_id ?? '');
        $this->business_registration_number = (string) ($application->business_registration_number ?? '');
        $this->tin_number                   = (string) ($application->tin_number ?? '');

        $this->owner_full_name = (string) ($application->owner_full_name ?? '');
        $this->owner_id_type   = (string) ($application->owner_id_type ?? '');
        $this->owner_id_number = (string) ($application->owner_id_number ?? '');
        $this->owner_birthdate = $application->owner_birthdate?->format('Y-m-d') ?? '';

        $this->contact_email = (string) ($application->contact_email ?? '');
        $this->contact_phone = (string) ($application->contact_phone ?? '');

        $this->address  = (string) ($application->address ?? '');
        $this->barangay = (string) ($application->barangay ?? '');
        $this->city     = (string) ($application->city ?? '');
        $this->province = (string) ($application->province ?? '');

        $this->step = max(1, min(self::TOTAL_STEPS, $this->step));
    }

    // ─────────────────────────────────────────────────────────────
    //  Computed
    // ─────────────────────────────────────────────────────────────

    #[Computed]
    public function tenantTypes()
    {
        return TypeOfTenant::query()->orderBy('type')->get();
    }

    /** @return array<string, string> */
    #[Computed]
    public function ownerIdTypes(): array
    {
        return BusinessApplication::OWNER_ID_TYPES;
    }

    /** @return array<string, string> */
    #[Computed]
    public function businessTypeLabels(): array
    {
        return BusinessApplication::BUSINESS_TYPE_LABELS;
    }

    #[Computed]
    public function registrationNumberPlaceholder(): string
    {
        if ($this->business_type === '') {
            return 'e.g. DTI-2024-123456';
        }

        return BusinessApplication::REGISTRATION_NUMBER_PLACEHOLDERS[$this->business_type]
            ?? 'Enter your registration number';
    }

    #[Computed]
    public function registrationNumberHint(): string
    {
        if ($this->business_type === '') {
            return 'Found on your government registration certificate.';
        }

        return BusinessApplication::REGISTRATION_NUMBER_HINTS[$this->business_type]
            ?? 'Found on your government registration certificate.';
    }

    #[Computed]
    public function documents()
    {
        return $this->application->documents()
            ->orderBy('document_type')
            ->get();
    }

    #[Computed]
    public function documentsByType(): array
    {
        return $this->documents->keyBy('document_type')->all();
    }

    #[Computed]
    public function requiredDocuments(): array
    {
        return BusinessApplication::REQUIRED_DOCUMENTS;
    }

    #[Computed]
    public function optionalDocuments(): array
    {
        return BusinessApplication::OPTIONAL_DOCUMENTS;
    }

    #[Computed]
    public function documentLabels(): array
    {
        return BusinessApplication::DOCUMENT_LABELS;
    }

    #[Computed]
    public function completionPercent(): int
    {
        return $this->application->fresh(['documents'])->completionPercent();
    }

    #[Computed]
    public function isReadyToSubmit(): bool
    {
        return $this->application->fresh(['documents'])->isReadyForSubmission();
    }

    #[Computed]
    public function uploadedRequiredCount(): int
    {
        return $this->documents
            ->whereIn('document_type', $this->requiredDocuments)
            ->count();
    }

    /** @return array<int, string> */
    #[Computed]
    public function missingRequiredDocuments(): array
    {
        $uploaded = $this->documents->pluck('document_type')->unique()->all();
        $missing  = array_diff($this->requiredDocuments, $uploaded);
        $labels   = BusinessApplication::DOCUMENT_LABELS;

        return array_values(array_map(
            fn (string $type) => $labels[$type] ?? $type,
            $missing,
        ));
    }

    #[Computed]
    public function selectedOwnerIdLabel(): ?string
    {
        if ($this->owner_id_type === '') {
            return null;
        }

        return BusinessApplication::OWNER_ID_TYPES[$this->owner_id_type] ?? null;
    }

    // ─────────────────────────────────────────────────────────────
    //  Validation
    // ─────────────────────────────────────────────────────────────

    protected function stepRules(int $step): array
    {
        return match ($step) {
            1 => [
                'business_name'     => ['required', 'string', 'min:3', 'max:255'],
                'business_type'     => ['required', Rule::in(BusinessApplication::BUSINESS_TYPES)],
                'type_of_tenant_id' => ['required', 'integer', 'exists:type_of_tenants,id'],

                'business_registration_number' => [
                    'required',
                    'string',
                    'min:4',
                    'max:50',
                    'regex:/^[A-Za-z0-9\-]+$/',
                ],

                'tin_number' => [
                    'required',
                    'string',
                    'regex:/^\d{3}[-\s]?\d{3}[-\s]?\d{3}(?:[-\s]?\d{3})?$/',
                ],
            ],
            2 => [
                'owner_full_name' => ['required', 'string', 'min:3', 'max:255'],
                'owner_id_type'   => ['required', Rule::in(array_keys(BusinessApplication::OWNER_ID_TYPES))],
                'owner_id_number' => ['required', 'string', 'min:4', 'max:100'],
                'owner_birthdate' => ['nullable', 'date', 'before:today'],
                'contact_email'   => ['required', 'email', 'max:255'],
                'contact_phone'   => ['required', 'string', 'max:20', 'regex:/^(09|\+639)\d{9}$/'],
                'address'         => ['nullable', 'string', 'max:255'],
                'barangay'        => ['nullable', 'string', 'max:255'],
                'city'            => ['nullable', 'string', 'max:255'],
                'province'        => ['nullable', 'string', 'max:255'],
            ],
            default => [],
        };
    }

    protected function rules(): array
    {
        return array_merge($this->stepRules(1), $this->stepRules(2));
    }

    protected function messages(): array
    {
        return [
            'business_registration_number.required' => 'Please enter your registration number.',
            'business_registration_number.regex'    => 'Registration numbers can only contain letters, digits, and dashes.',
            'business_registration_number.min'      => 'That registration number looks too short.',
            'tin_number.required'                   => 'Please enter your TIN.',
            'tin_number.regex'                      => 'Enter a valid TIN: 123-456-789 or 123-456-789-000.',
            'contact_phone.regex'                   => 'Use a valid PH number: 09xxxxxxxxx or +639xxxxxxxxx.',
            'type_of_tenant_id.required'            => 'Please select a business category.',
            'business_type.required'                => 'Please select a registration type.',
            'owner_id_type.required'                => 'Please select the type of ID you will upload.',
            'owner_id_type.in'                      => 'That ID type is not accepted. Choose one from the list.',
            'owner_id_number.required'              => 'Please enter the ID number exactly as it appears on your card.',
            'owner_id_number.min'                   => 'That ID number looks too short. Please double-check it.',
        ];
    }

    // ─────────────────────────────────────────────────────────────
    //  Step navigation
    // ─────────────────────────────────────────────────────────────

    public function gotoStep(int $step): void
    {
        $this->step = max(1, min(self::TOTAL_STEPS, $step));
        $this->saveMessage = null;
        $this->saveError   = null;
        $this->submitError = null;

        $this->dispatch('scroll-to-top');
    }

    public function next(): void
    {
        $this->saveMessage = null;
        $this->saveError   = null;

        $currentRules = $this->stepRules($this->step);
        if (!empty($currentRules)) {
            $this->validate($currentRules);
        }

        try {
            $this->application->update($this->payloadForStep($this->step));
        } catch (\Throwable $e) {
            Log::error('KYB step save failed', [
                'application_id' => $this->application->id,
                'step'           => $this->step,
                'error'          => $e->getMessage(),
            ]);
            $this->saveError = 'Could not save your progress. Please try again.';
            return;
        }

        unset($this->completionPercent, $this->isReadyToSubmit, $this->missingRequiredDocuments);

        $this->step = min(self::TOTAL_STEPS, $this->step + 1);
        $this->dispatch('scroll-to-top');
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
        $this->saveMessage = null;
        $this->saveError   = null;
        $this->submitError = null;
        $this->dispatch('scroll-to-top');
    }

    protected function payloadForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'business_name'                => trim($this->business_name),
                'business_type'                => $this->business_type,
                'type_of_tenant_id'            => $this->type_of_tenant_id,
                'business_registration_number' => strtoupper(trim($this->business_registration_number)),
                'tin_number'                   => trim($this->tin_number),
            ],
            2 => [
                'owner_full_name' => trim($this->owner_full_name),
                'owner_id_type'   => $this->owner_id_type ?: null,
                'owner_id_number' => $this->owner_id_number ?: null,
                'owner_birthdate' => $this->owner_birthdate ?: null,
                'contact_email'   => $this->contact_email,
                'contact_phone'   => $this->contact_phone,
                'address'         => $this->address ?: null,
                'barangay'        => $this->barangay ?: null,
                'city'            => $this->city ?: null,
                'province'        => $this->province ?: null,
            ],
            default => [],
        };
    }

    // ─────────────────────────────────────────────────────────────
    //  Document uploads
    // ─────────────────────────────────────────────────────────────

    public function updated(string $name, mixed $value): void
    {
        if (!str_starts_with($name, 'uploads.')) {
            return;
        }

        $documentType = substr($name, strlen('uploads.'));

        if (empty($value)) {
            return;
        }

        $this->attachDocument($documentType);
    }

    public function attachDocument(string $documentType): void
    {
        $allowed = array_merge($this->requiredDocuments, $this->optionalDocuments);
        if (!in_array($documentType, $allowed, true)) {
            unset($this->uploads[$documentType]);
            return;
        }

        $file = $this->uploads[$documentType] ?? null;
        if (!$file) {
            return;
        }

        try {
            $file->getSize();
        } catch (\Throwable) {
            unset($this->uploads[$documentType]);
            $this->dispatch('toast', message: 'Your upload expired. Please select the file again.', type: 'error');
            return;
        }

        try {
            $this->validate([
                "uploads.{$documentType}" => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            ], [
                "uploads.{$documentType}.max"   => 'Documents must be 10 MB or smaller.',
                "uploads.{$documentType}.mimes" => 'Only PDF, JPG, PNG, and WEBP are accepted.',
            ]);
        } catch (\League\Flysystem\UnableToRetrieveMetadata) {
            unset($this->uploads[$documentType]);
            $this->dispatch('toast', message: 'Your upload expired. Please select the file again.', type: 'error');
            return;
        } catch (\Illuminate\Validation\ValidationException $e) {
            unset($this->uploads[$documentType]);
            $first = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', message: $first ?: 'Invalid file.', type: 'error');
            return;
        }

        try {
            $service = app(BusinessApplicationService::class);

            $this->application->documents()
                ->ofType($documentType)
                ->get()
                ->each(fn ($doc) => $doc->delete());

            $service->attachDocument(
                $this->application,
                Auth::user(),
                $documentType,
                $file,
                [
                    'document_number' => null,
                    'issued_at'       => null,
                    'expires_at'      => null,
                ],
            );

            unset($this->uploads[$documentType]);

            unset(
                $this->documents,
                $this->documentsByType,
                $this->completionPercent,
                $this->isReadyToSubmit,
                $this->uploadedRequiredCount,
                $this->missingRequiredDocuments,
            );

            $this->dispatch('toast', message: 'Uploaded and watermarked.', type: 'success');
        } catch (\Throwable $e) {
            Log::error('KYB document upload failed', [
                'application_id' => $this->application->id,
                'document_type'  => $documentType,
                'error'          => $e->getMessage(),
            ]);
            unset($this->uploads[$documentType]);
            $this->dispatch('toast', message: 'Upload failed. Please try again.', type: 'error');
        }
    }

    public function deleteDocument(int $documentId): void
    {
        $doc = $this->application->documents()->whereKey($documentId)->first();
        if (!$doc) {
            return;
        }

        try {
            $doc->delete();

            unset(
                $this->documents,
                $this->documentsByType,
                $this->completionPercent,
                $this->isReadyToSubmit,
                $this->uploadedRequiredCount,
                $this->missingRequiredDocuments,
            );

            $this->dispatch('toast', message: 'Document removed.', type: 'success');
        } catch (\Throwable $e) {
            Log::error('KYB document delete failed', [
                'document_id' => $documentId,
                'error'       => $e->getMessage(),
            ]);
            $this->dispatch('toast', message: 'Could not remove the document.', type: 'error');
        }
    }

    public function toggleMetaPanel(string $documentType): void
    {
        $this->openMetaFor = $this->openMetaFor === $documentType ? '' : $documentType;
    }

    public function mountDocumentMeta(string $documentType): void
    {
        if (isset($this->document_meta[$documentType])) {
            return;
        }

        $doc = $this->documentsByType[$documentType] ?? null;

        $this->document_meta[$documentType] = [
            'document_number' => $doc?->document_number ?? '',
            'issued_at'       => $doc?->issued_at?->format('Y-m-d') ?? '',
            'expires_at'      => $doc?->expires_at?->format('Y-m-d') ?? '',
        ];
    }

    public function saveDocumentMeta(string $documentType): void
    {
        $doc = $this->application->documents()->ofType($documentType)->first();
        if (!$doc) {
            return;
        }

        $input = $this->document_meta[$documentType] ?? [];

        $doc->update([
            'document_number' => $input['document_number'] ?? null,
            'issued_at'       => $input['issued_at']       ?? null,
            'expires_at'      => $input['expires_at']      ?? null,
        ]);

        unset($this->documents, $this->documentsByType);

        $this->openMetaFor = '';
        $this->dispatch('toast', message: 'Details saved.', type: 'success');
    }

    public function documentUrl(BusinessDocument $doc): ?string
    {
        $path = $doc->watermarked_path ?: $doc->stored_path;
        return $path ? asset('storage/' . $path) : null;
    }

    public function isImageDocument(BusinessDocument $doc): bool
    {
        return str_starts_with((string) $doc->mime_type, 'image/');
    }

    // ─────────────────────────────────────────────────────────────
    //  Submit
    // ─────────────────────────────────────────────────────────────

    public function submit(BusinessApplicationService $service)
    {
        $this->submitError = null;

        $this->validate();

        $this->application->refresh();

        if (!$this->application->hasAllRequiredDocuments()) {
            $missing = $this->missingRequiredDocuments;

            $this->submitError = empty($missing)
                ? 'Please upload all required documents before submitting.'
                : 'Please upload the following required document(s) before submitting: '
                    . implode(', ', $missing) . '.';

            $this->step = 3;
            $this->dispatch('scroll-to-top');

            return null;
        }

        try {
            $this->application->update(array_merge(
                $this->payloadForStep(1),
                $this->payloadForStep(2),
            ));
        } catch (\Throwable $e) {
            Log::error('KYB save before submit failed', [
                'application_id' => $this->application->id,
                'error'          => $e->getMessage(),
            ]);
            $this->submitError = 'Could not save your application. Please try again.';
            return null;
        }

        try {
            $service->submit($this->application->fresh(['documents']));
        } catch (\Throwable $e) {
            Log::warning('KYB submit blocked', [
                'application_id' => $this->application->id,
                'error'          => $e->getMessage(),
            ]);
            $this->submitError = $e->getMessage();
            return null;
        }

        session()->flash('message', 'Your business application was submitted for review.');

        return redirect()->route('register_business');
    }
};
?>

@php
    $siteName = \App\Models\SiteSetting::getValue('site_name', config('app.name'));
    $logoPath = \App\Models\SiteSetting::getValue('site_logo');
    $logoUrl  = $logoPath ? asset('storage/' . $logoPath) : null;

    $stepLabels = [
        1 => 'Business',
        2 => 'Owner',
        3 => 'Documents',
    ];

    $isRevision = $application->status === BusinessApplication::STATUS_NEEDS_REVISION
                  && !empty($application->revision_notes);
@endphp

<main
    x-data="{ toasts: [] }"
    x-on:toast.window="
        const id = Date.now() + Math.random();
        toasts.push({ id, message: $event.detail.message, type: $event.detail.type || 'info' });
        setTimeout(() => { toasts = toasts.filter(t => t.id !== id) }, 3500);
    "
    x-on:scroll-to-top.window="window.scrollTo({ top: 0, behavior: 'smooth' })"
    class="min-h-screen bg-gray-50 dark:bg-gray-950">

    {{-- Toast container --}}
    <div class="fixed bottom-4 right-4 z-[2000] flex flex-col gap-2 w-full max-w-sm pointer-events-none">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-transition
                 class="pointer-events-auto rounded-xl px-4 py-3 shadow-lg text-sm font-medium border"
                 :class="{
                     'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-300': toast.type === 'success',
                     'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-500/10 dark:border-rose-500/30 dark:text-rose-300': toast.type === 'error',
                     'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-500/10 dark:border-blue-500/30 dark:text-blue-300': toast.type === 'info',
                 }">
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>

    {{-- Sticky header --}}
    <div class="sticky top-0 z-30 bg-white/90 dark:bg-gray-900/90 backdrop-blur border-b border-gray-200 dark:border-gray-800">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5">

            <div class="flex items-center justify-between gap-4 mb-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $siteName }}" class="w-7 h-7 object-contain rounded-lg shrink-0">
                    @else
                        <div class="w-7 h-7 rounded-lg bg-primary-600 flex items-center justify-center text-white shrink-0 font-semibold text-xs">
                            {{ strtoupper(substr($siteName, 0, 1)) }}
                        </div>
                    @endif
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Step {{ $step }} of {{ count($stepLabels) }}
                        </p>
                        <h1 class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                            {{ $stepLabels[$step] ?? 'Application' }}
                        </h1>
                    </div>
                </div>

                <a href="{{ route('register_business') }}" wire:navigate
                   class="shrink-0 inline-flex items-center gap-1 text-[11px] font-medium text-gray-500 dark:text-gray-400 hover:text-rose-600 dark:hover:text-rose-400 transition-colors rounded px-2 py-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Save &amp; exit
                </a>
            </div>

            <div class="flex items-center gap-2">
                @foreach ($stepLabels as $num => $label)
                    @php
                        $isActive   = $step === $num;
                        $isComplete = $step > $num;
                    @endphp
                    <button type="button"
                            wire:click="gotoStep({{ $num }})"
                            class="flex-1 flex items-center gap-2 group text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded-lg">
                        <span class="shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold transition-all duration-200
                            {{ $isActive
                                ? 'bg-primary-600 text-white ring-4 ring-primary-500/20'
                                : ($isComplete
                                    ? 'bg-emerald-500 text-white'
                                    : 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400 group-hover:bg-gray-300 dark:group-hover:bg-gray-600') }}">
                            @if ($isComplete)
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                </svg>
                            @else
                                {{ $num }}
                            @endif
                        </span>
                        <span class="hidden sm:block text-xs font-medium truncate transition-colors
                            {{ $isActive ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400' }}">
                            {{ $label }}
                        </span>
                    </button>

                    @if (!$loop->last)
                        <div class="h-px flex-1 max-w-[20px] {{ $isComplete ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-gray-700' }}"></div>
                    @endif
                @endforeach
            </div>

            <div class="mt-3 h-0.5 w-full rounded-full bg-gray-200 dark:bg-gray-800 overflow-hidden">
                <div class="h-full bg-primary-600 transition-all duration-500"
                     style="width: {{ $this->completionPercent }}%"></div>
            </div>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">

        {{-- Reviewer's note --}}
        @if ($isRevision)
            <div class="mb-6 rounded-2xl border border-indigo-200/80 dark:border-indigo-500/30 bg-indigo-50/60 dark:bg-indigo-500/[0.06] p-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="shrink-0 w-9 h-9 rounded-full bg-indigo-100 dark:bg-indigo-500/20 border border-indigo-200 dark:border-indigo-500/30 flex items-center justify-center text-indigo-700 dark:text-indigo-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-300 mb-1">
                            Note from your reviewer
                        </p>
                        <p class="text-sm text-indigo-950 dark:text-indigo-100 leading-relaxed italic">
                            “{{ $application->revision_notes }}”
                        </p>
                        <p class="mt-2 text-[11px] text-indigo-700/70 dark:text-indigo-300/70">
                            Fix the item(s) above, then resubmit. Your other information is saved.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Save / submit errors --}}
        @if ($saveError || $submitError)
            <div class="mb-5 flex items-center gap-2.5 rounded-xl border border-rose-200/80 dark:border-rose-500/30 bg-rose-50 dark:bg-rose-500/10 px-3.5 py-2.5 text-xs sm:text-sm text-rose-800 dark:text-rose-300 shadow-sm">
                <svg class="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                </svg>
                <span class="font-medium">{{ $saveError ?: $submitError }}</span>
            </div>
        @endif

        {{-- STEP 1 — BUSINESS --}}
        @if ($step === 1)
            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Business Information
                    </span>
                </div>

                <div class="space-y-5">
                    <div>
                        <label for="business_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Business Name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="business_name" wire:model="business_name"
                               placeholder="e.g. Gawahon Eco Park"
                               maxlength="255"
                               class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                        @error('business_name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                            This is how customers will see your business on the public site.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="business_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Registration Type <span class="text-rose-500">*</span>
                            </label>
                            <select id="business_type" wire:model.live="business_type"
                                    class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                                <option value="">— Select —</option>
                                @foreach($this->businessTypeLabels as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('business_type') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                            <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                                Not sure? Check the certificate you'll upload in Step 3.
                            </p>
                        </div>

                        <div>
                            <label for="type_of_tenant_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Category <span class="text-rose-500">*</span>
                            </label>
                            <select id="type_of_tenant_id" wire:model="type_of_tenant_id"
                                    class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                                <option value="">— Select —</option>
                                @foreach($this->tenantTypes as $type)
                                    <option wire:key="t-{{ $type->id }}" value="{{ $type->id }}">{{ $type->type }}</option>
                                @endforeach
                            </select>
                            @error('type_of_tenant_id') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                            <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                                The type of destination or service you offer.
                            </p>
                        </div>

                        <div>
                            <label for="business_registration_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Registration No. <span class="text-rose-500">*</span>
                            </label>
                            <input type="text"
                                   id="business_registration_number"
                                   wire:model="business_registration_number"
                                   placeholder="{{ $this->registrationNumberPlaceholder }}"
                                   maxlength="50"
                                   autocomplete="off"
                                   x-on:input="
                                       const up = $event.target.value.toUpperCase();
                                       if (up !== $event.target.value) {
                                           $event.target.value = up;
                                           $event.target.dispatchEvent(new Event('input', { bubbles: true }));
                                       }
                                   "
                                   class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition font-mono">
                            @error('business_registration_number') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                            <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                                {{ $this->registrationNumberHint }}
                            </p>
                        </div>

                        <div>
                            <label for="tin_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                TIN <span class="text-rose-500">*</span>
                            </label>
                            <input type="text"
                                   id="tin_number"
                                   wire:model="tin_number"
                                   placeholder="123-456-789-000"
                                   inputmode="numeric"
                                   maxlength="15"
                                   autocomplete="off"
                                   x-on:blur="
                                       const digits = ($event.target.value || '').replace(/\D/g, '').slice(0, 12);
                                       let parts = [];
                                       if (digits.length > 0) parts.push(digits.slice(0, 3));
                                       if (digits.length > 3) parts.push(digits.slice(3, 6));
                                       if (digits.length > 6) parts.push(digits.slice(6, 9));
                                       if (digits.length > 9) parts.push(digits.slice(9, 12));
                                       const formatted = parts.join('-');
                                       if (formatted !== $event.target.value) {
                                           $event.target.value = formatted;
                                           $event.target.dispatchEvent(new Event('input', { bubbles: true }));
                                       }
                                   "
                                   class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition font-mono">
                            @error('tin_number') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                            <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                                Format: <span class="font-mono">123-456-789</span> for individuals or <span class="font-mono">123-456-789-000</span> for businesses. Shown on your BIR Form 2303.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- STEP 2 — OWNER & CONTACT --}}
        @if ($step === 2)
            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Owner
                    </span>
                </div>

                <div class="space-y-5">
                    <div>
                        <label for="owner_full_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Full Name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="owner_full_name" wire:model="owner_full_name"
                               placeholder="Juan dela Cruz"
                               class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                        @error('owner_full_name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label for="owner_id_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Type of Government ID <span class="text-rose-500">*</span>
                            </label>
                            <select id="owner_id_type" wire:model.live="owner_id_type"
                                    class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                                <option value="">— Select an accepted ID —</option>
                                @foreach($this->ownerIdTypes as $key => $label)
                                    <option wire:key="id-{{ $key }}" value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('owner_id_type') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror

                            <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                                You'll upload a photo of this ID on the next step.
                            </p>
                        </div>

                        <div class="sm:col-span-2">
                            <label for="owner_id_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                ID Number <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" id="owner_id_number" wire:model="owner_id_number"
                                   placeholder="Number as printed on your ID"
                                   autocomplete="off"
                                   class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                            @error('owner_id_number') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2 sm:max-w-xs">
                            <label for="owner_birthdate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Date of Birth <span class="text-[10px] font-normal text-gray-400">(optional)</span>
                            </label>
                            <input type="date" id="owner_birthdate" wire:model="owner_birthdate"
                                   class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6 mt-5">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Contact
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="contact_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Email <span class="text-rose-500">*</span>
                        </label>
                        <input type="email" id="contact_email" wire:model="contact_email"
                               class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                        @error('contact_email') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="contact_phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Phone <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="contact_phone" wire:model="contact_phone"
                               placeholder="09xxxxxxxxx"
                               class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                        @error('contact_phone') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6 mt-5">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Location <span class="text-[10px] font-normal normal-case text-gray-400">(optional)</span>
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Street Address
                        </label>
                        <input type="text" id="address" wire:model="address"
                               class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                    </div>

                    <div>
                        <label for="barangay" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Barangay
                        </label>
                        <input type="text" id="barangay" wire:model="barangay"
                               class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                    </div>

                    <div>
                        <label for="city" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            City / Municipality
                        </label>
                        <input type="text" id="city" wire:model="city"
                               class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="province" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Province
                        </label>
                        <input type="text" id="province" wire:model="province"
                               class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                    </div>
                </div>
            </section>
        @endif

        {{-- STEP 3 — DOCUMENTS --}}
        @if ($step === 3)

            <div class="mb-5 flex items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <span>
                    Your uploads are encrypted, watermarked, and only visible to platform reviewers.&nbsp;
                    <span class="text-gray-400 dark:text-gray-500">RA 10173 compliant.</span>
                </span>
            </div>

            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Required Documents
                        </span>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider
                        {{ $this->uploadedRequiredCount === count($this->requiredDocuments)
                            ? 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30'
                            : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700' }}">
                        {{ $this->uploadedRequiredCount }} / {{ count($this->requiredDocuments) }}
                    </span>
                </div>

                <div class="space-y-2.5">
                    @foreach ($this->requiredDocuments as $docType)
                        @php
                            $existing  = $this->documentsByType[$docType] ?? null;
                            $isOwnerId = $docType === BusinessDocument::TYPE_OWNER_ID;
                        @endphp
                        <div wire:key="slot-{{ $docType }}"
                             class="border rounded-xl p-3.5 transition
                                {{ $existing
                                    ? 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/40 dark:bg-emerald-500/[0.04]'
                                    : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900' }}">

                            @if ($existing)
                                <div class="flex items-start gap-3">
                                    @php
                                        $url     = $this->documentUrl($existing);
                                        $isImage = $this->isImageDocument($existing);
                                    @endphp

                                    @if ($isImage && $url)
                                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                           class="block shrink-0 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 hover:border-primary-500 transition">
                                            <img src="{{ $url }}" alt="" loading="lazy" class="w-12 h-12 object-cover">
                                        </a>
                                    @else
                                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                           class="shrink-0 w-12 h-12 rounded-lg bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 flex flex-col items-center justify-center text-rose-600 dark:text-rose-400 hover:border-rose-400 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                            </svg>
                                            <span class="text-[8px] font-bold uppercase mt-0.5">PDF</span>
                                        </a>
                                    @endif

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                                {{ $this->documentLabels[$docType] ?? $docType }}
                                            </p>
                                        </div>
                                        <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                            {{ $existing->original_filename }}
                                        </p>
                                        <div class="mt-1.5 flex items-center gap-3">
                                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                               class="text-[11px] font-semibold text-primary-600 dark:text-primary-400 hover:underline">
                                                View
                                            </a>
                                            <span class="w-px h-3 bg-gray-300 dark:bg-gray-700"></span>
                                            <button type="button"
                                                    wire:click="deleteDocument({{ $existing->id }})"
                                                    wire:confirm="Remove this document?"
                                                    wire:loading.attr="disabled"
                                                    class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 hover:text-rose-600 dark:hover:text-rose-400 transition">
                                                Replace
                                            </button>
                                        </div>
                                    </div>
                                </div>

                            @else
                                <label for="file-{{ $docType }}"
                                       class="flex items-center gap-3 cursor-pointer group">
                                    <div class="shrink-0 w-9 h-9 rounded-lg bg-white dark:bg-gray-800 border border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center text-gray-400 group-hover:border-primary-500 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                            {{ $this->documentLabels[$docType] ?? $docType }}
                                        </p>
                                        @if ($isOwnerId && $this->selectedOwnerIdLabel)
                                            <p class="mt-0.5 inline-flex items-center gap-1 text-[10px] font-semibold text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/30 rounded px-1.5 py-0.5">
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/>
                                                </svg>
                                                Should be your {{ $this->selectedOwnerIdLabel }}
                                            </p>
                                        @endif
                                        <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                                            JPG, PNG, or PDF · max 10 MB
                                        </p>
                                    </div>
                                    <input type="file"
                                           id="file-{{ $docType }}"
                                           wire:model="uploads.{{ $docType }}"
                                           accept=".pdf,.jpg,.jpeg,.png,.webp"
                                           class="sr-only">
                                </label>
                            @endif

                            <div wire:loading wire:target="uploads.{{ $docType }}"
                                 class="mt-2 flex items-center gap-2 text-[11px] text-primary-600 dark:text-primary-400">
                                <svg class="animate-spin w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                Uploading…
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <details class="mt-5 bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm overflow-hidden">
                <summary class="cursor-pointer list-none px-5 py-3.5 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Additional Documents
                        </span>
                    </div>
                    <span class="text-[11px] text-gray-500 dark:text-gray-400">Optional</span>
                </summary>
                <div class="px-5 pb-5 space-y-2.5">
                    @foreach ($this->optionalDocuments as $docType)
                        @php
                            $existing = $this->documentsByType[$docType] ?? null;
                        @endphp
                        <div wire:key="opt-{{ $docType }}"
                             class="border rounded-xl p-3 transition
                                {{ $existing
                                    ? 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/40 dark:bg-emerald-500/[0.04]'
                                    : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900' }}">
                            @if ($existing)
                                <div class="flex items-center gap-3">
                                    <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <p class="flex-1 text-sm font-medium text-gray-900 dark:text-white truncate">
                                        {{ $this->documentLabels[$docType] ?? $docType }}
                                    </p>
                                    <button type="button"
                                            wire:click="deleteDocument({{ $existing->id }})"
                                            wire:confirm="Remove this document?"
                                            class="text-[11px] font-semibold text-gray-500 hover:text-rose-600 transition">
                                        Remove
                                    </button>
                                </div>
                            @else
                                <label for="file-opt-{{ $docType }}" class="flex items-center gap-3 cursor-pointer group">
                                    <div class="shrink-0 w-8 h-8 rounded-lg bg-white dark:bg-gray-800 border border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center text-gray-400 group-hover:border-primary-500 group-hover:text-primary-600 transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </div>
                                    <p class="flex-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ $this->documentLabels[$docType] ?? $docType }}
                                    </p>
                                    <input type="file"
                                           id="file-opt-{{ $docType }}"
                                           wire:model="uploads.{{ $docType }}"
                                           accept=".pdf,.jpg,.jpeg,.png,.webp"
                                           class="sr-only">
                                </label>
                                <div wire:loading wire:target="uploads.{{ $docType }}"
                                     class="mt-2 flex items-center gap-2 text-[11px] text-primary-600 dark:text-primary-400">
                                    <svg class="animate-spin w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    Uploading…
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </details>
        @endif

        {{-- Missing-documents notice --}}
        @if ($step === 3 && !$this->isReadyToSubmit)
            @php
                $missing = $this->missingRequiredDocuments;
                $missingCount = count($missing);
            @endphp
            <div class="mt-5 rounded-xl border border-amber-200/80 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/[0.06] px-3.5 py-3 shadow-sm">
                <div class="flex items-start gap-2.5">
                    <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                    </svg>
                    <div class="min-w-0 text-xs sm:text-sm">
                        <p class="font-semibold text-amber-900 dark:text-amber-200">
                            {{ $missingCount }} required document{{ $missingCount === 1 ? '' : 's' }} still missing
                        </p>
                        <p class="mt-0.5 text-amber-800/80 dark:text-amber-300/80 leading-relaxed">
                            {{ implode(' · ', $missing) }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Navigation bar --}}
        <div class="sticky bottom-4 mt-5 z-20">
            <div class="bg-white/95 dark:bg-gray-800/95 backdrop-blur border border-gray-200 dark:border-gray-700 rounded-2xl shadow-lg p-2.5 flex items-center justify-between gap-3">

                @if ($step > 1)
                    <button type="button"
                            wire:click="back"
                            wire:loading.attr="disabled"
                            wire:target="back,next,submit"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-60">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Back
                    </button>
                @else
                    <div></div>
                @endif

                <div class="hidden sm:flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                    <span class="font-semibold tabular-nums">{{ $step }}</span>
                    <span class="text-gray-300 dark:text-gray-600">/</span>
                    <span class="tabular-nums">{{ count($stepLabels) }}</span>
                </div>

                @if ($step < count($stepLabels))
                    <button type="button"
                            wire:click="next"
                            wire:loading.attr="disabled"
                            wire:target="back,next,submit"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-60">
                        <span wire:loading.remove wire:target="next">Continue</span>
                        <span wire:loading wire:target="next">Saving…</span>
                        <svg wire:loading.remove wire:target="next" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                @else
                    <button type="button"
                            wire:click="submit"
                            wire:loading.attr="disabled"
                            wire:target="back,next,submit"
                            @disabled(!$this->isReadyToSubmit)
                            title="{{ $this->isReadyToSubmit ? 'Submit your application for review' : 'Complete all required fields and upload all required documents first' }}"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-40 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="submit">Submit for Review</span>
                        <span wire:loading wire:target="submit">Submitting…</span>
                        <svg wire:loading.remove wire:target="submit" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        <p class="mt-3 text-center text-[11px] text-gray-400 dark:text-gray-500">
            Progress saves automatically when you continue.
        </p>
    </div>
</main>