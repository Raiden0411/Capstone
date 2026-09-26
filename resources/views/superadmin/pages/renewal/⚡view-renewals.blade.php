{{-- resources/views/superadmin/pages/renewal/⚡view-renewals.blade.php --}}
<?php

use App\Models\BusinessDocument;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
    #[Layout('superadmin::layouts.app')]
    #[Title('Renewal review queue')]
class extends Component {
    use WithPagination;

    #[Url(keep: true)]
    public string $filter = 'pending';

    #[Url(keep: true)]
    public string $search = '';

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function counts(): array
    {
        $base = BusinessDocument::query()->renewals();

        return [
            'pending'  => (clone $base)->where('verification_status', BusinessDocument::STATUS_PENDING)->count(),
            'verified' => (clone $base)->where('verification_status', BusinessDocument::STATUS_VERIFIED)->count(),
            'rejected' => (clone $base)->where('verification_status', BusinessDocument::STATUS_REJECTED)->count(),
            'all'      => (clone $base)->count(),
        ];
    }

    #[Computed]
    public function statusChips(): array
    {
        return [
            BusinessDocument::STATUS_PENDING => [
                'label' => 'Pending',
                'chip'  => 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-200',
                'tile'  => 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400',
            ],
            BusinessDocument::STATUS_VERIFIED => [
                'label' => 'Approved',
                'chip'  => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200',
                'tile'  => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400',
            ],
            BusinessDocument::STATUS_REJECTED => [
                'label' => 'Rejected',
                'chip'  => 'bg-rose-100 text-rose-800 dark:bg-rose-500/10 dark:text-rose-200',
                'tile'  => 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400',
            ],
        ];
    }

    #[Computed]
    public function renewals()
    {
        $query = BusinessDocument::query()
            ->renewals()
            ->with(['application.user', 'renewalReviewer'])
            ->latest('created_at');

        if (in_array($this->filter, ['pending', 'verified', 'rejected'], true)) {
            $query->where('verification_status', $this->filter);
        }

        if (trim($this->search) !== '') {
            $search = '%' . trim($this->search) . '%';

            $query->where(function ($q) use ($search) {
                $q->where('original_filename', 'like', $search)
                  ->orWhere('document_type', 'like', $search)
                  ->orWhereHas('application.user', function ($u) use ($search) {
                      $u->where('name', 'like', $search);
                  });
            });
        }

        return $query->paginate(20);
    }

    /**
     * MIME-type-driven icon + label. Used to pick the right SVG in the
     * icon tile and to give the tile a subtle colour when we don't
     * have a real thumbnail to show.
     */
    public function fileKindFor(BusinessDocument $doc): string
    {
        $mime = (string) ($doc->mime_type ?? '');

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if ($mime === 'application/pdf') {
            return 'pdf';
        }

        return 'file';
    }

    public function hasWatermark(BusinessDocument $doc): bool
    {
        return ! empty($doc->watermarked_path);
    }
};
?>

<div class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">

    {{-- Header --}}
    <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-amber-500">Platform</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">Renewal review queue</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Approve or reject document renewals submitted by tenants.
            </p>
        </div>
    </header>

    {{-- Filters + search.
         Filter buttons use aria-pressed (not role="tab"/role="tablist") —
         the incomplete tab pattern previously declared a tablist with no
         matching tabpanel. aria-pressed expresses the same "currently
         selected filter" state without implying a keyboard arrow-key
         contract that isn't implemented. Matches the platform's other
         filter UIs (tourist-spots category pills). --}}
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">

        <div class="flex flex-wrap gap-2" role="group" aria-label="Renewal filters">
            @foreach ([
                'pending'  => ['label' => 'Pending',  'count' => $this->counts['pending']],
                'verified' => ['label' => 'Approved', 'count' => $this->counts['verified']],
                'rejected' => ['label' => 'Rejected', 'count' => $this->counts['rejected']],
                'all'      => ['label' => 'All',      'count' => $this->counts['all']],
            ] as $key => $meta)
                <button
                    type="button"
                    aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                    wire:click="$set('filter', '{{ $key }}')"
                    class="inline-flex h-11 items-center gap-2 rounded-full border px-4 text-sm font-medium transition touch-manipulation [-webkit-tap-highlight-color:transparent] sm:h-9
                        {{ $filter === $key
                            ? 'border-amber-500 bg-amber-50 text-amber-900 dark:bg-amber-500/10 dark:text-amber-200'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-600' }}"
                >
                    <span>{{ $meta['label'] }}</span>
                    <span class="tabular-nums text-xs font-semibold opacity-70">{{ $meta['count'] }}</span>
                </button>
            @endforeach
        </div>

        <label class="relative block w-full lg:w-72">
            <span class="sr-only">Search renewals</span>
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search applicant, type, filename…"
                class="h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-base text-slate-900 placeholder:text-slate-400 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/30 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500 sm:h-9 sm:text-sm"
            />
        </label>
    </div>

    {{-- List.
         `wire:loading` gives the user a visible cue that a filter or
         search change is in flight. The pointer-events-none prevents
         clicking a row that's about to be replaced. --}}
    @if ($this->renewals->isEmpty())
        <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-14 text-center dark:border-slate-700 dark:bg-slate-900">
            <div class="flex size-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-900 dark:text-white">
                    No renewals match this filter
                </p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    @if ($search !== '')
                        Try a different search, or clear the search field to see all renewals.
                    @else
                        Try a different status filter, or check back once tenants submit renewals.
                    @endif
                </p>
            </div>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white transition-opacity duration-200 dark:border-slate-700 dark:bg-slate-900"
             wire:loading.class="opacity-40 pointer-events-none"
             wire:target="filter,search">
            <ul role="list" class="divide-y divide-slate-200 dark:divide-slate-700">
                @foreach ($this->renewals as $renewal)
                    @php
                        $statusMeta = $this->statusChips[$renewal->verification_status] ?? null;
                        $kind       = $this->fileKindFor($renewal);
                    @endphp
                    <li wire:key="renewal-{{ $renewal->id }}">
                        <a
                            href="{{ route('superadmin.renewals.review', $renewal->id) }}"
                            wire:navigate
                            class="group flex items-center gap-3 p-4 transition touch-manipulation [-webkit-tap-highlight-color:transparent] hover:bg-slate-50 dark:hover:bg-slate-800/50 sm:gap-4"
                        >
                            {{-- Status-coloured file-type tile --}}
                            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl
                                        {{ $statusMeta['tile'] ?? 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">
                                @if ($kind === 'image')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                                        <circle cx="9" cy="9" r="2"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                                    </svg>
                                @elseif ($kind === 'pdf')
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 13h1.5a1.5 1.5 0 0 1 0 3H8v2m4-5v5m3-5h1.5M15 13h-1v5"/>
                                    </svg>
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6"/>
                                    </svg>
                                @endif
                            </div>

                            {{-- Info --}}
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">
                                        {{ $renewal->application?->user?->name ?? 'Unknown applicant' }}
                                    </p>
                                    @if ($this->hasWatermark($renewal))
                                        <span class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">
                                            Watermarked
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{ str($renewal->document_type)->replace('_', ' ')->title() }}
                                    <span aria-hidden="true">·</span>
                                    {{ $renewal->original_filename }}
                                </p>
                            </div>

                            {{-- Meta --}}
                            <div class="flex shrink-0 items-center gap-3 sm:gap-4">
                                <span class="hidden text-xs tabular-nums text-slate-500 dark:text-slate-400 sm:inline">
                                    {{ $renewal->created_at?->diffForHumans() }}
                                </span>

                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                                             {{ $statusMeta['chip'] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200' }}">
                                    {{ $statusMeta['label'] ?? ucfirst((string) $renewal->verification_status) }}
                                </span>

                                <svg xmlns="http://www.w3.org/2000/svg"
                                     class="size-4 shrink-0 text-slate-300 transition-colors group-hover:text-slate-500 dark:text-slate-600 dark:group-hover:text-slate-400"
                                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/>
                                </svg>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div>
            {{ $this->renewals->links() }}
        </div>
    @endif
</div>