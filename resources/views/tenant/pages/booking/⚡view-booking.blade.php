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
    public string $sortBy       = 'check_in_asc';
    public ?int   $expandedId   = null;

    public function mount()
    {
        $this->cancelOverdueBookings();
    }

    protected function cancelOverdueBookings(): void
    {
        $deadline = now()->subMinutes(Booking::PAYMENT_DEADLINE_MINUTES);

        $overdue = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('status', Booking::STATUS_PENDING)
            ->where('created_at', '<=', $deadline)
            ->get();

        foreach ($overdue as $booking) {
            $propertyIds = $booking->items()->pluck('property_id')->unique()->values()->toArray();
            if ($propertyIds) {
                Property::whereIn('id', $propertyIds)->update(['status' => 'available']);
            }
            $booking->update(['status' => Booking::STATUS_CANCELLED]);
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

    public function getAllowedStatuses(Booking $booking): array
    {
        $current = $booking->status;

        if (in_array($current, [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED])) {
            return [];
        }

        return match ($current) {
            Booking::STATUS_PENDING    => [Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED],
            Booking::STATUS_RESERVED   => [Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED],
            Booking::STATUS_CONFIRMED  => [Booking::STATUS_CHECKED_IN, Booking::STATUS_CANCELLED],
            Booking::STATUS_CHECKED_IN => [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED],
            default                    => [],
        };
    }

    public function updateStatus(int $id, string $status)
    {
        $booking = Booking::withoutGlobalScope(TenantScope::class)
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->firstOrFail();

        $allowed = $this->getAllowedStatuses($booking);
        if (!in_array($status, $allowed)) {
            session()->flash('error', "Cannot change status from '{$booking->status}' to '{$status}'.");
            return;
        }

        if ($booking->status === Booking::STATUS_PENDING && $status === Booking::STATUS_CONFIRMED) {
            $totalPaid = $booking->payments()->where('payment_status', 'paid')->sum('amount');
            if ($totalPaid < $booking->total_amount) {
                session()->flash('error', 'Payment must be completed before confirming.');
                return;
            }
        }

        $oldStatus = $booking->status;
        $booking->update(['status' => $status]);

        if (in_array($status, [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED]) 
            && !in_array($oldStatus, [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED])) {
            $propertyIds = $booking->items()->pluck('property_id')->unique()->toArray();
            if ($propertyIds) {
                Property::whereIn('id', $propertyIds)->update(['status' => 'available']);
            }
        }

        session()->flash('message', "Booking #{$booking->booking_reference} marked as " . str_replace('_', ' ', $status) . ".");
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

        $callback = function() use ($bookings) {
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
                    number_format($b->total_amount, 2),
                    number_format($paid, 2),
                    number_format($b->total_amount - $paid, 2),
                    $b->status,
                ]);
            }
            fclose($file);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }

    #[Computed]
    public function users()
    {
        return User::whereHas('bookings', fn($q) => $q->where('tenant_id', Auth::user()->tenant_id))
            ->orderBy('name')
            ->get();
    }

    private function query()
    {
        return Booking::withoutGlobalScope(TenantScope::class)
            ->with(['user', 'items.property', 'services.service', 'payments'])
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
                switch ($this->sortBy) {
                    case 'check_in_asc':
                        $q->orderBy('check_in', 'asc');
                        break;
                    case 'check_in_desc':
                        $q->orderBy('check_in', 'desc');
                        break;
                    case 'amount_high':
                        $q->orderByDesc('total_amount');
                        break;
                    case 'amount_low':
                        $q->orderBy('total_amount');
                        break;
                    default:
                        $q->latest();
                }
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
        $deadline = now()->subMinutes(Booking::PAYMENT_DEADLINE_MINUTES);

        return [
            'total'            => Booking::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tid)->whereNotIn('status', [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED])->count(),
            'pending'          => Booking::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tid)->where('status', Booking::STATUS_PENDING)->count(),
            'reserved'         => Booking::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tid)->where('status', Booking::STATUS_RESERVED)->count(),
            'confirmed'        => Booking::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tid)->where('status', Booking::STATUS_CONFIRMED)->count(),
            'checked_in'       => Booking::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tid)->where('status', Booking::STATUS_CHECKED_IN)->count(),
            'completed'        => Booking::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tid)->where('status', Booking::STATUS_COMPLETED)->count(),
            'overdue'          => Booking::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tid)->where('status', Booking::STATUS_PENDING)->where('created_at', '<=', $deadline)->count(),
            'revenue'          => Booking::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tid)->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN, Booking::STATUS_COMPLETED])->sum('total_amount'),
            'today_arrivals'   => Booking::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tid)->whereDate('check_in', today())->where('status', '!=', Booking::STATUS_CANCELLED)->count(),
            'today_departures' => Booking::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tid)->whereDate('check_out', today())->where('status', '!=', Booking::STATUS_CANCELLED)->count(),
            'available'        => Property::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tid)->where('is_active', true)->where('status', 'available')->count(),
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
               class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                History
            </a>
            <button wire:click="$refresh" class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M4 9a9 9 0 0014.5 4.5M20 20v-5h-5M20 15a9 9 0 00-14.5-4.5"/></svg>
                Refresh
            </button>
            <button wire:click="exportCsv" class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export CSV
            </button>
            <a href="{{ route('tenant.customers.create') }}" wire:navigate
               class="inline-flex items-center justify-center px-5 py-2.5 rounded-full bg-[#376df1] hover:bg-blue-700 text-white text-sm font-semibold shadow-lg shadow-blue-500/20 transition hover:scale-105">
                New Reservation
            </a>
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium">
            ✔ {{ session('message') }}
        </div>
    @endif
    @if(session()->has('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md text-sm text-red-700 dark:text-red-300 font-medium">
            ✖ {{ session('error') }}
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
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $label }}</span>
                <div class="flex items-end justify-between mt-2">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ $value }}</span>
                    <span class="w-2 h-2 rounded-full {{ $dotClass }}"></span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-4 shadow-sm space-y-4">
        <div class="flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search"
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 pl-10 pr-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition"
                       placeholder="Search reference or guest…">
            </div>
            <select wire:model.live="userFilter"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition">
                <option value="">All Guests</option>
                @foreach($this->users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-wrap gap-3 items-center">
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400">From:</span>
                <input type="date" wire:model.live="fromDate"
                       class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2 px-3 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[#376df1]/50 transition">
                <span class="text-xs text-gray-500 dark:text-gray-400">To:</span>
                <input type="date" wire:model.live="toDate"
                       class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2 px-3 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[#376df1]/50 transition">
            </div>
            <select wire:model.live="sortBy"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition">
                <option value="check_in_asc">Check-in (Earliest)</option>
                <option value="check_in_desc">Check-in (Latest)</option>
                <option value="newest">Newest Created</option>
                <option value="amount_high">Amount (High to Low)</option>
                <option value="amount_low">Amount (Low to High)</option>
            </select>
            @if($search || $statusFilter || $fromDate || $toDate || $userFilter)
                <button wire:click="clearFilters"
                        class="px-4 py-2 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 text-xs font-semibold uppercase tracking-wider transition">
                    ✕ Clear
                </button>
            @endif
        </div>
    </div>

    {{-- Status filters + legend --}}
    <div class="flex flex-wrap gap-2 items-center">
        @foreach(['' => 'All', 'pending' => 'Pending', 'reserved' => 'Reserved', 'confirmed' => 'Confirmed', 'checked_in' => 'Checked In'] as $val => $label)
            <button wire:click="$set('statusFilter','{{ $val }}')" wire:key="pill-{{ $val }}"
                    class="px-4 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wider transition border
                           {{ $statusFilter === $val ? 'bg-[#376df1] border-[#376df1] text-white' : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-blue-400 hover:text-[#376df1] dark:hover:text-blue-400' }}">
                {{ $label }}
            </button>
        @endforeach
        @if($s['overdue'] > 0)
            <div class="px-4 py-1.5 rounded-full bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-300 text-xs font-semibold uppercase tracking-wider flex items-center gap-1">
                ⚠ {{ $s['overdue'] }} Overdue
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
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
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
                            $allowed = $this->getAllowedStatuses($booking);
                            $paid = $booking->payments->where('payment_status','paid')->sum('amount');
                            $balance = $booking->total_amount - $paid;
                        @endphp
                        <tr wire:key="row-{{ $booking->id }}"
                            class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition cursor-pointer {{ $isOverdue ? 'bg-red-50 dark:bg-red-500/5' : '' }} {{ $expandedId === $booking->id ? 'bg-gray-50 dark:bg-gray-700/50' : '' }}"
                            wire:click="toggleExpand({{ $booking->id }})">
                            <td class="px-6 py-4">
                                <span class="font-mono text-sm font-semibold text-[#376df1] dark:text-blue-400">{{ $booking->booking_reference }}</span>
                                @if($isToday)<span class="ml-2 text-[10px] bg-blue-50 dark:bg-blue-500/20 text-[#376df1] dark:text-blue-300 px-1.5 py-0.5 rounded-full">Today</span>@endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-blue-50 dark:bg-blue-500/15 flex items-center justify-center text-[#376df1] dark:text-blue-300 font-semibold text-sm">
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
                                    <p class="text-xs text-[#376df1] dark:text-blue-400">Paid ✓</p>
                                @endif
                            </td>
                            <td class="px-6 py-4" wire:click.stop>
                                <div class="flex flex-col gap-2">
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

                                    @if(!empty($allowed))
                                        <select x-data="{}"
                                                x-on:change="$wire.updateStatus({{ $booking->id }}, $event.target.value); $el.value = '';"
                                                @click.stop
                                                class="w-full max-w-[160px] bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg py-1.5 px-3 text-xs text-gray-900 dark:text-white focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition appearance-none">
                                            <option value="">Move to…</option>
                                            @foreach($allowed as $next)
                                                <option value="{{ $next }}">→ {{ ucfirst(str_replace('_',' ',$next)) }}</option>
                                            @endforeach
                                        </select>
                                    @endif

                                    @if($booking->status === 'pending' && $balance > 0)
                                        <p class="text-xs flex items-center gap-1 {{ $isOverdue ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400' }}">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            {{ $isOverdue ? 'Overdue' : floor($minsLeft).'m left' }}
                                        </p>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right" wire:click.stop>
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('tenant.bookings.show', $booking->id) }}" wire:navigate title="View" class="p-1.5 text-gray-400 dark:text-gray-500 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></a>
                                    <a href="{{ route('tenant.bookings.edit', $booking->id) }}" wire:navigate title="Edit" class="p-1.5 text-blue-600 dark:text-blue-400 hover:text-white hover:bg-blue-500/20 rounded-lg transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a>
                                    <button wire:click="delete({{ $booking->id }})" wire:confirm="Delete booking #{{ $booking->booking_reference }}?" title="Delete" class="p-1.5 text-red-600 dark:text-red-400 hover:text-white hover:bg-red-500/20 rounded-lg transition"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                                    <button wire:click="toggleExpand({{ $booking->id }})" title="Details" class="p-1.5 text-gray-400 dark:text-gray-500 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition"><svg class="w-4 h-4 transition-transform {{ $expandedId === $booking->id ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>
                                </div>
                            </td>
                        </tr>

                        @if($expandedId === $booking->id)
                            <tr wire:key="drawer-{{ $booking->id }}">
                                <td colspan="6" class="p-0 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                                    <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wider text-[#376df1] dark:text-blue-400 mb-3">Guest Details</h4>
                                            @if($booking->user)
                                                @foreach(['Name' => $booking->user->name, 'Phone' => $booking->user->phone, 'Email' => $booking->user->email] as $k => $v)
                                                    <div class="flex justify-between py-1 text-sm"><span class="text-gray-500 dark:text-gray-400">{{ $k }}</span><span class="text-gray-900 dark:text-white">{{ $v ?? '—' }}</span></div>
                                                @endforeach
                                            @else
                                                <p class="text-sm text-gray-500 dark:text-gray-400">Walk‑in · no profile</p>
                                            @endif
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wider text-[#376df1] dark:text-blue-400 mb-3">Items Booked</h4>
                                            @foreach($booking->items as $item)
                                                <div class="flex justify-between py-1 text-sm"><span class="text-gray-700 dark:text-gray-300">{{ $item->property->name ?? 'Unknown' }} ×{{ $item->quantity }}</span><span class="text-gray-900 dark:text-white">₱{{ number_format($item->subtotal,0) }}</span></div>
                                            @endforeach
                                            @foreach($booking->services as $bs)
                                                <div class="flex justify-between py-1 text-sm"><span class="text-gray-500 dark:text-gray-400">+ {{ $bs->service->name ?? '?' }} ×{{ $bs->quantity }}</span><span class="text-gray-500 dark:text-gray-400">₱{{ number_format($bs->subtotal,0) }}</span></div>
                                            @endforeach
                                            <div class="flex justify-between py-1 text-sm border-t border-gray-200 dark:border-gray-700 mt-2 pt-2 font-semibold"><span class="text-gray-700 dark:text-gray-300">Total</span><span class="text-[#376df1] dark:text-blue-400">₱{{ number_format($booking->total_amount, 2) }}</span></div>
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wider text-[#376df1] dark:text-blue-400 mb-3">Payment</h4>
                                            @php $paidPct = $booking->total_amount > 0 ? min(100, ($paid / $booking->total_amount) * 100) : 0; @endphp
                                            <div class="flex justify-between py-1 text-sm"><span class="text-gray-500 dark:text-gray-400">Paid</span><span class="text-[#376df1] dark:text-blue-400">₱{{ number_format($paid, 2) }}</span></div>
                                            <div class="flex justify-between py-1 text-sm"><span class="text-gray-500 dark:text-gray-400">Balance</span><span class="{{ $balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-[#376df1] dark:text-blue-400' }}">{{ $balance > 0 ? '₱'.number_format($balance,2) : 'Settled ✓' }}</span></div>
                                            <div class="w-full h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full mt-2 overflow-hidden">
                                                <div class="h-full rounded-full transition-all duration-500" style="width: {{ $paidPct }}%; background: {{ $paidPct >= 100 ? '#22c55e' : '#f59e0b' }};"></div>
                                            </div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ round($paidPct) }}% paid</p>
                                            @if($balance > 0)
                                                <a href="{{ route('tenant.payments.create', ['booking' => $booking->id]) }}" wire:navigate class="inline-flex items-center gap-2 mt-3 px-4 py-2 rounded-full bg-[#376df1] hover:bg-blue-700 text-white text-sm font-semibold transition shadow-lg shadow-blue-500/20">
                                                    Record Payment
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <p class="text-lg font-medium">No active bookings found.</p>
                                <p class="text-sm mt-1">Try adjusting your filters or create a new reservation.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->bookings->hasPages())
            <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                {{ $this->bookings->links() }}
            </div>
        @endif
    </div>
</div>