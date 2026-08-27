{{-- resources/views/superadmin/pages/event/⚡view-event.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use App\Models\Event;

new
#[Layout('superadmin.layouts.app')]
#[Title('Events')]
class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $typeFilter = '';
    public string $statusFilter = '';
    public int $perPage = 12;

    public function updatingSearch() { $this->resetPage(); }
    public function updatingTypeFilter() { $this->resetPage(); }
    public function updatingStatusFilter() { $this->resetPage(); }
    public function updatingPerPage() { $this->resetPage(); }

    public function getEventsProperty()
    {
        return Event::with('tenant')
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('barangay', 'like', '%'.$this->search.'%')
                        ->orWhere('type', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->typeFilter, fn($q) => $q->where('type', $this->typeFilter))
            ->when($this->statusFilter !== '', function ($q) {
                if ($this->statusFilter === 'upcoming') {
                    $q->where('start_date', '>=', now());
                } elseif ($this->statusFilter === 'past') {
                    $q->where('start_date', '<', now());
                } elseif ($this->statusFilter === 'active') {
                    $q->where('is_active', true);
                } elseif ($this->statusFilter === 'inactive') {
                    $q->where('is_active', false);
                }
            })
            ->latest('start_date')
            ->paginate($this->perPage);
    }

    public function deleteEvent(Event $event)
    {
        $event->delete();
        session()->flash('message', 'Event deleted.');
    }

    public function toggleActive(Event $event)
    {
        $event->update(['is_active' => !$event->is_active]);
        session()->flash('message', 'Event status updated.');
    }

    public function clearFilters()
    {
        $this->reset(['search', 'typeFilter', 'statusFilter']);
    }

    public function exportCsv()
    {
        $events = Event::with('tenant')
            ->when($this->search, fn($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->typeFilter, fn($q) => $q->where('type', $this->typeFilter))
            ->when($this->statusFilter, function ($q) {
                if ($this->statusFilter === 'upcoming') {
                    $q->where('start_date', '>=', now());
                } elseif ($this->statusFilter === 'past') {
                    $q->where('start_date', '<', now());
                } elseif ($this->statusFilter === 'active') {
                    $q->where('is_active', true);
                } elseif ($this->statusFilter === 'inactive') {
                    $q->where('is_active', false);
                }
            })
            ->latest('start_date')
            ->get();

        $filename = 'events-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($events) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Barangay', 'Type', 'Start Date', 'End Date', 'Tenant', 'Active', 'Featured']);
            foreach ($events as $e) {
                fputcsv($out, [
                    $e->name,
                    $e->barangay,
                    $e->type,
                    $e->start_date->format('Y-m-d H:i'),
                    $e->end_date?->format('Y-m-d H:i') ?? '',
                    $e->tenant->name ?? 'Platform-wide',
                    $e->is_active ? 'Yes' : 'No',
                    $e->featured ? 'Yes' : 'No',
                ]);
            }
            fclose($out);
        }, $filename);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Events</h1>
        </div>
        <a href="{{ route('superadmin.events.create') }}" wire:navigate
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-lg shadow-primary-500/20 transition hover:scale-105 focus-visible:ring-2 focus-visible:ring-primary-500/50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Event
        </a>
    </div>

    {{-- Flash Message --}}
    @if(session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium">
            {{ session('message') }}
        </div>
    @endif

    {{-- Quick Stats --}}
    @php
        $totalEvents = Event::count();
        $upcomingEvents = Event::where('start_date', '>=', now())->count();
        $pastEvents = Event::where('start_date', '<', now())->count();
        $activeEvents = Event::where('is_active', true)->count();
        $featuredEvents = Event::where('featured', true)->count();
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $totalEvents }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Upcoming</p>
            <p class="text-2xl font-bold text-primary-600 dark:text-primary-400 mt-2">{{ $upcomingEvents }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Past</p>
            <p class="text-2xl font-bold text-gray-500 dark:text-gray-400 mt-2">{{ $pastEvents }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Active</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-2">{{ $activeEvents }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Featured</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-2">{{ $featuredEvents }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-4 shadow-sm space-y-4">
        <div class="flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search..."
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 pl-10 pr-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
            </div>
            <select wire:model.live="typeFilter"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                <option value="">All Types</option>
                <option value="fiesta">Fiesta</option>
                <option value="sports">Sports</option>
                <option value="environment">Environment</option>
                <option value="entertainment">Entertainment</option>
                <option value="adventure">Adventure</option>
            </select>
            <select wire:model.live="statusFilter"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                <option value="">All Status</option>
                <option value="upcoming">Upcoming</option>
                <option value="past">Past</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <select wire:model.live="perPage"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                <option value="12">12</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
            <button type="button" wire:click="exportCsv"
                    class="px-4 py-2 rounded-xl bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export CSV
            </button>
            @if($search || $typeFilter || $statusFilter !== '')
                <button type="button" wire:click="clearFilters"
                        class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 text-xs font-semibold transition focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    ✕ Clear
                </button>
            @endif
        </div>
    </div>

    {{-- Events Table --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-700 dark:text-gray-200">
                <thead class="border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 sm:px-6 py-4 text-xs uppercase tracking-wider font-medium">Event</th>
                        <th class="px-4 sm:px-6 py-4 text-xs uppercase tracking-wider font-medium hidden md:table-cell">Barangay</th>
                        <th class="px-4 sm:px-6 py-4 text-xs uppercase tracking-wider font-medium">Type</th>
                        <th class="px-4 sm:px-6 py-4 text-xs uppercase tracking-wider font-medium hidden lg:table-cell">Date</th>
                        <th class="px-4 sm:px-6 py-4 text-xs uppercase tracking-wider font-medium hidden xl:table-cell">Tenant</th>
                        <th class="px-4 sm:px-6 py-4 text-xs uppercase tracking-wider font-medium">Status</th>
                        <th class="px-4 sm:px-6 py-4 text-xs uppercase tracking-wider font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($this->events as $event)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 sm:px-6 py-4">
                                <div class="flex items-center gap-3">
                                    @if($event->image_path)
                                        <img src="{{ asset('storage/' . $event->image_path) }}" class="w-10 h-10 rounded-lg object-cover border border-gray-200 dark:border-gray-700 shrink-0" alt="{{ $event->name }}">
                                    @else
                                        <div class="w-10 h-10 rounded-lg bg-primary-50 dark:bg-primary-500/10 border border-primary-200 dark:border-primary-500/20 flex items-center justify-center text-primary-700 dark:text-primary-300 font-bold text-xs shrink-0">
                                            {{ strtoupper(substr($event->name, 0, 1)) }}
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900 dark:text-white truncate">{{ $event->name }}</p>
                                        @if($event->featured)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30">★ Featured</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 hidden md:table-cell">{{ $event->barangay }}</td>
                            <td class="px-4 sm:px-6 py-4 capitalize">{{ $event->type }}</td>
                            <td class="px-4 sm:px-6 py-4 hidden lg:table-cell">
                                <p class="text-sm">{{ $event->start_date->format('M d, Y') }}</p>
                                @if($event->end_date)
                                    <p class="text-xs text-gray-500 dark:text-gray-400">to {{ $event->end_date->format('M d, Y') }}</p>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 hidden xl:table-cell">{{ $event->tenant->name ?? 'Platform-wide' }}</td>
                            <td class="px-4 sm:px-6 py-4">
                                <button type="button" wire:click="toggleActive({{ $event->id }})" title="Toggle active status" class="focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                                    @if($event->is_active)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-500/30 cursor-pointer">Active</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-500/15 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-500/30 cursor-pointer">Inactive</span>
                                    @endif
                                </button>
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-right space-x-2 whitespace-nowrap">
                                <a href="{{ route('superadmin.events.edit', $event) }}" wire:navigate
                                   class="text-primary-600 hover:text-primary-700 hover:underline text-xs focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">Edit</a>
                                <button type="button" wire:click="deleteEvent({{ $event->id }})"
                                        wire:confirm="Delete this event?"
                                        class="text-red-600 dark:text-red-400 hover:text-red-700 hover:underline text-xs focus-visible:ring-2 focus-visible:ring-red-500/50 rounded">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                No events found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($this->events->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                {{ $this->events->links() }}
            </div>
        @endif
    </div>
</div>