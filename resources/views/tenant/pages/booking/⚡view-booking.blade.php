{{-- resources/views/tenant/pages/booking/⚡view-booking.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Models\User;
use App\Models\Property;
use App\Scopes\TenantScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new 
#[Layout('tenant.layouts.app')]
#[Title('Active Bookings')]
class extends Component {
    use WithPagination;

    public string $search       = '';
    public string $statusFilter = '';
    public ?string $fromDate    = null;
    public ?string $toDate      = null;
    public ?int   $userFilter   = null;
    public string $sortBy       = 'newest';
    public ?int   $expandedId   = null;

    public function mount()
    {
        $this->cancelOverdueBookings();
        $this->syncPaidBookings();
    }

    protected function cancelOverdueBookings(): void
    {
        $deadline = now()->subMinutes(Booking::PAYMENT_DEADLINE_MINUTES);

        $overdue = Booking::withoutGlobalScope(TenantScope::class)
            ->with('items')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('status', Booking::STATUS_PENDING)
            ->where('created_at', '<=', $deadline)
            ->get();

        if ($overdue->isEmpty()) return;

        $propertyIds = $overdue->flatMap->items->pluck('property_id')->filter()->unique()->toArray();
        if (!empty($propertyIds)) {
            Property::whereIn('id', $propertyIds)->update(['status' => 'available']);
        }

        Booking::withoutGlobalScope(TenantScope::class)
            ->whereIn('id', $overdue->pluck('id'))
            ->update(['status' => Booking::STATUS_CANCELLED]);
    }

    protected function syncPaidBookings(): void
    {
        $pendingBookings = Booking::withoutGlobalScope(TenantScope::class)
            ->with(['payments' => fn($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_RESERVED])
            ->get();

        $confirmIds = [];

        foreach ($pendingBookings as $booking) {
            $totalPaid = $booking->payments->where('payment_status', 'paid')->sum('amount');
            if ($totalPaid >= $booking->total_amount && $booking->total_amount > 0) {
                $confirmIds[] = $booking->id;
            }
        }

        if (!empty($confirmIds)) {
            Booking::withoutGlobalScope(TenantScope::class)
                ->whereIn('id', $confirmIds)
                ->update(['status' => Booking::STATUS_CONFIRMED]);
        }
    }

    public function updatingSearch()       { $this->resetPage(); }
    public function updatingStatusFilter() { $this->resetPage(); }
    public function updatingFromDate()     { $this->resetPage(); }
    public function updatingToDate()       { $this->resetPage(); }
    public function updatingUserFilter()   { $this->resetPage(); }
    public function updatingSortBy()       { $this->resetPage(); }

    public function toggleExpand(int $id)
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function delete(int $id)
    {
        $booking = Booking::withoutGlobalScope(TenantScope::class)
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->firstOrFail();

        $ref = $booking->booking_reference;
        $booking->delete();
        session()->flash('message', "Booking #{$ref} deleted.");
    }

    public function cancelBooking(int $id)
    {
        $booking = Booking::withoutGlobalScope(TenantScope::class)
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->firstOrFail();

        if (in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_RESERVED, Booking::STATUS_CHECKED_IN])) {
            $booking->update(['status' => Booking::STATUS_CANCELLED]);
            session()->flash('message', "Booking #{$booking->booking_reference} has been cancelled.");
        } else {
            session()->flash('error', 'This booking cannot be cancelled.');
        }
    }

    public function clearFilters()
    {
        $this->reset(['search', 'statusFilter', 'fromDate', 'toDate', 'userFilter', 'sortBy']);
        $this->resetPage();
    }

    public function exportCsv()
    {
        $bookings = $this->query()->get();
        $filename = 'bookings_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        return response()->streamDownload(function() use ($bookings) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Reference', 'Guest', 'Email', 'Phone', 'Check-in', 'Check-out', 'Total', 'Paid', 'Balance', 'Status']);
            
            foreach ($bookings as $b) {
                $paid = $b->payments->where('payment_status', 'paid')->sum('amount');
                fputcsv($file, [
                    $b->booking_reference,
                    $b->user->name ?? 'Walk-in',
                    $b->user->email ?? '',
                    $b->user->phone ?? '',
                    $b->check_in?->format('Y-m-d'),
                    $b->check_out?->format('Y-m-d'),
                    number_format($b->total_amount, 2, '.', ''),
                    number_format($paid, 2, '.', ''),
                    number_format($b->total_amount - $paid, 2, '.', ''),
                    $b->status,
                ]);
            }
            fclose($file);
        }, $filename, $headers);
    }

    #[Computed]
    public function users()
    {
        return User::whereHas('bookings', fn($q) => $q->where('tenant_id', Auth::user()->tenant_id))
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    private function query()
    {
        return Booking::withoutGlobalScope(TenantScope::class)
            ->with([
                'user:id,name,email,phone',
                'items.property:id,name',
                'services.service:id,name',
                'payments' => fn($q) => $q->withoutGlobalScope(TenantScope::class),
            ])
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereNotIn('status', [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED])
            ->when($this->search, fn($q) => $q->where(fn($q2) =>
                $q2->where('booking_reference', 'like', '%'.$this->search.'%')
                   ->orWhereHas('user', fn($c) => $c->where('name', 'like', '%'.$this->search.'%'))
            ))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->userFilter, fn($q) => $q->where('user_id', $this->userFilter))
            ->when($this->fromDate && $this->toDate, function ($q) {
                $q->whereBetween('check_in', [
                    Carbon::parse($this->fromDate)->startOfDay(),
                    Carbon::parse($this->toDate)->endOfDay(),
                ]);
            })
            ->when($this->sortBy, function ($q) {
                match ($this->sortBy) {
                    'check_in_asc' => $q->orderBy('check_in', 'asc'),
                    'check_in_desc' => $q->orderBy('check_in', 'desc'),
                    'amount_high' => $q->orderByDesc('total_amount'),
                    'amount_low' => $q->orderBy('total_amount'),
                    default => $q->latest(),
                };
            });
    }

    #[Computed]
    public function bookings()
    {
        return $this->query()->paginate(12);
    }

    #[Computed]
    public function stats()
    {
        $tid = Auth::user()->tenant_id;
        $deadline = now()->subMinutes(Booking::PAYMENT_DEADLINE_MINUTES)->toDateTimeString();
        $today = today()->toDateString();

        $agg = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tid)
            ->selectRaw("
                SUM(CASE WHEN status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as checked_in,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'pending' AND created_at <= ? THEN 1 ELSE 0 END) as overdue,
                SUM(CASE WHEN status IN ('confirmed', 'checked_in', 'completed') THEN total_amount ELSE 0 END) as revenue,
                SUM(CASE WHEN DATE(check_in) = ? AND status != 'cancelled' THEN 1 ELSE 0 END) as today_arrivals,
                SUM(CASE WHEN DATE(check_out) = ? AND status != 'cancelled' THEN 1 ELSE 0 END) as today_departures
            ", [$deadline, $today, $today])
            ->first();

        $availableCount = Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tid)
            ->where('is_active', true)
            ->where('status', 'available')
            ->count();

        return [
            'total'            => $agg->total ?? 0,
            'pending'          => $agg->pending ?? 0,
            'reserved'         => $agg->reserved ?? 0,
            'confirmed'        => $agg->confirmed ?? 0,
            'checked_in'       => $agg->checked_in ?? 0,
            'completed'        => $agg->completed ?? 0,
            'overdue'          => $agg->overdue ?? 0,
            'revenue'          => $agg->revenue ?? 0,
            'today_arrivals'   => $agg->today_arrivals ?? 0,
            'today_departures' => $agg->today_departures ?? 0,
            'available'        => $availableCount,
        ];
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Active Bookings</h1>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('tenant.bookings.history') }}" wire:navigate
               class="btn-secondary text-xs sm:text-sm active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                History
            </a>
            <button type="button" wire:click="$refresh"
                    class="btn-secondary text-xs sm:text-sm active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M4 9a9 9 0 0014.5 4.5M20 20v-5h-5M20 15a9 9 0 00-14.5-4.5"/></svg>
                Refresh
            </button>
            <button type="button" wire:click="exportCsv" wire:loading.attr="disabled"
                    class="btn-secondary text-xs sm:text-sm active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
                <span wire:loading wire:target="exportCsv" class="inline-flex items-center gap-1">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Exporting…
                </span>
            </button>
            <a href="{{ route('tenant.bookings.create') }}" wire:navigate
               class="btn-primary text-xs sm:text-sm active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Reservation
            </a>
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('message') }}
        </div>
    @endif
    @if(session()->has('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md text-sm text-red-700 dark:text-red-300 font-medium flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Stats Cards --}}
    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4">
        @foreach([
            ['Today Arrivals', $s['today_arrivals'], 'bg-emerald-400'],
            ['Today Departures', $s['today_departures'], 'bg-rose-400'],
            ['Pending', $s['pending'], 'bg-amber-400'],
            ['Reserved', $s['reserved'], 'bg-blue-400'],
            ['Confirmed', $s['confirmed'], 'bg-indigo-400'],
            ['Checked In', $s['checked_in'], 'bg-purple-400'],
            ['Completed', $s['completed'], 'bg-slate-400'],
            ['Available', $s['available'], 'bg-teal-400'],
            ['Overdue', $s['overdue'], 'bg-red-400'],
            ['Revenue', '₱'.number_format($s['revenue'], 0), 'bg-brand-400'],
        ] as [$label, $value, $dotClass])
            <div class="card p-4">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $label }}</span>
                <div class="flex items-end justify-between mt-2">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ $value }}</span>
                    <span class="w-2 h-2 rounded-full {{ $dotClass }}"></span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="card p-4 space-y-4">
        <div class="flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search"
                       class="input pl-10"
                       placeholder="Search reference or guest…">
            </div>
            <select wire:model.live="userFilter" class="select w-full sm:w-auto">
                <option value="">All Guests</option>
                @foreach($this->users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-wrap gap-3 items-center">
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400">From:</span>
                <input type="date" wire:model.live="fromDate" class="input !py-2 !w-auto">
                <span class="text-xs text-gray-500 dark:text-gray-400">To:</span>
                <input type="date" wire:model.live="toDate" class="input !py-2 !w-auto">
            </div>
            <select wire:model.live="sortBy" class="select w-full sm:w-auto">
                <option value="newest">Newest Created</option>
                <option value="check_in_asc">Check-in (Earliest)</option>
                <option value="check_in_desc">Check-in (Latest)</option>
                <option value="amount_high">Amount (High to Low)</option>
                <option value="amount_low">Amount (Low to High)</option>
            </select>
            @if($search || $statusFilter || $fromDate || $toDate || $userFilter)
                <button type="button" wire:click="clearFilters"
                        class="btn-secondary text-xs active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Clear
                </button>
            @endif
        </div>
    </div>

    {{-- Status filters + legend --}}
    <div class="flex flex-wrap gap-2 items-center">
        @foreach(['' => 'All', 'pending' => 'Pending', 'reserved' => 'Reserved', 'confirmed' => 'Confirmed', 'checked_in' => 'Checked In'] as $val => $label)
            <button type="button" wire:click="$set('statusFilter','{{ $val }}')" wire:key="pill-{{ $val }}"
                    class="px-4 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wider transition-all duration-200 active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50 border
                           {{ $statusFilter === $val ? 'bg-primary-600 border-primary-600 text-white shadow-md' : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400' }}">
                {{ $label }}
            </button>
        @endforeach
        @if($s['overdue'] > 0)
            <div class="px-4 py-1.5 rounded-full bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-300 text-xs font-semibold uppercase tracking-wider flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                {{ $s['overdue'] }} Overdue
            </div>
        @endif
    </div>

    {{-- Status legend --}}
    <div class="flex flex-wrap gap-4">
        <span class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400"><span class="w-2 h-2 rounded-full bg-amber-400"></span>Pending</span>
        <span class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400"><span class="w-2 h-2 rounded-full bg-blue-400"></span>Reserved</span>
        <span class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400"><span class="w-2 h-2 rounded-full bg-indigo-400"></span>Confirmed</span>
        <span class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400"><span class="w-2 h-2 rounded-full bg-purple-400"></span>Checked In</span>
        <span class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400"><span class="w-2 h-2 rounded-full bg-slate-400"></span>Completed</span>
        <span class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400"><span class="w-2 h-2 rounded-full bg-red-400"></span>Cancelled</span>
    </div>

    {{-- Bookings table --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Booking Ref</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Guest</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 hidden md:table-cell">Visit</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 hidden md:table-cell">Amount</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($this->bookings as $booking)
                        @php
                            $isOverdue = $booking->isOverdue();
                            $isToday = $booking->check_in?->isToday();
                            $days = ($booking->check_in && $booking->check_out) ? max(1, $booking->check_in->diffInDays($booking->check_out)) : 0;
                            $minsLeft = max(0, Booking::PAYMENT_DEADLINE_MINUTES - $booking->created_at->diffInMinutes(now()));
                            $paid = $booking->payments->where('payment_status','paid')->sum('amount');
                            $balance = $booking->total_amount - $paid;
                        @endphp
                        <tr wire:key="row-{{ $booking->id }}"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition cursor-pointer {{ $isOverdue ? 'bg-red-50 dark:bg-red-500/5' : '' }} {{ $expandedId === $booking->id ? 'bg-gray-50 dark:bg-gray-700/50' : '' }}"
                            wire:click="toggleExpand({{ $booking->id }})">
                            <td class="px-6 py-4">
                                <span class="font-mono text-sm font-semibold text-primary-600 dark:text-primary-400">{{ $booking->booking_reference }}</span>
                                @if($isToday)<span class="ml-2 text-[10px] bg-blue-50 dark:bg-blue-500/20 text-primary-600 dark:text-primary-400 px-1.5 py-0.5 rounded-full">Today</span>@endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-blue-50 dark:bg-blue-500/15 flex items-center justify-center text-primary-600 dark:text-primary-400 font-semibold text-sm">
                                        {{ strtoupper(substr($booking->user->name ?? 'G', 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $booking->user->name ?? 'Walk‑in Guest' }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $booking->user->phone ?? $booking->user->email ?? '—' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell">
                                <p class="text-gray-700 dark:text-gray-300">{{ $booking->check_in?->format('M d') ?? '—' }} → {{ $booking->check_out?->format('M d, Y') ?? '—' }}</p>
                                @if($days > 0)<p class="text-xs text-gray-500 dark:text-gray-400">{{ $days }} day{{ $days != 1 ? 's' : '' }}</p>@endif
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell">
                                <p class="font-semibold text-gray-900 dark:text-white">₱{{ number_format($booking->total_amount, 0) }}</p>
                                @if($balance > 0)
                                    <p class="text-xs text-red-600 dark:text-red-400">₱{{ number_format($balance,0) }} due</p>
                                @else
                                    <p class="text-xs text-primary-600 dark:text-primary-400 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Paid
                                    </p>
                                @endif
                            </td>
                            <td class="px-6 py-4" wire:click.stop>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wider
                                    {{ $booking->status === 'pending' ? 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30' : '' }}
                                    {{ $booking->status === 'reserved' ? 'bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-500/30' : '' }}
                                    {{ $booking->status === 'confirmed' ? 'bg-indigo-100 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/30' : '' }}
                                    {{ $booking->status === 'checked_in' ? 'bg-purple-100 dark:bg-purple-500/15 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-500/30' : '' }}
                                    {{ $booking->status === 'completed' ? 'bg-slate-100 dark:bg-slate-500/15 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-500/30' : '' }}
                                    {{ $booking->status === 'cancelled' ? 'bg-red-100 dark:bg-red-500/15 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-500/30' : '' }}">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                    {{ ucfirst(str_replace('_', ' ', $booking->status)) }}
                                </span>
                                @if($booking->status === 'pending' && $balance > 0)
                                    <p class="text-xs flex items-center gap-1 mt-1 {{ $isOverdue ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400' }}">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        {{ $isOverdue ? 'Overdue' : floor($minsLeft).'m left' }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right" wire:click.stop>
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('tenant.bookings.show', $booking->id) }}" wire:navigate title="View"
                                       class="p-1.5 text-gray-400 dark:text-gray-500 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </a>
                                    <a href="{{ route('tenant.bookings.edit', $booking->id) }}" wire:navigate title="Edit"
                                       class="p-1.5 text-blue-600 dark:text-blue-400 hover:text-white hover:bg-blue-500/20 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-blue-500/50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <button type="button" wire:click="delete({{ $booking->id }})" wire:confirm="Delete booking #{{ $booking->booking_reference }}?" title="Delete"
                                            class="p-1.5 text-red-600 dark:text-red-400 hover:text-white hover:bg-red-500/20 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-red-500/50">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                    <button type="button" wire:click="toggleExpand({{ $booking->id }})" title="Details"
                                            class="p-1.5 text-gray-400 dark:text-gray-500 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                        <svg class="w-4 h-4 transition-transform {{ $expandedId === $booking->id ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        @if($expandedId === $booking->id)
                            <tr wire:key="drawer-{{ $booking->id }}">
                                <td colspan="6" class="p-0 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                                    <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400 mb-3">Guest Details</h4>
                                            @if($booking->user)
                                                @foreach(['Name' => $booking->user->name, 'Phone' => $booking->user->phone, 'Email' => $booking->user->email] as $k => $v)
                                                    <div class="flex justify-between py-1 text-sm"><span class="text-gray-500 dark:text-gray-400">{{ $k }}</span><span class="text-gray-900 dark:text-white">{{ $v ?? '—' }}</span></div>
                                                @endforeach
                                            @else
                                                <p class="text-sm text-gray-500 dark:text-gray-400">Walk‑in · no profile</p>
                                            @endif
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400 mb-3">Items Booked</h4>
                                            @foreach($booking->items as $item)
                                                <div class="flex justify-between py-1 text-sm"><span class="text-gray-700 dark:text-gray-300">{{ $item->property->name ?? 'Unknown' }} ×{{ $item->quantity }}</span><span class="text-gray-900 dark:text-white">₱{{ number_format($item->subtotal,0) }}</span></div>
                                            @endforeach
                                            @if($booking->services->isNotEmpty())
                                                <h5 class="mt-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Services</h5>
                                                @foreach($booking->services as $svc)
                                                    <div class="flex justify-between py-1 text-sm"><span class="text-gray-700 dark:text-gray-300">{{ $svc->service->name ?? 'Unknown' }}</span><span class="text-gray-900 dark:text-white">₱{{ number_format($svc->subtotal,0) }}</span></div>
                                                @endforeach
                                            @endif
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400 mb-3">Payment Summary</h4>
                                            <div class="flex justify-between py-1 text-sm"><span class="text-gray-500 dark:text-gray-400">Total</span><span class="text-gray-900 dark:text-white">₱{{ number_format($booking->total_amount,2) }}</span></div>
                                            <div class="flex justify-between py-1 text-sm"><span class="text-gray-500 dark:text-gray-400">Paid</span><span class="text-green-600 dark:text-green-400">₱{{ number_format($paid,2) }}</span></div>
                                            <div class="flex justify-between py-1 text-sm"><span class="text-gray-500 dark:text-gray-400">Balance</span><span class="{{ $balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">₱{{ number_format($balance,2) }}</span></div>
                                            @if($booking->status === 'pending' && $balance > 0)
                                                <button type="button" wire:click="cancelBooking({{ $booking->id }})"
                                                        wire:confirm="Cancel this booking?"
                                                        class="mt-3 w-full inline-flex items-center justify-center gap-1 px-3 py-2 rounded-lg bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-500/30 text-xs font-semibold hover:bg-red-100 dark:hover:bg-red-500/20 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-red-500/50">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    Cancel Booking
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                No bookings found matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($this->bookings->hasPages())
            <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                {{ $this->bookings->links() }}
            </div>
        @endif
    </div>
</div>