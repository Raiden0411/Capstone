{{-- resources/views/tenant/pages/event/⚡view-event.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;

new
#[Layout('tenant.layouts.app')]
#[Title('Events')]
class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch() { $this->resetPage(); }

    public function getEventsProperty()
    {
        return Event::where('tenant_id', Auth::user()->tenant_id)
            ->when($this->search, fn($q) =>
                $q->where('name', 'like', '%'.$this->search.'%')
                  ->orWhere('barangay', 'like', '%'.$this->search.'%')
            )
            ->latest()
            ->paginate(15);
    }

    public function deleteEvent(Event $event)
    {
        $this->authorize('delete', $event);
        $event->delete();
        session()->flash('message', 'Event deleted.');
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Events</h1>
        </div>
        <a href="{{ route('tenant.events.create') }}" wire:navigate
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-[#376df1] hover:bg-blue-700 text-white text-sm font-semibold shadow-lg shadow-blue-500/20 transition hover:scale-105">
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

    {{-- Search --}}
    <div class="flex flex-col md:flex-row gap-4 items-start md:items-center">
        <div class="relative flex-1 max-w-md w-full">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search events..."
                   class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 pl-10 pr-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition">
        </div>
    </div>

    {{-- Events Table --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Name</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Barangay</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Type</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Date</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Active</th>
                        <th class="px-4 sm:px-6 py-4 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-200">
                    @forelse($this->events as $event)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 sm:px-6 py-4 font-medium text-gray-900 dark:text-white">{{ $event->name }}</td>
                            <td class="px-4 sm:px-6 py-4">{{ $event->barangay }}</td>
                            <td class="px-4 sm:px-6 py-4 capitalize">{{ $event->type }}</td>
                            <td class="px-4 sm:px-6 py-4">{{ $event->start_date->format('M d, Y') }}</td>
                            <td class="px-4 sm:px-6 py-4">
                                @if($event->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-500/30">Active</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 border border-gray-300 dark:border-gray-600">Inactive</span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-right whitespace-nowrap space-x-2">
                                <a href="{{ route('tenant.events.edit', $event) }}" wire:navigate
                                   class="text-[#376df1] dark:text-blue-400 hover:text-blue-700 hover:underline text-xs font-medium">Edit</a>
                                <button wire:click="deleteEvent({{ $event->id }})"
                                        wire:confirm="Delete this event?"
                                        class="text-red-600 dark:text-red-400 hover:text-red-700 hover:underline text-xs font-medium">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z"/></svg>
                                <span class="text-sm">No events found.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->events->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $this->events->links() }}
            </div>
        @endif
    </div>
</div>