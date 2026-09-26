{{-- resources/views/superadmin/pages/business-application/⚡show-business-application.blade.php --}}
<?php

use App\Models\BusinessApplication;
use App\Models\User;
use App\Services\BusinessApplicationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
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

    /**
     * Eager-load set — mirrored between mount() and hydrate() so the
     * relation graph is identical on every request. Without this in
     * hydrate(), Livewire v4's model re-fetch drops the loaded
     * relations and every `->documents` / `->verifications` access
     * becomes a fresh lazy query.
     */
    private const RELATIONS = [
        'user:id,name,email,avatar,created_at',
        'documents'            => 'orderBy:document_type',
        'verifications.verifier:id,name',
        'typeOfTenant:id,type,description',
        'reviewer:id,name',
        'approvedTenant:id,name,slug',
    ];

    public function mount(BusinessApplication $application): void
    {
        $this->assertSuperAdmin();

        $application->load([
            'user:id,name,email,avatar,created_at',
            'documents'            => fn ($q) => $q->orderBy('document_type'),
            'verifications.verifier:id,name',
            'typeOfTenant:id,type,description',
            'reviewer:id,name',
            'approvedTenant:id,name,slug',
        ]);

        $this->application = $application;
    }

    public function hydrate(): void
    {
        $this->assertSuperAdmin();

        // Re-hydrate the relation graph — Livewire's model binding
        // re-fetches the row but does NOT re-populate relations.
        $this->application->load([
            'user:id,name,email,avatar,created_at',
            'documents'            => fn ($q) => $q->orderBy('document_type'),
            'verifications.verifier:id,name',
            'typeOfTenant:id,type,description',
            'reviewer:id,name',
            'approvedTenant:id,name,slug',
        ]);
    }

    protected function assertSuperAdmin(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403);
    }

    /**
     * Resolve and type-cast the authenticated super-admin.
     *
     * Rule 44: Auth::user() returns Authenticatable|null, not User.
     * Always called AFTER assertSuperAdmin() has passed.
     */
    protected function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    // ─────────────────────────────────────────────────────────────
    //  Computed
    // ─────────────────────────────────────────────────────────────

    /** @return array{label: string, classes: string} */
    #[Computed]
    public function statusConfig(): array
    {
        return match ($this->application->status) {
            'pending'        => ['label' => 'Pending Review',  'classes' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300 border-amber-200 dark:border-amber-500/30'],
            'under_review'   => ['label' => 'Under Review',    'classes' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300 border-blue-200 dark:border-blue-500/30'],
            'needs_revision' => ['label' => 'Needs Revision',  'classes' => 'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300 border-orange-200 dark:border-orange-500/30'],
            'draft'          => ['label' => 'Draft',           'classes' => 'bg-slate-100 text-slate-700 dark:bg-slate-500/15 dark:text-slate-300 border-slate-200 dark:border-slate-500/30'],
            'approved'       => ['label' => 'Approved',        'classes' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30'],
            'rejected'       => ['label' => 'Rejected',        'classes' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300 border-rose-200 dark:border-rose-500/30'],
            default          => ['label' => ucfirst($this->application->status), 'classes' => 'bg-gray-100 text-gray-700 dark:bg-gray-500/15 dark:text-gray-300 border-gray-200 dark:border-gray-500/30'],
        };
    }

    #[Computed]
    public function isActionable(): bool
    {
        return in_array($this->application->status, ['pending', 'under_review'], true);
    }

    #[Computed]
    public function docCount(): int
    {
        return $this->application->documents->count();
    }

    #[Computed]
    public function isDocComplete(): bool
    {
        return $this->application->hasAllRequiredDocuments();
    }

    #[Computed]
    public function businessDescription(): ?string
    {
        return $this->application->metadata['description'] ?? null;
    }

    /** @return array<string, string> */
    #[Computed]
    public function verificationLabels(): array
    {
        return [
            'tin_match'  => 'TIN Match',
            'name_match' => 'Name Match',
        ];
    }

    /** @return array{lat: float, lng: float}|null */
    #[Computed]
    public function primaryCoords(): ?array
    {
        $coords  = $this->application->coordinates ?? [];
        $primary = $coords[0] ?? null;

        if (! $primary || ! isset($primary['lat'], $primary['lng'])) {
            return null;
        }

        return [
            'lat' => (float) $primary['lat'],
            'lng' => (float) $primary['lng'],
        ];
    }

    #[Computed]
    public function hasPinnedLocation(): bool
    {
        return $this->primaryCoords !== null;
    }

    // ─────────────────────────────────────────────────────────────
    //  Actions
    // ─────────────────────────────────────────────────────────────

    public function approve(BusinessApplicationService $service): void
    {
        $this->assertSuperAdmin();
        $this->errorMessage = null;

        $fresh = $this->application->fresh(['documents']);

        if (! $fresh->hasAllRequiredDocuments()) {
            $this->errorMessage = 'This application is missing required documents. Request a revision instead.';
            return;
        }

        try {
            $tenant = $service->approve($fresh, $this->currentUser());

            session()->flash(
                'message',
                "Business \"{$tenant->name}\" approved and tenant created. The applicant has been notified by email."
            );

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
        $this->assertSuperAdmin();
        $this->errorMessage = null;

        $validated = $this->validate([
            'rejection_reason' => 'required|string|max:1000',
        ], [], [
            'rejection_reason' => 'reason',
        ]);

        try {
            $service->reject($this->application, $this->currentUser(), $validated['rejection_reason']);

            session()->flash('message', 'Application rejected. The applicant has been notified by email.');

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
        $this->assertSuperAdmin();
        $this->errorMessage = null;

        $validated = $this->validate([
            'revision_notes' => 'required|string|max:1000',
        ], [], [
            'revision_notes' => 'notes',
        ]);

        try {
            $service->requestRevision($this->application, $this->currentUser(), $validated['revision_notes']);

            session()->flash(
                'message',
                'Revision requested. The applicant can now update their submission and has been notified by email.'
            );

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

<div class="p-4 sm:p-6 lg:p-8 max-w-6xl mx-auto space-y-6">

    {{-- ═══ Back link ═══ --}}
    <a href="{{ route('superadmin.business-applications.index') }}" wire:navigate
       class="inline-flex items-center gap-2 text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400
              transition-all duration-200 active:scale-95 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        <span>Back to applications</span>
    </a>

    {{-- ═══ Page header ═══ --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div class="min-w-0">
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">KYB Review</span>
            </div>
            <div class="flex flex-wrap items-center gap-3 mb-2">
                <h1 class="font-display text-3xl md:text-4xl font-semibold text-gray-900 dark:text-white truncate">
                    {{ $application->business_name ?? 'Untitled Application' }}
                </h1>
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border {{ $this->statusConfig['classes'] }}">
                    <span class="w-1 h-1 rounded-full bg-current"></span>
                    {{ $this->statusConfig['label'] }}
                </span>
            </div>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                Application #<span class="font-mono">{{ $application->id }}</span>
                @if ($application->submitted_at)
                    · submitted {{ $application->submitted_at->format('M j, Y \a\t g:i A') }}
                    ({{ $application->submitted_at->diffForHumans() }})
                @else
                    · not yet submitted
                @endif
            </p>
        </div>
    </div>

    {{-- ═══ Flash: success ═══ --}}
    @if (session('message'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 4000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>{{ session('message') }}</span>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-7 w-7 rounded-md text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/10
                           transition-all duration-200 active:scale-95 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    {{-- ═══ Flash: local error state ═══ --}}
    @if ($errorMessage)
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 6000)"
             :class="show ? '' : 'hidden'"
             role="alert"
             aria-live="polite"
             class="flex items-start justify-between gap-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
            <div class="flex items-start gap-2.5 min-w-0">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span>{{ $errorMessage }}</span>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-7 w-7 shrink-0 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                           transition-all duration-200 active:scale-95 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    {{-- ═══ HERO — COVER PHOTO ═══ --}}
    <section class="rounded-2xl overflow-hidden border border-gray-200/80 dark:border-gray-700/80 shadow-sm">
        <div class="relative aspect-[3/1] w-full bg-gray-900">
            @if ($application->cover_photo_path)
                <img src="{{ asset('storage/' . $application->cover_photo_path) }}"
                     alt="{{ $application->business_name }} cover photo"
                     loading="eager"
                     fetchpriority="high"
                     decoding="async"
                     class="w-full h-full object-cover">

                <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/15 to-transparent pointer-events-none"></div>

                <div class="absolute inset-x-0 bottom-0 p-5 sm:p-6 flex items-end justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-white/80 mb-1">
                            Applicant's Cover Photo
                        </p>
                        <p class="text-lg sm:text-xl font-bold text-white leading-tight truncate">
                            {{ $application->business_name }}
                        </p>
                    </div>

                    <span class="hidden sm:inline-flex shrink-0 items-center gap-1 px-2 py-1 rounded-full bg-white/15 backdrop-blur-sm border border-white/20 text-[10px] font-bold uppercase tracking-wider text-white">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                        </svg>
                        Uploaded
                    </span>
                </div>
            @else
                <div class="absolute inset-0 bg-gradient-to-br from-primary-500 via-primary-600 to-primary-700 flex items-center justify-center text-white/85">
                    <div class="text-center px-6">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-white/15 backdrop-blur-sm border border-white/20 mb-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-bold uppercase tracking-wider text-white">No cover photo uploaded</p>
                        <p class="mt-1 text-xs text-white/70 max-w-md mx-auto leading-relaxed">
                            Cover photos are optional but strongly recommended — the public listing shows this as the hero banner. Consider requesting a revision if this category expects one.
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ══════════════════════════ LEFT ══════════════════════════ --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Business --}}
            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-5 sm:p-6">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Business Information
                    </span>
                </div>

                <div class="mb-5 pb-5 border-b border-gray-100 dark:border-gray-700/60">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
                        Business Logo
                    </p>
                    @if ($application->logo_path)
                        <div class="flex items-center gap-4">
                            <img src="{{ asset('storage/' . $application->logo_path) }}"
                                 alt="{{ $application->business_name }} logo"
                                 loading="lazy"
                                 decoding="async"
                                 width="80"
                                 height="80"
                                 class="w-20 h-20 rounded-2xl object-cover border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Uploaded</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    Will become the public listing logo when approved.
                                </p>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center gap-4">
                            <div class="w-20 h-20 rounded-2xl border-2 border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 flex items-center justify-center text-gray-400">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 italic">Not uploaded</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                    Optional. Consider requesting a revision if a logo is expected for this category.
                                </p>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mb-5 pb-5 border-b border-gray-100 dark:border-gray-700/60">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
                        Business Description
                    </p>
                    @if ($this->businessDescription)
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line">
                            {{ $this->businessDescription }}
                        </p>
                    @else
                        <p class="text-sm italic text-gray-400 dark:text-gray-500">
                            Not provided. The applicant can add one from the wizard — it appears on the public listing.
                        </p>
                    @endif
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
            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-5 sm:p-6">
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Owner Information
                    </span>
                </div>

                <div class="mb-5 pb-5 border-b border-gray-100 dark:border-gray-700/60">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
                        Owner Photo
                    </p>
                    @if ($application->owner_avatar_path)
                        <div class="flex items-center gap-4">
                            <img src="{{ asset('storage/' . $application->owner_avatar_path) }}"
                                 alt="Owner photo"
                                 loading="lazy"
                                 decoding="async"
                                 width="64"
                                 height="64"
                                 class="w-16 h-16 rounded-full object-cover border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Uploaded</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    Will become the applicant's profile avatar when approved.
                                </p>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 rounded-full border-2 border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 flex items-center justify-center text-gray-400">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 italic">Not uploaded</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                    Optional. The applicant's account avatar will be used if available.
                                </p>
                            </div>
                        </div>
                    @endif
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
            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-5 sm:p-6">
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

                @if ($this->hasPinnedLocation)
                    @php $coords = $this->primaryCoords; @endphp

                    <div class="mt-5 pt-5 border-t border-gray-100 dark:border-gray-700/60">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Pinned Location
                                </p>
                                <p class="mt-0.5 text-xs font-mono text-gray-600 dark:text-gray-300 tabular-nums">
                                    {{ number_format($coords['lat'], 6) }}, {{ number_format($coords['lng'], 6) }}
                                </p>
                            </div>

                            <a href="https://www.google.com/maps?q={{ $coords['lat'] }},{{ $coords['lng'] }}"
                               target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center justify-center gap-1.5 h-11 px-4 rounded-lg
                                      border border-primary-300 dark:border-primary-500/40
                                      bg-white dark:bg-gray-800 text-primary-700 dark:text-primary-300
                                      text-[11px] font-semibold
                                      transition-all duration-200 active:scale-95 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                      hover:bg-primary-50 dark:hover:bg-primary-500/10
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                                Open in Google Maps
                            </a>
                        </div>

                        <div wire:ignore
                             wire:key="review-map-{{ $application->id }}"
                             class="relative h-72 sm:h-80 rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-900">
                            <x-map
                                id="review-map-{{ $application->id }}"
                                :center="[$coords['lng'], $coords['lat']]"
                                :zoom="16"
                                height="100%"
                                provider="carto-voyager"
                                theme="auto"
                                :max-zoom="22"
                                class="h-full w-full"
                            >
                                <x-map-controls
                                    :zoom="true"
                                    :compass="true"
                                    :locate="false"
                                    :fullscreen="true"
                                    :scale="false"
                                    position="top-right"
                                />

                                <x-map-marker
                                    :lat="$coords['lat']"
                                    :lng="$coords['lng']"
                                    color="#ef4444"
                                    id="review-pin"
                                    anchor="bottom"
                                >
                                    <x-marker-content>
                                        <div class="relative flex items-center justify-center">
                                            <svg class="h-10 w-10 drop-shadow-lg" viewBox="0 0 24 24" fill="#ef4444" stroke="white" stroke-width="1.5" aria-hidden="true">
                                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                                                <circle cx="12" cy="9" r="2.5" fill="white"/>
                                            </svg>
                                        </div>
                                    </x-marker-content>
                                    <x-marker-popup>
                                        <div class="p-2">
                                            <strong class="text-gray-900 dark:text-white">{{ $application->business_name ?? 'Pinned Location' }}</strong>
                                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Applicant's pinned coordinate</p>
                                        </div>
                                    </x-marker-popup>
                                </x-map-marker>
                            </x-map>
                        </div>

                        <p class="mt-2 text-[10px] text-gray-400 dark:text-gray-500 leading-relaxed">
                            Reviewer view — pan and zoom to verify the pin falls inside the declared barangay and near the street address.
                        </p>
                    </div>
                @else
                    <div class="mt-5 pt-5 border-t border-gray-100 dark:border-gray-700/60">
                        <div class="flex items-start gap-3 rounded-xl border border-amber-200/80 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/[0.06] px-4 py-3">
                            <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <div class="min-w-0 text-xs sm:text-sm">
                                <p class="font-semibold text-amber-900 dark:text-amber-200">
                                    No location pinned
                                </p>
                                <p class="mt-0.5 text-amber-800/80 dark:text-amber-300/80 leading-relaxed">
                                    The applicant did not drop a pin on the map. Consider requesting a revision so the public listing has a proper map marker.
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            </section>

            {{-- Documents --}}
            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-5 sm:p-6">
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Documents ({{ $this->docCount }})
                        </span>
                    </div>
                    @if ($this->isDocComplete)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30">
                            <span class="w-1 h-1 rounded-full bg-current"></span>
                            Complete
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30">
                            <span class="w-1 h-1 rounded-full bg-current"></span>
                            Incomplete
                        </span>
                    @endif
                </div>

                @if ($application->documents->isEmpty())
                    <div class="text-center py-8">
                        <div class="inline-flex p-3 bg-gray-100 dark:bg-gray-800 rounded-2xl mb-3 text-gray-400">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
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
                                        <img src="{{ $url }}"
                                             alt="{{ BusinessApplication::DOCUMENT_LABELS[$doc->document_type] ?? $doc->document_type }}"
                                             loading="lazy"
                                             decoding="async"
                                             class="w-16 h-16 object-cover">
                                    </a>
                                @else
                                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                       class="shrink-0 w-16 h-16 rounded-lg bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 flex flex-col items-center justify-center text-rose-600 dark:text-rose-400 hover:border-rose-400 transition">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                        <span class="text-[9px] font-bold uppercase tracking-wider mt-0.5">PDF</span>
                                    </a>
                                @endif

                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-gray-900 dark:text-white truncate">
                                        {{ BusinessApplication::DOCUMENT_LABELS[$doc->document_type] ?? $doc->document_type }}
                                    </p>
                                    <p class="mt-0.5 text-[10px] text-gray-500 dark:text-gray-400 truncate">
                                        {{ $doc->original_filename }}
                                    </p>
                                    <p class="mt-0.5 text-[10px] text-gray-500 dark:text-gray-400 tabular-nums">
                                        {{ number_format($doc->file_size / 1024, 1) }} KB
                                        @if ($doc->expires_at)
                                            · expires {{ $doc->expires_at->format('M j, Y') }}
                                        @endif
                                    </p>

                                    <div class="mt-2 flex items-center gap-3">
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
                <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-5 sm:p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Automated Verification Results
                        </span>
                    </div>

                    <div class="overflow-x-auto -mx-2 px-2">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-gray-50/70 dark:bg-gray-900/50 border-b border-gray-200/80 dark:border-gray-700/80 text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">
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
                                                {{ $this->verificationLabels[$v->verification_type] ?? ucfirst(str_replace('_', ' ', $v->verification_type)) }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3">
                                            <span class="text-xs text-gray-700 dark:text-gray-300">{{ $v->reference_value ?? '—' }}</span>
                                        </td>
                                        <td class="px-3 py-3">
                                            <span class="text-xs text-gray-700 dark:text-gray-300">{{ $v->submitted_value ?? '—' }}</span>
                                        </td>
                                        <td class="px-3 py-3">
                                            <span class="text-xs font-semibold text-gray-900 dark:text-white tabular-nums">
                                                {{ $v->confidence_score !== null ? number_format($v->confidence_score, 1) . '%' : '—' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3">
                                            @if ($v->matched)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30">
                                                    <span class="w-1 h-1 rounded-full bg-current"></span>
                                                    Matched
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30">
                                                    <span class="w-1 h-1 rounded-full bg-current"></span>
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

        {{-- ══════════════════════════ RIGHT ══════════════════════════ --}}
        <div class="space-y-6">

            {{-- Applicant --}}
            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-5 sm:p-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Applicant Account
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    @if ($application->user?->avatar)
                        <img src="{{ asset('storage/' . $application->user->avatar) }}"
                             alt="{{ $application->user->name }}"
                             loading="lazy"
                             decoding="async"
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

            {{-- Review status --}}
            <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-5 sm:p-6">
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
                            <dd class="text-gray-900 dark:text-white tabular-nums">{{ $application->reviewed_at->format('M j, Y g:i A') }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="text-xs text-gray-500 dark:text-gray-400 italic">Not yet reviewed.</p>
                @endif

                @if ($application->rejection_reason)
                    <div class="mt-4 rounded-xl bg-rose-50/60 dark:bg-rose-500/5 border border-rose-100 dark:border-rose-500/20 p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 mb-1">Rejection reason</p>
                        <p class="text-xs text-rose-900 dark:text-rose-300 leading-relaxed whitespace-pre-line">{{ $application->rejection_reason }}</p>
                    </div>
                @endif

                @if ($application->revision_notes)
                    <div class="mt-4 rounded-xl bg-amber-50/60 dark:bg-amber-500/5 border border-amber-100 dark:border-amber-500/20 p-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 mb-1">Revision notes</p>
                        <p class="text-xs text-amber-900 dark:text-amber-300 leading-relaxed whitespace-pre-line">{{ $application->revision_notes }}</p>
                    </div>
                @endif

                @if ($application->approvedTenant)
                    <a href="{{ route('superadmin.tenants.preview', $application->approvedTenant) }}" wire:navigate
                       class="mt-4 inline-flex items-center justify-center gap-1.5 w-full h-11 px-5 rounded-xl
                              border border-emerald-300 dark:border-emerald-500/40
                              bg-white dark:bg-gray-800 text-emerald-700 dark:text-emerald-300
                              text-xs font-semibold
                              transition-all duration-200 active:scale-95 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                              hover:bg-emerald-50 dark:hover:bg-emerald-500/10
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <span>View approved tenant</span>
                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @endif
            </section>

            {{-- ACTIONS --}}
            @if ($this->isActionable)
                <section class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-5 sm:p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Review Actions
                        </span>
                    </div>

                    {{-- Approve --}}
                    <button type="button"
                            x-on:click="if (confirm('Approve this application and create the tenant? An approval email will be sent to the applicant.')) $wire.approve()"
                            wire:loading.attr="disabled"
                            wire:target="approve"
                            @disabled(! $this->isDocComplete)
                            title="{{ $this->isDocComplete ? 'Approve and create the tenant' : 'All required documents must be uploaded first' }}"
                            class="w-full inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-sm
                                   transition-all duration-200 active:scale-95 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                   disabled:opacity-40 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="approve" class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Approve &amp; create tenant
                        </span>
                        <span wire:loading wire:target="approve" class="inline-flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Approving…
                        </span>
                    </button>

                    @if (! $this->isDocComplete)
                        <p class="mt-2 text-[10px] text-amber-600 dark:text-amber-400 leading-relaxed">
                            All required documents must be uploaded before this application can be approved.
                        </p>
                    @endif

                    {{-- Divider --}}
                    <div class="relative my-5" aria-hidden="true">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-200 dark:border-gray-700"></div>
                        </div>
                        <div class="relative flex justify-center">
                            <span class="bg-white dark:bg-gray-800/90 px-3 text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                or
                            </span>
                        </div>
                    </div>

                    {{-- Tab switcher --}}
                    <div x-data="{ tab: null }" class="space-y-3">
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button"
                                    x-on:click="tab = tab === 'revision' ? null : 'revision'"
                                    :class="tab === 'revision'
                                        ? 'bg-amber-100 dark:bg-amber-500/20 text-amber-800 dark:text-amber-200 border-amber-300 dark:border-amber-500/50 ring-2 ring-amber-500/30'
                                        : 'bg-white dark:bg-gray-800 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/30 hover:bg-amber-50 dark:hover:bg-amber-500/10'"
                                    class="inline-flex items-center justify-center gap-1.5 h-11 px-3 rounded-xl text-xs font-semibold border
                                           transition-all duration-200 active:scale-95 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                                </svg>
                                Request revision
                            </button>

                            <button type="button"
                                    x-on:click="tab = tab === 'reject' ? null : 'reject'"
                                    :class="tab === 'reject'
                                        ? 'bg-rose-100 dark:bg-rose-500/20 text-rose-800 dark:text-rose-200 border-rose-300 dark:border-rose-500/50 ring-2 ring-rose-500/30'
                                        : 'bg-white dark:bg-gray-800 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-500/30 hover:bg-rose-50 dark:hover:bg-rose-500/10'"
                                    class="inline-flex items-center justify-center gap-1.5 h-11 px-3 rounded-xl text-xs font-semibold border
                                           transition-all duration-200 active:scale-95 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                Reject
                            </button>
                        </div>

                        {{-- Revision panel --}}
                        <div :class="tab === 'revision' ? 'block' : 'hidden'" class="pt-2">
                            <label for="revision-notes" class="sr-only">Revision notes</label>
                            <textarea id="revision-notes"
                                      wire:model="revision_notes"
                                      rows="4"
                                      maxlength="1000"
                                      @error('revision_notes') aria-describedby="revision-notes-error" @enderror
                                      placeholder="Explain what needs to be corrected — this text is emailed to the applicant."
                                      class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2.5 px-4 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition resize-none"></textarea>
                            @error('revision_notes')
                                <p id="revision-notes-error" class="mt-1 text-xs text-rose-500 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                            <button type="button"
                                    x-on:click="if (confirm('Send this revision request? The applicant will receive an email with your notes.')) $wire.requestRevision()"
                                    wire:loading.attr="disabled"
                                    wire:target="requestRevision"
                                    class="mt-3 w-full inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold shadow-sm
                                           transition-all duration-200 active:scale-95 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                <span wire:loading.remove wire:target="requestRevision">Send revision request</span>
                                <span wire:loading wire:target="requestRevision" class="inline-flex items-center gap-2">
                                    <svg class="animate-spin w-3.5 h-3.5 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    Sending…
                                </span>
                            </button>
                        </div>

                        {{-- Reject panel --}}
                        <div :class="tab === 'reject' ? 'block' : 'hidden'" class="pt-2">
                            <label for="rejection-reason" class="sr-only">Rejection reason</label>
                            <textarea id="rejection-reason"
                                      wire:model="rejection_reason"
                                      rows="4"
                                      maxlength="1000"
                                      @error('rejection_reason') aria-describedby="rejection-reason-error" @enderror
                                      placeholder="Explain why this application is being rejected — this text is emailed to the applicant."
                                      class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2.5 px-4 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-rose-500/50 focus:border-rose-500 transition resize-none"></textarea>
                            @error('rejection_reason')
                                <p id="rejection-reason-error" class="mt-1 text-xs text-rose-500 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                            <button type="button"
                                    x-on:click="if (confirm('Reject this application permanently? A rejection email will be sent to the applicant.')) $wire.reject()"
                                    wire:loading.attr="disabled"
                                    wire:target="reject"
                                    class="mt-3 w-full inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-sm
                                           transition-all duration-200 active:scale-95 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                <span wire:loading.remove wire:target="reject">Reject permanently</span>
                                <span wire:loading wire:target="reject" class="inline-flex items-center gap-2">
                                    <svg class="animate-spin w-3.5 h-3.5 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    Rejecting…
                                </span>
                            </button>
                        </div>
                    </div>

                    <p class="mt-4 text-[10px] text-gray-400 dark:text-gray-500 leading-relaxed">
                        Every action sends an email to the applicant and cannot be undone. Reviewer name and notes are included.
                    </p>
                </section>
            @endif
        </div>
    </div>
</div>