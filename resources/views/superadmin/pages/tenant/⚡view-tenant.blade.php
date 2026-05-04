<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;

new 
#[Layout('superadmin.layouts.app')]
#[Title('Manage Tenants')]
class extends Component {
    use WithPagination;

    public $search = '';
    public $statusFilter = 'all';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    #[Computed]
    public function tenants()
    {
        return Tenant::with('typeOfTenant')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('is_active', $this->statusFilter === 'active');
            })
            ->latest()
            ->paginate(10);
    }

    public function deleteTenant($id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->delete();
        session()->flash('message', 'Business Location successfully deleted.');
    }

    public function approve(int $id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['is_active' => true]);

        $user = $tenant->users()->first();
        if ($user) {
            $user->update(['is_active' => true]);
            if (!$user->hasRole('admin')) {
                $user->assignRole('admin');
            }
        }

        session()->flash('message', "{$tenant->name} has been approved and is now active.");
    }
};
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto space-y-6 text-gray-900 dark:text-white">

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Manage Businesses</h1>
            <p class="text-gray-500 dark:text-slate-400 mt-1">View, search, approve, edit, and remove tourist spots from the city platform.</p>
        </div>
        <a href="{{ route('superadmin.tenants.create') }}" wire:navigate class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-5 rounded-xl shadow transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Register Business
        </a>
    </div>

    {{-- Flash Message --}}
    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border-l-4 border-green-500 p-4 rounded-md shadow-sm">
            <p class="text-sm text-green-700 dark:text-green-400 font-medium">{{ session('message') }}</p>
        </div>
    @endif

    {{-- Main Card --}}
    <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm overflow-hidden">
        
        {{-- Filters --}}
        <div class="p-4 border-b border-gray-200 dark:border-slate-700/50 bg-gray-50 dark:bg-slate-800/50 flex flex-col sm:flex-row gap-3">
            <div class="relative w-full sm:w-1/3">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or email..." 
                       class="w-full pl-10 rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-300 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500 text-sm py-2.5">
            </div>
            <select wire:model.live="statusFilter" 
                    class="w-full sm:w-1/4 rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-300 focus:ring-blue-500 focus:border-blue-500 text-sm py-2.5">
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Pending Approval</option>
            </select>
        </div>
        
        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-gray-50 dark:bg-slate-800/50 border-b border-gray-200 dark:border-slate-700/50 text-gray-600 dark:text-slate-400 uppercase text-xs font-semibold">
                    <tr>
                        <th class="px-4 sm:px-6 py-4">Tourist Spot / Business</th>
                        <th class="px-4 sm:px-6 py-4 hidden sm:table-cell">Contact Details</th>
                        <th class="px-4 sm:px-6 py-4 hidden lg:table-cell">Location</th>
                        <th class="px-4 sm:px-6 py-4">Status</th>
                        <th class="px-4 sm:px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-slate-700/30 text-gray-700 dark:text-slate-300">
                    @forelse($this->tenants as $tenant)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            {{-- Name & Type --}}
                            <td class="px-4 sm:px-6 py-4">
                                <div class="font-bold text-gray-900 dark:text-white">{{ $tenant->name }}</div>
                                <div class="mt-1 flex items-center gap-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/30">
                                        {{ $tenant->typeOfTenant->type ?? 'Uncategorized' }}
                                    </span>
                                    <span class="text-xs text-gray-400 dark:text-slate-500 hidden sm:inline">ID: {{ $tenant->id }}</span>
                                </div>
                                {{-- Mobile: contact info visible only on small screens --}}
                                <div class="sm:hidden mt-1 space-y-0.5 text-xs text-gray-500 dark:text-slate-400">
                                    <div>{{ $tenant->email }}</div>
                                    <div>{{ $tenant->contact_number ?? 'No contact number' }}</div>
                                </div>
                            </td>

                            {{-- Contact (hidden on sm and below) --}}
                            <td class="px-4 sm:px-6 py-4 hidden sm:table-cell">
                                <div class="text-gray-900 dark:text-white font-medium">{{ $tenant->email }}</div>
                                <div class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">{{ $tenant->contact_number ?? 'No contact number' }}</div>
                            </td>

                            {{-- Location (hidden on lg and below) --}}
                            <td class="px-4 sm:px-6 py-4 hidden lg:table-cell">
                                <span class="truncate max-w-[200px] block text-gray-600 dark:text-slate-400" title="{{ $tenant->address }}">
                                    {{ $tenant->address }}
                                </span>
                            </td>

                            {{-- Status --}}
                            <td class="px-4 sm:px-6 py-4">
                                @if($tenant->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-500/20 text-green-800 dark:text-green-400">
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-500/20 text-amber-800 dark:text-amber-400">
                                        Pending
                                    </span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="px-4 sm:px-6 py-4 text-right space-x-2 whitespace-nowrap">
                                @if(!$tenant->is_active)
                                    <button wire:click="approve({{ $tenant->id }})" wire:confirm="Approve this business and activate its owner account?" 
                                            class="bg-green-600 hover:bg-green-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg shadow-sm transition-colors">
                                        Approve
                                    </button>
                                @endif
                                <a href="{{ route('superadmin.tenants.edit', $tenant->id) }}" wire:navigate class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors font-medium">Edit</a>
                                <button wire:click="deleteTenant({{ $tenant->id }})" wire:confirm="Are you sure you want to delete this business? This will also remove all their properties and bookings." class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors font-medium">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No businesses found</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Get started by registering a new tourist spot.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        {{-- Pagination --}}
        @if($this->tenants->hasPages())
            <div class="p-4 border-t border-gray-200 dark:border-slate-700/50 bg-gray-50 dark:bg-slate-800/50">
                {{ $this->tenants->links() }}
            </div>
        @endif
    </div>
</div>