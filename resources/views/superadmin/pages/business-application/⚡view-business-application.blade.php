<?php

use App\Models\BusinessApplication;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('superadmin.layouts.app')]
#[Title('Business Applications')]
class extends Component
{
    use WithPagination;

    #[Url(keep: true)]
    public string $search = '';

    #[Url(keep: true)]
    public string $status = '';

    #[Url(keep: true)]
    public int $perPage = 20;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->resetPage();
    }

    /** @return array<string, string> */
    #[Computed]
    public function statusLabels(): array
    {
        return BusinessApplication::STATUS_LABELS;
    }

    #[Computed]
    public function requiredDocumentsCount(): int
    {
        return count(BusinessApplication::REQUIRED_DOCUMENTS);
    }

    #[Computed]
    public function applications()
    {
        return BusinessApplication::query()
            ->with([
                'user:id,name,email,avatar',
                'typeOfTenant:id,type',
            ])
            ->withCount([
                'documents',
                'verifications as mismatches_count' => fn ($q) => $q->where('matched', false),
            ])
            ->when(trim($this->search) !== '', function ($q) {
                $search = trim($this->search);
                $q->where(function ($sub) use ($search) {
                    $sub->where('business_name', 'like', "%{$search}%")
                        ->orWhere('owner_full_name', 'like', "%{$search}%")
                        ->orWhere('contact_email', 'like', "%{$search}%")
                        ->orWhere('business_registration_number', 'like', "%{$search}%")
                        ->orWhere('tin_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%"));
                });
            })
            ->when(array_key_exists($this->status, BusinessApplication::STATUS_LABELS), function ($q) {
                $q->where('status', $this->status);
            })
            ->orderByRaw("FIELD(status, 'pending', 'under_review', 'needs_revision', 'draft', 'approved', 'rejected')")
            ->latest('submitted_at')
            ->paginate($this->perPage);
    }

    #[Computed]
    public function stats()
    {
        return BusinessApplication::query()
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('pending','under_review') THEN 1 ELSE 0 END) as awaiting_review,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            ")
            ->first();
    }
};
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">

    {{-- HEADER --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-800">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                Business Applications
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                Review and process KYB submissions from business owners.
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

    @if (session('error'))
        <div class="mt-6 flex items-center gap-2.5 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
            <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
            </svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- STATS --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm transition-all duration-200 hover:border-primary-500/30">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($this->stats->total ?? 0) }}</p>
                </div>
                <div class="p-2 bg-primary-50 dark:bg-primary-950/50 rounded-xl text-primary-600 dark:text-primary-400 border border-primary-100 dark:border-primary-900/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm transition-all duration-200 hover:border-amber-500/30">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Awaiting Review</p>
                    <p class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($this->stats->awaiting_review ?? 0) }}</p>
                </div>
                <div class="p-2 bg-amber-50 dark:bg-amber-500/10 rounded-xl text-amber-600 dark:text-amber-400 border border-amber-100 dark:border-amber-900/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm transition-all duration-200 hover:border-emerald-500/30">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Approved</p>
                    <p class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($this->stats->approved ?? 0) }}</p>
                </div>
                <div class="p-2 bg-emerald-50 dark:bg-emerald-500/10 rounded-xl text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm transition-all duration-200 hover:border-rose-500/30">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Rejected</p>
                    <p class="mt-2 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($this->stats->rejected ?? 0) }}</p>
                </div>
                <div class="p-2 bg-rose-50 dark:bg-rose-500/10 rounded-xl text-rose-600 dark:text-rose-400 border border-rose-100 dark:border-rose-900/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- FILTER TOOLBAR --}}
    <div class="mt-6 flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between bg-white dark:bg-gray-800/90 p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm">
        <div class="relative flex-1 max-w-md">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input wire:model.live.debounce.300ms="search"
                   type="search"
                   placeholder="Search business, owner, email, TIN, or reg #..."
                   class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2 pl-10 pr-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
        </div>

        <div class="flex items-center gap-3">
            <select wire:model.live="status"
                    class="bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2 px-4 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                <option value="">All statuses</option>
                @foreach ($this->statusLabels as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            @if ($search || $status)
                <button type="button" wire:click="clearFilters"
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded px-2 py-1">
                    Reset
                </button>
            @endif
        </div>
    </div>

    {{-- TABLE --}}
    <div class="mt-6 bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm overflow-hidden relative">
        <div wire:loading.flex wire:target="search,status,gotoPage,nextPage,previousPage,clearFilters"
             class="absolute inset-0 bg-white/60 dark:bg-gray-900/60 backdrop-blur-[1px] z-10 items-center justify-center">
            <svg class="animate-spin h-7 w-7 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50/70 dark:bg-gray-900/50 border-b border-gray-200/80 dark:border-gray-700/80 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">
                    <tr>
                        <th class="px-6 py-4">Applicant</th>
                        <th class="px-6 py-4">Business</th>
                        <th class="px-6 py-4">Documents</th>
                        <th class="px-6 py-4">Submitted</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                    @forelse ($this->applications as $app)
                        @php
                            $statusConfig = match ($app->status) {
                                'pending'        => ['label' => 'Pending',        'classes' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300 border-amber-200 dark:border-amber-500/30'],
                                'under_review'   => ['label' => 'Under Review',   'classes' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300 border-blue-200 dark:border-blue-500/30'],
                                'needs_revision' => ['label' => 'Needs Revision', 'classes' => 'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300 border-orange-200 dark:border-orange-500/30'],
                                'draft'          => ['label' => 'Draft',          'classes' => 'bg-slate-100 text-slate-700 dark:bg-slate-500/15 dark:text-slate-300 border-slate-200 dark:border-slate-500/30'],
                                'approved'       => ['label' => 'Approved',       'classes' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30'],
                                'rejected'       => ['label' => 'Rejected',       'classes' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300 border-rose-200 dark:border-rose-500/30'],
                                default          => ['label' => ucfirst($app->status), 'classes' => 'bg-gray-100 text-gray-700 dark:bg-gray-500/15 dark:text-gray-300 border-gray-200 dark:border-gray-500/30'],
                            };

                            $docsLabel     = $app->documents_count . '/' . $this->requiredDocumentsCount;
                            $mismatchCount = (int) $app->mismatches_count;
                        @endphp
                        <tr wire:key="app-{{ $app->id }}" class="hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    @if ($app->user?->avatar)
                                        <img src="{{ asset('storage/' . $app->user->avatar) }}"
                                             alt="{{ $app->user->name }}"
                                             class="w-9 h-9 rounded-full object-cover shrink-0">
                                    @else
                                        <div class="w-9 h-9 rounded-full bg-primary-600 text-white flex items-center justify-center text-xs font-bold shrink-0">
                                            {{ strtoupper(substr($app->user?->name ?? '?', 0, 1)) }}
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                            {{ $app->user?->name ?? '—' }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                            {{ $app->user?->email ?? '—' }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate max-w-[220px]">
                                    {{ $app->business_name ?? '—' }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[220px]">
                                    {{ $app->typeOfTenant?->type ?? 'Uncategorized' }}
                                </p>
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-700 dark:text-gray-300">
                                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        {{ $docsLabel }}
                                    </span>
                                    @if ($mismatchCount > 0)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30">
                                            {{ $mismatchCount }} {{ $mismatchCount === 1 ? 'mismatch' : 'mismatches' }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                @if ($app->submitted_at)
                                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $app->submitted_at->format('M j, Y') }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $app->submitted_at->diffForHumans() }}</p>
                                @else
                                    <span class="text-xs text-gray-400 dark:text-gray-500 italic">Not submitted</span>
                                @endif
                            </td>

                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border {{ $statusConfig['classes'] }}">
                                    {{ $statusConfig['label'] }}
                                </span>
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end">
                                    <a href="{{ route('superadmin.business-applications.show', $app) }}" wire:navigate
                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg text-primary-700 dark:text-primary-400 bg-primary-50 dark:bg-primary-950/50 hover:bg-primary-100 dark:hover:bg-primary-950 border border-primary-100 dark:border-primary-900/50 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                        Review
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                                    <div class="p-3 bg-gray-100 dark:bg-gray-800 rounded-2xl mb-3 text-gray-400">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                        @if ($search || $status)
                                            No applications match your filters
                                        @else
                                            No applications yet
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        @if ($search || $status)
                                            Try a different search term or status.
                                        @else
                                            KYB submissions from business owners will appear here.
                                        @endif
                                    </p>
                                    @if ($search || $status)
                                        <button type="button" wire:click="clearFilters"
                                                class="mt-3 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline">
                                            Clear filters
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->applications->hasPages())
            <div class="px-6 py-4 border-t border-gray-200/80 dark:border-gray-700/80 bg-gray-50/50 dark:bg-gray-900/50">
                {{ $this->applications->links() }}
            </div>
        @endif
    </div>
</div>