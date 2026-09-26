{{-- resources/views/tenant/pages/documents/⚡renew.blade.php --}}
<?php

use App\Models\BusinessApplication;
use App\Models\BusinessDocument;
use App\Models\Tenant;
use App\Services\BusinessApplicationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Layout('tenant.layouts.app')]
#[Title('Renew Document')]
class extends Component
{
    use WithFileUploads;

    public ?Tenant $tenant = null;

    #[Locked]
    public string $documentType = '';

    #[Locked]
    public ?int $applicationId = null;

    public $file = null;
    public string $document_number = '';
    public string $issued_at = '';
    public string $expires_at = '';

    public function mount(string $type): void
    {
        $user = Auth::user();

        abort_unless($user, 403);
        abort_unless($user->tenant_id, 403, 'No business is linked to your account.');
        abort_unless(
            $user->hasAnyRole(['admin', 'super-admin']),
            403,
            'Only business owners can upload renewals.'
        );

        $this->tenant = $user->tenant;

        $allowed = array_merge(
            BusinessApplication::REQUIRED_DOCUMENTS,
            BusinessApplication::OPTIONAL_DOCUMENTS,
        );
        abort_unless(in_array($type, $allowed, true), 404);

        $this->documentType = $type;

        $application = BusinessApplication::query()
            ->where('approved_tenant_id', $this->tenant->id)
            ->latest('id')
            ->first();

        abort_unless($application, 404, 'No approved application found.');
        $this->applicationId = $application->id;

        $previous = $application->documents()
            ->where('document_type', $type)
            ->latest('created_at')
            ->first();

        if ($previous) {
            $this->document_number = (string) ($previous->document_number ?? '');
            $this->issued_at       = $previous->issued_at?->format('Y-m-d') ?? '';
            $this->expires_at      = $previous->expires_at?->format('Y-m-d') ?? '';
        }

        $this->assertCanRenew($type);
    }

    public function hydrate(): void
    {
        $user = Auth::user();

        abort_unless($user, 403);
        abort_unless($user->tenant_id, 403);
        abort_unless($user->hasAnyRole(['admin', 'super-admin']), 403);
        abort_unless($this->tenant?->id === $user->tenant_id, 403);
    }

    protected function assertCanRenew(string $type): void
    {
        $application = $this->application;
        if (! $application) {
            return;
        }

        $hasPending = $application->documents()
            ->where('document_type', $type)
            ->where('is_renewal', true)
            ->where('verification_status', BusinessDocument::STATUS_PENDING)
            ->exists();

        abort_if($hasPending, 403, 'A renewal for this document is already under review.');
    }

    #[Computed]
    public function application(): ?BusinessApplication
    {
        if (! $this->applicationId) {
            return null;
        }

        return BusinessApplication::find($this->applicationId);
    }

    #[Computed]
    public function label(): string
    {
        return BusinessApplication::DOCUMENT_LABELS[$this->documentType] ?? $this->documentType;
    }

    #[Computed]
    public function previous(): ?BusinessDocument
    {
        $application = $this->application;
        if (! $application) {
            return null;
        }

        return $application->documents()
            ->where('document_type', $this->documentType)
            ->latest('created_at')
            ->first();
    }

    /**
     * Rule J — relative /storage/ URL. asset() prefixes APP_URL, which
     * may not match the current host (envkit.net vs 127.0.0.1).
     */
    public function previousUrl(): ?string
    {
        $prev = $this->previous;
        if (! $prev) {
            return null;
        }

        $path = $prev->watermarked_path ?: $prev->stored_path;

        return $path ? '/storage/' . ltrim($path, '/') : null;
    }

    public function submit(BusinessApplicationService $service): void
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $this->assertCanRenew($this->documentType);

        $validated = $this->validate([
            'file'            => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issued_at'       => ['nullable', 'date'],
            'expires_at'      => ['nullable', 'date', 'after_or_equal:issued_at'],
        ], [
            'file.mimes'           => 'File must be a PDF, JPEG, PNG, or WebP.',
            'expires_at.after_or_equal' => 'Expiry date must be on or after the issue date.',
        ]);

        try {
            $document = $service->attachDocument(
                $this->application,
                $user,
                $this->documentType,
                $this->file,
                [
                    'document_number' => $validated['document_number'] ?? null,
                    'issued_at'       => $validated['issued_at']       ?: null,
                    'expires_at'      => $validated['expires_at']      ?: null,
                ],
            );

            $document->update(['is_renewal' => true]);
        } catch (\Throwable $e) {
            Log::error('Document renewal submission failed', [
                'tenant_id'     => $this->tenant->id,
                'document_type' => $this->documentType,
                'error'         => $e->getMessage(),
            ]);

            $this->addError('file', 'Upload failed. Please try again.');
            return;
        }

        session()->flash('message', 'Renewal submitted — the platform admin will review it shortly.');
        $this->redirect(route('tenant.documents.index'), navigate: true);
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

    <div class="p-4 sm:p-6 lg:p-8 max-w-3xl mx-auto space-y-6
                pb-[max(1rem,env(safe-area-inset-bottom))]">

        {{-- ═══ Back link ═══ --}}
        <a href="{{ route('tenant.documents.index') }}" wire:navigate
           class="inline-flex items-center gap-2 text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400
                  transition-all duration-200 active:scale-95
                  py-2.5 -my-2.5 px-1 -mx-1 rounded
                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            <span>All documents</span>
        </a>

        {{-- ═══ Page header ═══ --}}
        <div class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                    rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                    shadow-sm p-5 sm:p-6">
            <div class="flex items-center gap-4 min-w-0">
                <div class="hidden sm:flex w-12 h-12 rounded-xl bg-primary-600 text-white items-center justify-center shrink-0 shadow-md shadow-primary-500/30">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">
                            Renew Document
                        </span>
                    </div>
                    <h1 class="font-display text-2xl sm:text-3xl font-semibold text-gray-900 dark:text-white tracking-tight leading-tight">
                        {{ $this->label }}
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Upload a new version of this document. It'll be reviewed by the platform admin.
                    </p>
                </div>
            </div>
        </div>

        {{-- ═══ Previous version ═══ --}}
        @if($this->previous)
            <div class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                        rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                        shadow-sm p-5">
                <div class="flex items-center gap-3 mb-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Current Version
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ $this->previousUrl() }}" target="_blank" rel="noopener noreferrer"
                       aria-label="View current version"
                       class="shrink-0 inline-flex items-center justify-center size-11 sm:size-10 rounded-lg
                              bg-gray-100 dark:bg-gray-700/40 text-gray-500 dark:text-gray-400
                              hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors active:scale-95
                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </a>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                            {{ $this->previous->original_filename }}
                        </p>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 tabular-nums">
                            @if($this->previous->expires_at)
                                Expires {{ $this->previous->expires_at->format('M j, Y') }}
                                @if($this->previous->expires_at->isPast())
                                    · <span class="text-rose-500 dark:text-rose-400 font-semibold">expired</span>
                                @endif
                            @else
                                No expiry on file
                            @endif
                        </p>
                    </div>
                    <a href="{{ $this->previousUrl() }}" target="_blank" rel="noopener noreferrer"
                       class="shrink-0 inline-flex items-center text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline
                              py-2.5 -my-2.5 px-1 -mx-1 rounded
                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        View
                    </a>
                </div>
            </div>
        @endif

        {{-- ═══ Renew form ═══ --}}
        <form wire:submit="submit" class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                                         rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                                         shadow-sm p-5 sm:p-6 space-y-5">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    New Version
                </h2>
            </div>

            {{-- File — custom-styled upload zone matching property pages --}}
            <div>
                <label for="renew-file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Document File <span class="text-rose-500">*</span>
                </label>

                <label for="renew-file"
                       class="group relative flex items-center gap-3 p-4 rounded-xl cursor-pointer min-h-[64px]
                              border-2 border-dashed border-gray-300 dark:border-gray-600
                              bg-gray-50 dark:bg-gray-900/40
                              hover:border-primary-400 dark:hover:border-primary-500/60
                              hover:bg-primary-50/40 dark:hover:bg-primary-500/[0.06]
                              transition-all duration-200
                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                              focus-within:ring-2 focus-within:ring-primary-500/50">
                    <span class="shrink-0 inline-flex items-center justify-center size-10 rounded-lg
                                 bg-white dark:bg-gray-800 text-gray-400 dark:text-gray-500
                                 group-hover:text-primary-600 dark:group-hover:text-primary-400
                                 shadow-sm transition-colors">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $file ? $file->getClientOriginalName() : 'Click to choose a file' }}
                        </span>
                        <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            PDF, JPEG, PNG, or WebP · max 10 MB
                        </span>
                    </span>
                    <input id="renew-file"
                           type="file"
                           wire:model="file"
                           accept="application/pdf,image/jpeg,image/png,image/webp"
                           class="sr-only">
                </label>

                @error('file')
                    <p class="mt-2 text-xs text-rose-500 dark:text-rose-400" role="alert">{{ $message }}</p>
                @enderror

                <div wire:loading wire:target="file" class="mt-2 inline-flex items-center gap-1.5 text-[11px] font-medium text-primary-600 dark:text-primary-400">
                    <svg class="animate-spin w-3 h-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Uploading…
                </div>
            </div>

            {{-- Document number --}}
            <div>
                <label for="renew-number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Document Number <span class="text-gray-400 dark:text-gray-500 font-normal">(optional)</span>
                </label>
                <input id="renew-number"
                       type="text"
                       wire:model="document_number"
                       maxlength="100"
                       class="input w-full"
                       placeholder="e.g. DTI-2026-123456">
                @error('document_number') <p class="mt-1 text-xs text-rose-500 dark:text-rose-400" role="alert">{{ $message }}</p> @enderror
            </div>

            {{-- Dates --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="renew-issued" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Issue Date
                    </label>
                    <input id="renew-issued"
                           type="date"
                           wire:model="issued_at"
                           class="input w-full">
                    @error('issued_at') <p class="mt-1 text-xs text-rose-500 dark:text-rose-400" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="renew-expires" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Expiry Date
                    </label>
                    <input id="renew-expires"
                           type="date"
                           wire:model="expires_at"
                           class="input w-full">
                    @error('expires_at') <p class="mt-1 text-xs text-rose-500 dark:text-rose-400" role="alert">{{ $message }}</p> @enderror
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                        Setting this helps us remind you before it expires.
                    </p>
                </div>
            </div>

            {{-- Submit --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3 pt-4 border-t border-gray-100 dark:border-white/[0.04]">
                <a href="{{ route('tenant.documents.index') }}" wire:navigate
                   class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl
                          border border-gray-300 dark:border-gray-600
                          bg-white dark:bg-gray-800
                          text-gray-700 dark:text-gray-200
                          text-sm font-semibold
                          transition-all duration-200 active:scale-95
                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                          hover:bg-gray-50 dark:hover:bg-gray-700
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    Cancel
                </a>
                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="submit"
                        class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl
                               bg-primary-600 hover:bg-primary-700 text-white
                               text-sm font-semibold shadow-sm
                               transition-all duration-200 active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                               disabled:opacity-60 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="submit" class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <span>Submit Renewal</span>
                    </span>
                    <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span>Submitting…</span>
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>