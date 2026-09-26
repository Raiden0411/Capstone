{{-- resources/views/tenant/pages/documents/⚡index.blade.php --}}
<?php

use App\Models\BusinessApplication;
use App\Models\BusinessDocument;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('tenant.layouts.app')]
#[Title('Documents')]
class extends Component
{
    public ?Tenant $tenant = null;

    #[Locked]
    public ?int $applicationId = null;

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user, 403);
        abort_unless($user->tenant_id, 403, 'No business is linked to your account.');
        abort_unless(
            $user->hasAnyRole(['admin', 'super-admin']),
            403,
            'Only business owners can view documents.'
        );

        $this->tenant = $user->tenant;

        $application = BusinessApplication::query()
            ->where('approved_tenant_id', $this->tenant->id)
            ->latest('id')
            ->first();

        $this->applicationId = $application?->id;
    }

    public function hydrate(): void
    {
        $user = Auth::user();

        abort_unless($user, 403);
        abort_unless($user->tenant_id, 403);
        abort_unless($user->hasAnyRole(['admin', 'super-admin']), 403);
        abort_unless($this->tenant?->id === $user->tenant_id, 403);
    }

    #[Computed]
    public function application(): ?BusinessApplication
    {
        if (! $this->applicationId) {
            return null;
        }

        return BusinessApplication::with([
            'documents' => fn ($q) => $q->orderByDesc('created_at'),
        ])->find($this->applicationId);
    }

    /**
     * @return array<int, array{
     *     type: string,
     *     label: string,
     *     required: bool,
     *     latest: ?BusinessDocument,
     *     status: string,
     *     status_color: string,
     *     status_label: string,
     *     has_pending_renewal: bool,
     *     expiry_days: ?int,
     * }>
     */
    #[Computed]
    public function documentRows(): array
    {
        $application = $this->application;
        if (! $application) {
            return [];
        }

        $byType = $application->documents
            ->groupBy('document_type')
            ->map(fn ($group) => $group->first());

        $rows = [];

        $required = BusinessApplication::REQUIRED_DOCUMENTS;
        $optional = BusinessApplication::OPTIONAL_DOCUMENTS;
        $labels   = BusinessApplication::DOCUMENT_LABELS;

        foreach ($required as $type) {
            $rows[] = $this->buildRow($type, $labels[$type] ?? $type, true,  $byType->get($type));
        }

        foreach ($optional as $type) {
            $latest = $byType->get($type);
            if ($latest !== null) {
                $rows[] = $this->buildRow($type, $labels[$type] ?? $type, false, $latest);
            }
        }

        usort($rows, function ($a, $b) {
            $rank = [
                'expired'    => 0,
                'expiring'   => 1,
                'missing'    => 2,
                'valid'      => 3,
                'no_expiry'  => 4,
            ];
            return ($rank[$a['status']] ?? 99) <=> ($rank[$b['status']] ?? 99);
        });

        return $rows;
    }

    /**
     * @return array{
     *     type: string,
     *     label: string,
     *     required: bool,
     *     latest: ?BusinessDocument,
     *     status: string,
     *     status_color: string,
     *     status_label: string,
     *     has_pending_renewal: bool,
     *     expiry_days: ?int,
     * }
     */
    private function buildRow(string $type, string $label, bool $required, ?BusinessDocument $latest): array
    {
        $expiryDays = $latest?->expires_at
            ? (int) now()->startOfDay()->diffInDays($latest->expires_at->startOfDay(), false)
            : null;

        $hasPendingRenewal = $latest !== null
            && $latest->is_renewal
            && $latest->verification_status === BusinessDocument::STATUS_PENDING;

        if ($latest === null) {
            $status      = 'missing';
            $statusLabel = 'Not uploaded';
            $statusColor = 'bg-rose-50 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-500/30';
        } elseif ($expiryDays === null) {
            $status      = 'no_expiry';
            $statusLabel = 'On file';
            $statusColor = 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30';
        } elseif ($expiryDays < 0) {
            $status      = 'expired';
            $statusLabel = 'Expired ' . abs($expiryDays) . ' day' . (abs($expiryDays) === 1 ? '' : 's') . ' ago';
            $statusColor = 'bg-rose-50 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-500/30';
        } elseif ($expiryDays <= 60) {
            $status      = 'expiring';
            $statusLabel = 'Expires in ' . $expiryDays . ' day' . ($expiryDays === 1 ? '' : 's');
            $statusColor = $expiryDays <= 7
                ? 'bg-rose-50 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-500/30'
                : 'bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/30';
        } else {
            $status      = 'valid';
            $statusLabel = 'Valid until ' . $latest->expires_at->format('M j, Y');
            $statusColor = 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30';
        }

        return [
            'type'                => $type,
            'label'               => $label,
            'required'            => $required,
            'latest'              => $latest,
            'status'              => $status,
            'status_color'        => $statusColor,
            'status_label'        => $statusLabel,
            'has_pending_renewal' => $hasPendingRenewal,
            'expiry_days'         => $expiryDays,
        ];
    }

    /**
     * Rule J — relative /storage/ URL. Every "View" link on this page
     * resolves correctly on any origin (envkit.net, 127.0.0.1, tunnel).
     */
    public function docUrl(BusinessDocument $document): string
    {
        $path = $document->watermarked_path ?: $document->stored_path;

        return '/storage/' . ltrim($path, '/');
    }
};
?>

@push('styles')
    @once
        <style>
            .tenant-documents-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.06) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.05) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.04) 0%, transparent 60%);
            }
            .dark .tenant-documents-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.08) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.07) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.06) 0%, transparent 60%);
            }
        </style>
    @endonce
@endpush

<div class="relative">
    <div class="tenant-documents-ambient fixed inset-0 -z-10 pointer-events-none" aria-hidden="true"></div>

    <div class="p-4 sm:p-6 lg:p-8 max-w-5xl mx-auto space-y-6
                pb-[max(1rem,env(safe-area-inset-bottom))]">

        {{-- ═══ Page header ═══ --}}
        <div class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                    rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                    shadow-sm p-5 sm:p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
                <div class="flex items-center gap-4 min-w-0">
                    <div class="hidden sm:flex w-12 h-12 rounded-xl bg-primary-600 text-white items-center justify-center shrink-0 shadow-md shadow-primary-500/30">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="w-5 h-px bg-primary-600"></span>
                            <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Settings · Business</span>
                        </div>
                        <h1 class="font-display text-2xl sm:text-3xl font-semibold text-gray-900 dark:text-white tracking-tight leading-tight">
                            Documents
                        </h1>
                        <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Upload renewals before they expire. Pending renewals are reviewed by the platform admin.
                        </p>
                    </div>
                </div>

                <a href="{{ route('tenant.settings.index') }}" wire:navigate
                   class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                          transition-all duration-200 active:scale-95 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent] hover:bg-gray-50 dark:hover:bg-gray-700
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 shrink-0">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span>Business Settings</span>
                </a>
            </div>
        </div>

        {{-- ═══ Flash ═══ --}}
        @if(session()->has('message'))
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
                        class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 rounded-md text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/10
                               transition-all duration-200 active:scale-95 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                        aria-label="Dismiss">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        {{-- ═══ Empty state ═══ --}}
        @if(! $this->application)
            <div class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                        rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                        p-12 text-center">
                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl border border-gray-200/70 dark:border-gray-700/60 bg-white/80 dark:bg-gray-800/60 text-gray-400 dark:text-gray-500">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <p class="text-base font-semibold text-gray-900 dark:text-white">No documents on file</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Documents become available after your business application is approved.
                </p>
            </div>
        @else
            {{-- ═══ Documents list ═══ --}}
            <div class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                        rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                        shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100/80 dark:border-white/[0.04] flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Tracked Documents
                    </h2>
                </div>

                <div class="divide-y divide-gray-100/80 dark:divide-white/[0.04]">
                    @foreach($this->documentRows as $row)
                        <div wire:key="doc-row-{{ $row['type'] }}"
                             class="flex flex-col sm:flex-row sm:items-center gap-4 p-5">

                            <div class="flex-1 min-w-0 flex items-start gap-3">
                                <span class="shrink-0 inline-flex items-center justify-center size-10 rounded-lg
                                             bg-gray-100 dark:bg-gray-700/40 text-gray-500 dark:text-gray-400">
                                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-bold text-gray-900 dark:text-white truncate">
                                            {{ $row['label'] }}
                                        </p>
                                        @if($row['required'])
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-rose-500 dark:text-rose-400">
                                                Required
                                            </span>
                                        @endif
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 mt-1.5">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border {{ $row['status_color'] }}">
                                            <span class="w-1 h-1 rounded-full bg-current"></span>
                                            {{ $row['status_label'] }}
                                        </span>

                                        @if($row['has_pending_renewal'])
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                                                         bg-blue-50 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-500/30">
                                                <span class="w-1 h-1 rounded-full bg-blue-500 animate-pulse motion-reduce:animate-none"></span>
                                                Renewal pending review
                                            </span>
                                        @endif
                                    </div>

                                    @if($row['latest'])
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1.5 truncate">
                                            {{ $row['latest']->original_filename }}
                                            @if($row['latest']->issued_at)
                                                · issued {{ $row['latest']->issued_at->format('M j, Y') }}
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0 self-start sm:self-center">
                                @if($row['latest'])
                                    <a href="{{ $this->docUrl($row['latest']) }}"
                                       target="_blank" rel="noopener noreferrer"
                                       class="inline-flex items-center justify-center gap-1.5 h-11 px-3.5 rounded-lg
                                              border border-gray-300 dark:border-gray-600
                                              bg-white dark:bg-gray-800
                                              text-gray-700 dark:text-gray-200
                                              text-xs font-semibold
                                              transition-all duration-200 active:scale-95
                                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                              hover:bg-gray-50 dark:hover:bg-gray-700
                                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <span>View</span>
                                    </a>
                                @endif

                                @if($row['has_pending_renewal'])
                                    <span class="inline-flex items-center justify-center gap-1.5 h-11 px-4 rounded-lg
                                                 border border-gray-200 dark:border-gray-700
                                                 bg-gray-50 dark:bg-gray-900/40
                                                 text-gray-400 dark:text-gray-500
                                                 text-xs font-semibold cursor-not-allowed shrink-0"
                                          title="A renewal for this document is already under review">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <span class="hidden sm:inline">Locked</span>
                                    </span>
                                @else
                                    <a href="{{ route('tenant.documents.renew', ['type' => $row['type']]) }}"
                                       wire:navigate
                                       class="inline-flex items-center justify-center gap-1.5 h-11 px-4 rounded-lg
                                              bg-primary-600 hover:bg-primary-700 text-white
                                              text-xs font-semibold shadow-sm shrink-0
                                              transition-all duration-200 active:scale-95
                                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                        </svg>
                                        <span>{{ $row['latest'] ? 'Renew' : 'Upload' }}</span>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- ═══ Info card ═══ --}}
            <div class="rounded-2xl border border-amber-200/70 dark:border-amber-500/25
                        bg-amber-50/60 dark:bg-amber-500/[0.05] p-5">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="min-w-0 text-sm text-amber-900 dark:text-amber-200">
                        <p class="font-semibold">How renewals work</p>
                        <p class="mt-1 text-xs leading-relaxed text-amber-800/90 dark:text-amber-300/85">
                            Upload a new version of any document before it expires. The platform admin reviews it — while a
                            renewal is pending, the same document can't be re-uploaded until they approve or request a change.
                        </p>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>