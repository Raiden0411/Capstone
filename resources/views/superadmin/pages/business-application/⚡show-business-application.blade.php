<?php

use App\Models\BusinessApplication;
use App\Services\BusinessApplicationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('superadmin.layouts.app')]
#[Title('Review Application')]
class extends Component
{
    public BusinessApplication $application;

    public string $rejection_reason = '';
    public string $revision_notes   = '';

    public ?string $errorMessage = null;

    public function mount(BusinessApplication $application): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403);

        $application->load([
            'user:id,name,email,phone,avatar,created_at',
            'documents'            => fn ($q) => $q->orderBy('document_type'),
            'verifications.verifier:id,name',
            'typeOfTenant:id,type,description',
            'reviewer:id,name',
            'approvedTenant:id,name,slug',
        ]);

        $this->application = $application;
    }

    public function approve(BusinessApplicationService $service): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403);

        if (!$this->application->fresh(['documents'])->hasAllRequiredDocuments()) {
            $this->errorMessage = 'This application is missing required documents. Request a revision instead.';
            return;
        }

        try {
            $tenant = $service->approve($this->application->fresh(), Auth::user());

            session()->flash('message', "Business \"{$tenant->name}\" approved and tenant created.");

            $this->redirect(route('superadmin.business-applications.show', $this->application), navigate: true);
        } catch (\Throwable $e) {
            Log::error('KYB approve failed', [
                'application_id' => $this->application->id,
                'error'          => $e->getMessage(),
            ]);
            $this->errorMessage = 'Approval failed: ' . $e->getMessage();
        }
    }

    public function reject(BusinessApplicationService $service): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403);

        $validated = $this->validate([
            'rejection_reason' => 'required|string|max:1000',
        ], [], [
            'rejection_reason' => 'reason',
        ]);

        try {
            $service->reject($this->application, Auth::user(), $validated['rejection_reason']);

            session()->flash('message', 'Application rejected.');

            $this->redirect(route('superadmin.business-applications.show', $this->application), navigate: true);
        } catch (\Throwable $e) {
            Log::error('KYB reject failed', [
                'application_id' => $this->application->id,
                'error'          => $e->getMessage(),
            ]);
            $this->errorMessage = 'Rejection failed: ' . $e->getMessage();
        }
    }

    public function requestRevision(BusinessApplicationService $service): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403);

        $validated = $this->validate([
            'revision_notes' => 'required|string|max:1000',
        ], [], [
            'revision_notes' => 'notes',
        ]);

        try {
            $service->requestRevision($this->application, Auth::user(), $validated['revision_notes']);

            session()->flash('message', 'Revision requested. The applicant can now update their submission.');

            $this->redirect(route('superadmin.business-applications.show', $this->application), navigate: true);
        } catch (\Throwable $e) {
            Log::error('KYB requestRevision failed', [
                'application_id' => $this->application->id,
                'error'          => $e->getMessage(),
            ]);
            $this->errorMessage = 'Request failed: ' . $e->getMessage();
        }
    }
};
?>

@php
    $statusConfig = match ($application->status) {
        'pending'        => ['label' => 'Pending Review',  'classes' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300 border-amber-200 dark:border-amber-500/30'],
        'under_review'   => ['label' => 'Under Review',    'classes' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300 border-blue-200 dark:border-blue-500/30'],
        'needs_revision' => ['label' => 'Needs Revision',  'classes' => 'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300 border-orange-200 dark:border-orange-500/30'],
        'draft'          => ['label' => 'Draft',           'classes' => 'bg-slate-100 text-slate-700 dark:bg-slate-500/15 dark:text-slate-300 border-slate-200 dark:border-slate-500/30'],
        'approved'       => ['label' => 'Approved',        'classes' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30'],
        'rejected'       => ['label' => 'Rejected',        'classes' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300 border-rose-200 dark:border-rose-500/30'],
        default          => ['label' => ucfirst($application->status), 'classes' => 'bg-gray-100 text-gray-700 dark:bg-gray-500/15 dark:text-gray-300 border-gray-200 dark:border-gray-500/30'],
    };

    $isActionable = in_array($application->status, ['pending', 'under_review'], true);

    $verificationLabels = [
        'tin_match'  => 'TIN Match',
        'name_match' => 'Name Match',
    ];
@endphp

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">

    <a href="{{ route('superadmin.business-applications.index') }}" wire:navigate
       class="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors mb-6 rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to applications
    </a>

    {{-- HEADER --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-800">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-3 mb-2">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white truncate">
                    {{ $application->business_name ?? 'Untitled Application' }}
                </h1>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border {{ $statusConfig['classes'] }}">
                    {{ $statusConfig['label'] }}
                </span>
            </div>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                Application #{{ $application->id }}
                @if ($application->submitted_at)
                    · submitted {{ $application->submitted_at->format('M j, Y \a\t g:i A') }}
                    ({{ $application->submitted_at->diffForHumans() }})
                @else
                    · not yet submitted
                @endif
            </p>
        </div>
    </div>

    {{-- FLASH --}}
    @if (session('message'))
        <div class="mt-6 flex items-center gap-2.5 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
            <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>{{ session('message') }}</span>
        </div>
    @endif

    @if ($errorMessage)
        <div class="mt-6 flex items-center gap-2.5 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
            <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
            </svg>
            <span>{{ $errorMessage }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">

        {{-- LEFT --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Business --}}
            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Business Information
                    </span>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                    <div class="sm:col-span-2">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Business Name</dt>
                        <dd class="text-sm text-gray-900 dark:text-white font-medium">{{ $application->business_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Registration Type</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">
                            {{ BusinessApplication::BUSINESS_TYPE_LABELS[$application->business_type] ?? strtoupper($application->business_type ?? '—') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Category</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $application->typeOfTenant?->type ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Registration No.</dt>
                        <dd class="text-sm text-gray-900 dark:text-white font-mono">{{ $application->business_registration_number ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">TIN</dt>
                        <dd class="text-sm text-gray-900 dark:text-white font-mono">{{ $application->tin_number ?? '—' }}</dd>
                    </div>
                </dl>
            </section>

            {{-- Owner --}}
            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Owner Information
                    </span>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                    <div class="sm:col-span-2">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Full Name</dt>
                        <dd class="text-sm text-gray-900 dark:text-white font-medium">{{ $application->owner_full_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">ID Type</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">
                            {{ BusinessApplication::OWNER_ID_TYPES[$application->owner_id_type] ?? ($application->owner_id_type ?? '—') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">ID Number</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $application->owner_id_number ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Date of Birth</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $application->owner_birthdate?->format('M j, Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Contact Email</dt>
                        <dd class="text-sm text-gray-900 dark:text-white break-all">{{ $application->contact_email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Contact Phone</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $application->contact_phone ?? '—' }}</dd>
                    </div>
                </dl>
            </section>

            {{-- Location --}}
            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Location
                    </span>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                    <div class="sm:col-span-2">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Street Address</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $application->address ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Barangay</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $application->barangay ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">City / Municipality</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $application->city ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Province</dt>
                        <dd class="text-sm text-gray-900 dark:text-white">{{ $application->province ?? '—' }}</dd>
                    </div>
                </dl>
            </section>

            {{-- Documents --}}
            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Documents ({{ $application->documents->count() }})
                        </span>
                    </div>
                    @if ($application->hasAllRequiredDocuments())
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30">
                            Complete
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30">
                            Incomplete
                        </span>
                    @endif
                </div>

                @if ($application->documents->isEmpty())
                    <div class="text-center py-8">
                        <div class="inline-flex p-3 bg-gray-100 dark:bg-gray-800 rounded-2xl mb-3 text-gray-400">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">No documents uploaded</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Request a revision to prompt the applicant.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ($application->documents as $doc)
                            @php
                                $isImage = str_starts_with((string) $doc->mime_type, 'image/');
                                $url     = asset('storage/' . ($doc->watermarked_path ?: $doc->stored_path));
                            @endphp
                            <div wire:key="doc-{{ $doc->id }}"
                                 class="flex items-start gap-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-3">
                                @if ($isImage)
                                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                       class="block shrink-0 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 hover:border-primary-500 transition">
                                        <img src="{{ $url }}" alt="{{ \App\Models\BusinessApplication::DOCUMENT_LABELS[$doc->document_type] ?? $doc->document_type }}" loading="lazy"
                                             class="w-16 h-16 object-cover">
                                    </a>
                                @else
                                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                       class="shrink-0 w-16 h-16 rounded-lg bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 flex flex-col items-center justify-center text-rose-600 dark:text-rose-400 hover:border-rose-400 transition">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                        <span class="text-[9px] font-bold uppercase tracking-wider mt-0.5">PDF</span>
                                    </a>
                                @endif

                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-gray-900 dark:text-white truncate">
                                        {{ \App\Models\BusinessApplication::DOCUMENT_LABELS[$doc->document_type] ?? $doc->document_type }}
                                    </p>
                                    <p class="mt-0.5 text-[10px] text-gray-500 dark:text-gray-400 truncate">
                                        {{ $doc->original_filename }}
                                    </p>
                                    <p class="mt-0.5 text-[10px] text-gray-500 dark:text-gray-400">
                                        {{ number_format($doc->file_size / 1024, 1) }} KB
                                        @if ($doc->expires_at)
                                            · expires {{ $doc->expires_at->format('M j, Y') }}
                                        @endif
                                    </p>

                                    <div class="mt-2 flex items-center gap-2">
                                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                           class="inline-flex items-center gap-1 text-[10px] font-semibold text-primary-600 dark:text-primary-400 hover:underline">
                                            View
                                        </a>
                                        <a href="{{ $url }}" download
                                           class="inline-flex items-center gap-1 text-[10px] font-semibold text-gray-600 dark:text-gray-400 hover:underline">
                                            Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Verification results --}}
            @if ($application->verifications->isNotEmpty())
                <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Automated Verification Results
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-gray-50/70 dark:bg-gray-900/50 border-y border-gray-200/80 dark:border-gray-700/80 text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">
                                <tr>
                                    <th class="px-3 py-3">Check</th>
                                    <th class="px-3 py-3">Reference</th>
                                    <th class="px-3 py-3">Submitted</th>
                                    <th class="px-3 py-3">Score</th>
                                    <th class="px-3 py-3">Result</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                @foreach ($application->verifications as $v)
                                    <tr wire:key="v-{{ $v->id }}">
                                        <td class="px-3 py-3">
                                            <span class="text-xs font-semibold text-gray-900 dark:text-white">
                                                {{ $verificationLabels[$v->verification_type] ?? ucfirst(str_replace('_', ' ', $v->verification_type)) }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3">
                                            <span class="text-xs text-gray-700 dark:text-gray-300">{{ $v->reference_value ?? '—' }}</span>
                                        </td>
                                        <td class="px-3 py-3">
                                            <span class="text-xs text-gray-700 dark:text-gray-300">{{ $v->submitted_value ?? '—' }}</span>
                                        </td>
                                        <td class="px-3 py-3">
                                            <span class="text-xs font-semibold text-gray-900 dark:text-white">
                                                {{ $v->confidence_score !== null ? number_format($v->confidence_score, 1) . '%' : '—' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3">
                                            @if ($v->matched)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30">
                                                    Matched
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30">
                                                    Mismatch
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>

        {{-- RIGHT --}}
        <div class="space-y-6">

            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Applicant
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    @if ($application->user?->avatar)
                        <img src="{{ asset('storage/' . $application->user->avatar) }}"
                             alt="{{ $application->user->name }}"
                             class="w-12 h-12 rounded-full object-cover shrink-0">
                    @else
                        <div class="w-12 h-12 rounded-full bg-primary-600 text-white flex items-center justify-center text-sm font-bold shrink-0">
                            {{ strtoupper(substr($application->user?->name ?? '?', 0, 1)) }}
                        </div>
                    @endif
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $application->user?->name ?? '—' }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $application->user?->email ?? '—' }}</p>
                        @if ($application->user?->created_at)
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">
                                Member since {{ $application->user->created_at->format('M Y') }}
                            </p>
                        @endif
                    </div>
                </div>
            </section>

            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Review Status
                    </span>
                </div>

                @if ($application->reviewed_at)
                    <dl class="space-y-3 text-xs">
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-0.5">Reviewed By</dt>
                            <dd class="text-gray-900 dark:text-white">{{ $application->reviewer?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-0.5">Reviewed At</dt>
                            <dd class="text-gray-900 dark:text-white">{{ $application->reviewed_at->format('M j, Y g:i A') }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="text-xs text-gray-500 dark:text-gray-400 italic">Not yet reviewed.</p>
                @endif

                @if ($application->rejection_reason)
                    <div class="mt-4 rounded-xl bg-rose-50/60 dark:bg-rose-500/5 border border-rose-100 dark:border-rose-500/20 p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 mb-1">Rejection reason</p>
                        <p class="text-xs text-rose-900 dark:text-rose-300 leading-relaxed">{{ $application->rejection_reason }}</p>
                    </div>
                @endif

                @if ($application->revision_notes)
                    <div class="mt-4 rounded-xl bg-amber-50/60 dark:bg-amber-500/5 border border-amber-100 dark:border-amber-500/20 p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 mb-1">Revision notes</p>
                        <p class="text-xs text-amber-900 dark:text-amber-300 leading-relaxed">{{ $application->revision_notes }}</p>
                    </div>
                @endif

                @if ($application->approvedTenant)
                    <a href="{{ route('superadmin.tenants.preview', $application->approvedTenant) }}" wire:navigate
                       class="mt-4 inline-flex items-center gap-2 w-full justify-center px-4 py-2 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-xs font-semibold border border-emerald-200 dark:border-emerald-500/30 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50">
                        View approved tenant
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @endif
            </section>

            {{-- Actions --}}
            @if ($isActionable)
                <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Actions
                        </span>
                    </div>

                    <button type="button"
                            wire:click="approve"
                            wire:confirm="Approve this application and create the tenant?"
                            wire:loading.attr="disabled"
                            wire:target="approve"
                            @disabled(!$application->hasAllRequiredDocuments())
                            title="{{ $application->hasAllRequiredDocuments() ? 'Approve and create the tenant' : 'All required documents must be uploaded first' }}"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-sm transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50 disabled:opacity-40 disabled:cursor-not-allowed">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span wire:loading.remove wire:target="approve">Approve &amp; Create Tenant</span>
                        <span wire:loading wire:target="approve">Approving...</span>
                    </button>

                    <details class="mt-4">
                        <summary class="cursor-pointer inline-flex items-center gap-2 w-full justify-center px-4 py-2.5 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 text-sm font-semibold border border-amber-200 dark:border-amber-500/30 hover:bg-amber-100 dark:hover:bg-amber-500/20 transition list-none active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                            </svg>
                            Request Revision
                        </summary>
                        <div class="mt-3">
                            <textarea wire:model="revision_notes" rows="3" maxlength="1000"
                                      placeholder="Explain what needs to be corrected..."
                                      class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition resize-none"></textarea>
                            @error('revision_notes') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                            <button type="button"
                                    wire:click="requestRevision"
                                    wire:loading.attr="disabled"
                                    wire:target="requestRevision"
                                    class="mt-2 w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold shadow-sm transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50 disabled:opacity-60">
                                Send Revision Request
                            </button>
                        </div>
                    </details>

                    <details class="mt-3">
                        <summary class="cursor-pointer inline-flex items-center gap-2 w-full justify-center px-4 py-2.5 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 text-sm font-semibold border border-rose-200 dark:border-rose-500/30 hover:bg-rose-100 dark:hover:bg-rose-500/20 transition list-none active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Reject Application
                        </summary>
                        <div class="mt-3">
                            <textarea wire:model="rejection_reason" rows="3" maxlength="1000"
                                      placeholder="Explain why this application is being rejected..."
                                      class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition resize-none"></textarea>
                            @error('rejection_reason') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                            <button type="button"
                                    wire:click="reject"
                                    wire:confirm="Reject this application permanently?"
                                    wire:loading.attr="disabled"
                                    wire:target="reject"
                                    class="mt-2 w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-sm transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 disabled:opacity-60">
                                Reject Permanently
                            </button>
                        </div>
                    </details>
                </section>
            @endif
        </div>
    </div>
</div>