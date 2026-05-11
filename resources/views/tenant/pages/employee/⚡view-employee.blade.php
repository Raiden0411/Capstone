<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Employee;

new 
#[Layout('tenant.layouts.app')]
#[Title('Employees')]
class extends Component {
    use WithPagination;

    public string $search = '';

    public function updatingSearch() { $this->resetPage(); }

    public function toggleActive(int $id)
    {
        $employee = Employee::findOrFail($id);
        $employee->update(['is_active' => !$employee->is_active]);
    }

    public function delete(int $id)
    {
        Employee::findOrFail($id)->delete();
        session()->flash('message', 'Employee deleted.');
    }

    #[Computed]
    public function employees()
    {
        return Employee::with('user')
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy('name')
            ->paginate(10);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-white">Employees</h1>
            <p class="text-white/60 mt-1">Manage your team members and their access.</p>
        </div>
        <a href="{{ route('tenant.employees.create') }}" wire:navigate class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-sm font-semibold shadow-lg shadow-brand-500/20 transition hover:scale-105">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Add Employee
        </a>
    </div>

    {{-- Flash Message --}}
    @if (session()->has('message'))
        <div class="glass-card border-l-4 border-l-brand-400 p-4 text-sm text-white/80 flex items-center gap-3">
            <svg class="w-4 h-4 text-brand-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('message') }}
        </div>
    @endif

    {{-- Search --}}
    <div class="relative w-full md:w-1/3">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search employees..." 
               class="w-full bg-white/5 border border-white/10 rounded-xl py-3 pl-10 pr-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
    </div>

    {{-- Employees Table --}}
    <div class="glass-card !rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="border-b border-white/10">
                    <tr>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-white/50 uppercase">Name</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-white/50 uppercase hidden sm:table-cell">Role</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-white/50 uppercase hidden lg:table-cell">Phone</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-white/50 uppercase">Status</th>
                        <th class="px-4 sm:px-6 py-4 text-right text-xs font-semibold text-white/50 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-white/80">
                    @forelse($this->employees as $employee)
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="px-4 sm:px-6 py-4 font-medium text-white">{{ $employee->name }}</td>
                            <td class="px-4 sm:px-6 py-4 hidden sm:table-cell">{{ $employee->role ?? '—' }}</td>
                            <td class="px-4 sm:px-6 py-4 hidden lg:table-cell">{{ $employee->phone ?? '—' }}</td>
                            <td class="px-4 sm:px-6 py-4">
                                <button wire:click="toggleActive({{ $employee->id }})" 
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium transition-colors
                                               {{ $employee->is_active ? 'bg-green-500/20 text-green-300 hover:bg-green-500/30' : 'bg-white/10 text-white/40 hover:bg-white/20' }}">
                                    {{ $employee->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('tenant.employees.edit', $employee->id) }}" wire:navigate class="text-brand-400 hover:text-brand-300 font-medium text-sm transition">Edit</a>
                                    <button wire:click="delete({{ $employee->id }})" wire:confirm="Delete this employee?" class="text-red-400 hover:text-red-300 font-medium text-sm transition">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-white/40">
                                <svg class="mx-auto h-12 w-12 text-white/10 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                <span class="text-sm">No employees found{{ $search ? ' matching "' . $search . '"' : '' }}.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->employees->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-white/10">
                {{ $this->employees->links() }}
            </div>
        @endif
    </div>
</div>