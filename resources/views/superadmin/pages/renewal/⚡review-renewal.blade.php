{{-- resources/views/superadmin/pages/renewal/⚡review-renewal.blade.php --}}
<?php

use App\Models\BusinessDocument;
use App\Models\UserNotification;
use App\Services\UserNotificationService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new
    #[Layout('superadmin::layouts.app')]
    #[Title('Review renewal')]
class extends Component {
    #[Locked]
    public int $documentId;

    public string $rejectionNotes = '';

    public function mount(BusinessDocument $document): void
    {
        if (! $document->is_renewal) {
            abort(404);
        }

        $this->documentId = $document->id;
    }

    #[Computed]
    public function document(): BusinessDocument
    {
        return BusinessDocument::with([
            'application.user',
            'renewalReviewer',
        ])->findOrFail($this->documentId);
    }

    #[Computed]
    public function previousVersion(): ?BusinessDocument
    {
        $doc = $this->document;

        return BusinessDocument::query()
            ->kyb()
            ->where('business_application_id', $doc->business_application_id)
            ->where('document_type', $doc->document_type)
            ->where('verification_status', BusinessDocument::STATUS_VERIFIED)
            ->latest('created_at')
            ->first();
    }

    #[Computed]
    public function documentTypeLabel(): string
    {
        return (string) str($this->document->document_type)->replace('_', ' ')->title();
    }

    #[Computed]
    public function isPending(): bool
    {
        return $this->document->verification_status === BusinessDocument::STATUS_PENDING;
    }

    #[Computed]
    public function renewalPreviewKind(): string
    {
        return $this->previewKindFor($this->document);
    }

    #[Computed]
    public function previousPreviewKind(): string
    {
        return $this->previewKindFor($this->previousVersion);
    }

    /**
     * Relative public URL for the document file. Prefers the watermarked
     * version when one exists, falls back to the raw upload when the
     * watermark hasn't been generated yet.
     */
    public function previewPath(?BusinessDocument $doc): ?string
    {
        if (! $doc) {
            return null;
        }

        $path = $doc->watermarked_path ?: $doc->stored_path;

        return $path ? '/storage/' . ltrim($path, '/') : null;
    }

    public function hasWatermark(?BusinessDocument $doc): bool
    {
        return $doc !== null && ! empty($doc->watermarked_path);
    }

    private function previewKindFor(?BusinessDocument $doc): string
    {
        if (! $doc) {
            return 'none';
        }

        $mime = (string) ($doc->mime_type ?? '');

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if ($mime === 'application/pdf') {
            return 'pdf';
        }

        return 'file';
    }

    public function approve(UserNotificationService $notifications): void
    {
        $doc = $this->document;

        if ($doc->verification_status !== BusinessDocument::STATUS_PENDING) {
            $this->addError('review', 'This renewal has already been reviewed.');
            return;
        }

        $doc->update([
            'verification_status' => BusinessDocument::STATUS_VERIFIED,
            'verification_notes'  => null,
            'renewal_reviewed_by' => auth()->id(),
            'renewal_reviewed_at' => now(),
        ]);

        $this->notifyApplicant($notifications, $doc, approved: true);

        \Illuminate\Support\Facades\Cache::forget('superadmin.sidebar.pending_renewals');

        session()->flash('status', 'Renewal approved.');

        $this->redirectRoute('superadmin.renewals.index', navigate: true);
    }

    public function reject(UserNotificationService $notifications): void
    {
        $this->validate(
            ['rejectionNotes' => 'required|string|min:5|max:1000'],
            [],
            ['rejectionNotes' => 'rejection notes'],
        );

        $doc = $this->document;

        if ($doc->verification_status !== BusinessDocument::STATUS_PENDING) {
            $this->addError('review', 'This renewal has already been reviewed.');
            return;
        }

        $doc->update([
            'verification_status' => BusinessDocument::STATUS_REJECTED,
            'verification_notes'  => $this->rejectionNotes,
            'renewal_reviewed_by' => auth()->id(),
            'renewal_reviewed_at' => now(),
        ]);

        $this->notifyApplicant($notifications, $doc, approved: false);

        \Illuminate\Support\Facades\Cache::forget('superadmin.sidebar.pending_renewals');

        session()->flash('status', 'Renewal rejected.');

        $this->redirectRoute('superadmin.renewals.index', navigate: true);
    }

    protected function notifyApplicant(
        UserNotificationService $notifications,
        BusinessDocument $doc,
        bool $approved,
    ): void {
        $applicant = $doc->application?->user;

        if (! $applicant) {
            return;
        }

        $label = (string) str($doc->document_type)->replace('_', ' ')->title();

        $notifications->notify($applicant, [
            'type'    => $approved ? 'renewal_approved' : 'renewal_rejected',
            'scope'   => UserNotification::SCOPE_BUSINESS,
            'title'   => $approved
                ? "{$label} renewal approved"
                : "{$label} renewal needs attention",
            'message' => $approved
                ? "Your {$label} renewal was approved."
                : "Your {$label} renewal was rejected. Reason: {$doc->verification_notes}",
            'url'     => route('tenant.documents.index'),
            'icon'    => $approved ? 'check-circle' : 'x-circle',
            'color'   => $approved ? 'emerald' : 'rose',
        ]);
    }
};
?>

<div
    class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8"
    x-data="{ lightbox: null, lightboxLabel: '' }"
    x-on:keydown.escape.window="lightbox = null"
>

    {{-- Back --}}
    <a
        href="{{ route('superadmin.renewals.index') }}"
        wire:navigate
        class="inline-flex h-11 items-center gap-2 rounded-lg px-2 text-sm font-medium text-slate-600 transition touch-manipulation [-webkit-tap-highlight-color:transparent] hover:text-slate-900 dark:text-slate-300 dark:hover:text-white sm:h-9"
    >
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Back to queue
    </a>

    {{-- Header --}}
    <header>
        <p class="text-xs font-semibold uppercase tracking-widest text-amber-500">Renewal review</p>
        <h1 class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">
            {{ $this->documentTypeLabel }}
        </h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            {{ $this->document->application?->user?->name ?? 'Unknown applicant' }}
            <span aria-hidden="true">·</span>
            Submitted {{ $this->document->created_at?->format('M j, Y') }}
        </p>
    </header>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    @error('review')
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
            {{ $message }}
        </div>
    @enderror

    <div class="grid gap-4 lg:grid-cols-2">

        {{-- ─── Previous verified version ─── --}}
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
            <header class="flex items-center justify-between border-b border-slate-200 px-5 py-3 dark:border-slate-700">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Previous version</h2>
                <div class="flex items-center gap-2">
                    @if ($this->hasWatermark($this->previousVersion))
                        <span class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">
                            Watermarked
                        </span>
                    @endif
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                        Verified
                    </span>
                </div>
            </header>

            @if ($this->previousVersion)

                <div class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-950/50">
                    @php $prevUrl = $this->previewPath($this->previousVersion); @endphp

                    @if ($this->previousPreviewKind === 'image' && $prevUrl)
                        <div class="relative flex aspect-[4/3] items-center justify-center overflow-hidden">
                            <img
                                src="{{ $prevUrl }}"
                                alt="Previous {{ $this->documentTypeLabel }}"
                                loading="lazy"
                                decoding="async"
                                class="max-h-full max-w-full cursor-zoom-in object-contain transition hover:scale-[1.01]"
                                x-on:click="lightbox = @js($prevUrl); lightboxLabel = @js('Previous version')"
                            />
                        </div>

                    @elseif ($this->previousPreviewKind === 'pdf' && $prevUrl)
                        <iframe
                            src="{{ $prevUrl }}"
                            title="Previous PDF"
                            class="h-[420px] w-full"
                            loading="lazy"
                        ></iframe>

                    @elseif ($this->previousPreviewKind === 'file')
                        <div class="flex aspect-[4/3] flex-col items-center justify-center gap-2 px-4 text-center">
                            <svg class="size-10 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6"/>
                            </svg>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ strtoupper(pathinfo($this->previousVersion->original_filename, PATHINFO_EXTENSION) ?: 'FILE') }} — preview unavailable
                            </p>
                        </div>
                    @endif
                </div>

                <dl class="space-y-2 p-5 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">Filename</dt>
                        <dd class="truncate text-right font-medium text-slate-900 dark:text-white">
                            {{ $this->previousVersion->original_filename }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">Uploaded</dt>
                        <dd class="tabular-nums text-right text-slate-700 dark:text-slate-200">
                            {{ $this->previousVersion->created_at?->format('M j, Y') }}
                        </dd>
                    </div>
                    @if ($this->previousVersion->expires_at)
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500 dark:text-slate-400">Expires</dt>
                            <dd class="tabular-nums text-right text-slate-700 dark:text-slate-200">
                                {{ $this->previousVersion->expires_at->format('M j, Y') }}
                            </dd>
                        </div>
                    @endif

                    @if ($prevUrl)
                        <div class="flex justify-end pt-2">
                            <a
                                href="{{ $prevUrl }}"
                                download="{{ $this->previousVersion->original_filename }}"
                                class="inline-flex h-11 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 transition touch-manipulation [-webkit-tap-highlight-color:transparent] hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-600 dark:hover:bg-slate-800 sm:h-9"
                            >
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m7 10 5 5 5-5"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15V3"/>
                                </svg>
                                Download
                            </a>
                        </div>
                    @endif
                </dl>

            @else
                <div class="flex aspect-[4/3] flex-col items-center justify-center gap-2 px-4 text-center">
                    <svg class="size-10 text-slate-300 dark:text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/>
                    </svg>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        No previous verified version on file.
                    </p>
                </div>
            @endif
        </section>

        {{-- ─── Renewal submission ─── --}}
        <section class="overflow-hidden rounded-2xl border-2 border-amber-300 bg-white dark:border-amber-500/40 dark:bg-slate-900">
            <header class="flex items-center justify-between border-b border-amber-200 bg-amber-50/60 px-5 py-3 dark:border-amber-500/30 dark:bg-amber-500/10">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Renewal submission</h2>
                <div class="flex items-center gap-2">
                    @if ($this->hasWatermark($this->document))
                        <span class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">
                            Watermarked
                        </span>
                    @endif
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-500/20 dark:text-amber-200">
                        {{ ucfirst((string) $this->document->verification_status) }}
                    </span>
                </div>
            </header>

            <div class="border-b border-amber-200 bg-slate-50 dark:border-amber-500/30 dark:bg-slate-950/50">
                @php $renewUrl = $this->previewPath($this->document); @endphp

                @if ($this->renewalPreviewKind === 'image' && $renewUrl)
                    <div class="relative flex aspect-[4/3] items-center justify-center overflow-hidden">
                        <img
                            src="{{ $renewUrl }}"
                            alt="Renewal {{ $this->documentTypeLabel }}"
                            loading="lazy"
                            decoding="async"
                            class="max-h-full max-w-full cursor-zoom-in object-contain transition hover:scale-[1.01]"
                            x-on:click="lightbox = @js($renewUrl); lightboxLabel = @js('Renewal submission')"
                        />
                        <span class="pointer-events-none absolute right-3 top-3 rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white shadow-sm">
                            New
                        </span>
                    </div>

                @elseif ($this->renewalPreviewKind === 'pdf' && $renewUrl)
                    <iframe
                        src="{{ $renewUrl }}"
                        title="Renewal PDF"
                        class="h-[420px] w-full"
                        loading="lazy"
                    ></iframe>

                @elseif ($this->renewalPreviewKind === 'file')
                    <div class="flex aspect-[4/3] flex-col items-center justify-center gap-2 px-4 text-center">
                        <svg class="size-10 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6"/>
                        </svg>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ strtoupper(pathinfo($this->document->original_filename, PATHINFO_EXTENSION) ?: 'FILE') }} — preview unavailable
                        </p>
                    </div>
                @endif
            </div>

            <dl class="space-y-2 p-5 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500 dark:text-slate-400">Filename</dt>
                    <dd class="truncate text-right font-medium text-slate-900 dark:text-white">
                        {{ $this->document->original_filename }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500 dark:text-slate-400">Uploaded</dt>
                    <dd class="tabular-nums text-right text-slate-700 dark:text-slate-200">
                        {{ $this->document->created_at?->format('M j, Y') }}
                    </dd>
                </div>
                @if ($this->document->expires_at)
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">Expires</dt>
                        <dd class="tabular-nums text-right text-slate-700 dark:text-slate-200">
                            {{ $this->document->expires_at->format('M j, Y') }}
                        </dd>
                    </div>
                @endif
                @if ($this->document->document_number)
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">Document №</dt>
                        <dd class="truncate text-right text-slate-700 dark:text-slate-200">
                            {{ $this->document->document_number }}
                        </dd>
                    </div>
                @endif

                @if ($renewUrl)
                    <div class="flex justify-end pt-2">
                        <a
                            href="{{ $renewUrl }}"
                            download="{{ $this->document->original_filename }}"
                            class="inline-flex h-11 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 transition touch-manipulation [-webkit-tap-highlight-color:transparent] hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-600 dark:hover:bg-slate-800 sm:h-9"
                        >
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="m7 10 5 5 5-5"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15V3"/>
                            </svg>
                            Download
                        </a>
                    </div>
                @endif
            </dl>
        </section>
    </div>

    {{-- Decision / history.
         Both action buttons are type="button" with explicit $wire calls.
         The previous version had a wire:submit="approve" on the form AND
         an Approve button with type="submit" + x-on:click.prevent — the
         form's submit handler was dead code, but any future Enter-key
         path inside the form would have submitted and approved the
         document without arming. Removing the form submit handler closes
         that footgun. --}}
    @if ($this->isPending)
        <div
            class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
            x-data="{ armed: null, armTimer: null }"
            x-init="$watch('armed', v => { clearTimeout(armTimer); if (v) armTimer = setTimeout(() => armed = null, 4000) })"
        >
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Decision</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Inspect both documents above before deciding. Notes are required when rejecting.
            </p>

            <label class="mt-4 block">
                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Rejection notes</span>
                <textarea
                    wire:model="rejectionNotes"
                    rows="4"
                    placeholder="Explain what needs to be corrected…"
                    class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-base text-slate-900 placeholder:text-slate-400 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/30 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500 sm:text-sm"
                ></textarea>
                @error('rejectionNotes')
                    <span class="mt-1 block text-xs text-rose-600 dark:text-rose-400">{{ $message }}</span>
                @enderror
            </label>

            <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    wire:loading.attr="disabled"
                    wire:target="reject"
                    x-on:click="if (armed === 'reject') { armed = null; $wire.reject() } else { armed = 'reject' }"
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border px-4 text-sm font-medium transition touch-manipulation [-webkit-tap-highlight-color:transparent] sm:h-9 disabled:opacity-60 disabled:cursor-not-allowed"
                    x-bind:class="armed === 'reject'
                        ? 'border-rose-600 bg-rose-600 text-white'
                        : 'border-rose-300 bg-white text-rose-700 hover:bg-rose-50 dark:border-rose-500/40 dark:bg-transparent dark:text-rose-300 dark:hover:bg-rose-500/10'"
                >
                    <span x-text="armed === 'reject' ? 'Confirm reject' : 'Reject'">Reject</span>
                </button>

                <button
                    type="button"
                    wire:loading.attr="disabled"
                    wire:target="approve"
                    x-on:click="if (armed === 'approve') { armed = null; $wire.approve() } else { armed = 'approve' }"
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border px-4 text-sm font-medium transition touch-manipulation [-webkit-tap-highlight-color:transparent] sm:h-9 disabled:opacity-60 disabled:cursor-not-allowed"
                    x-bind:class="armed === 'approve'
                        ? 'border-emerald-600 bg-emerald-600 text-white'
                        : 'border-emerald-300 bg-white text-emerald-700 hover:bg-emerald-50 dark:border-emerald-500/40 dark:bg-transparent dark:text-emerald-300 dark:hover:bg-emerald-500/10'"
                >
                    <span x-text="armed === 'approve' ? 'Confirm approve' : 'Approve'">Approve</span>
                </button>
            </div>
        </div>
    @else
        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Review history</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500 dark:text-slate-400">Status</dt>
                    <dd class="font-medium text-slate-900 dark:text-white">
                        {{ ucfirst((string) $this->document->verification_status) }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500 dark:text-slate-400">Reviewed by</dt>
                    <dd class="text-right text-slate-700 dark:text-slate-200">
                        {{ $this->document->renewalReviewer?->name ?? '—' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500 dark:text-slate-400">Reviewed at</dt>
                    <dd class="tabular-nums text-right text-slate-700 dark:text-slate-200">
                        {{ $this->document->renewal_reviewed_at?->format('M j, Y g:i A') ?? '—' }}
                    </dd>
                </div>
                @if ($this->document->verification_notes)
                    <div class="pt-2">
                        <dt class="text-slate-500 dark:text-slate-400">Notes</dt>
                        <dd class="mt-1 whitespace-pre-line text-slate-700 dark:text-slate-200">
                            {{ $this->document->verification_notes }}
                        </dd>
                    </div>
                @endif
            </dl>
        </section>
    @endif

    {{-- Lightbox --}}
    <div
        x-cloak
        x-show="lightbox"
        x-transition.opacity.duration.200ms
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/85 p-4 backdrop-blur-sm"
        x-on:click.self="lightbox = null"
        role="dialog"
        aria-modal="true"
        aria-label="Document preview"
    >
        <div class="relative flex max-h-[92vh] max-w-[92vw] flex-col items-center gap-3">
            <div class="flex w-full items-center justify-between gap-4 text-white">
                <span class="text-sm font-medium" x-text="lightboxLabel"></span>
                <button
                    type="button"
                    class="flex size-11 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 touch-manipulation [-webkit-tap-highlight-color:transparent] sm:size-9"
                    x-on:click="lightbox = null"
                    aria-label="Close preview"
                >
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 6 6 18m0-12 12 12"/>
                    </svg>
                </button>
            </div>

            <img
                :src="lightbox"
                alt="Document preview"
                class="max-h-[82vh] max-w-full rounded-lg object-contain shadow-2xl"
            />
        </div>
    </div>

</div>