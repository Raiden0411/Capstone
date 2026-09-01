{{-- resources/views/superadmin/pages/tenant-type/⚡view-type.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\TypeOfTenant;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

new 
#[Layout('superadmin.layouts.app')]
#[Title('Tenant Types')]
class extends Component {
    use WithPagination;

    public string $search = '';

    public function updatingSearch() { $this->resetPage(); }

    #[Computed]
    public function types()
    {
        return TypeOfTenant::query()
            ->select('id', 'type', 'description')
            ->withCount('tenants')
            ->when($this->search, fn($q) => $q->where('type', 'like', '%' . $this->search . '%'))
            ->orderBy('type')
            ->paginate(10);
    }

    #[Computed]
    public function stats()
    {
        return TypeOfTenant::query()
            ->selectRaw('
                COUNT(*) as total_types,
                COALESCE(SUM((SELECT COUNT(*) FROM tenants WHERE tenants.type_of_tenant_id = type_of_tenants.id) > 0), 0) as types_in_use
            ')
            ->first();
    }

    public function delete(int $id)
    {
        $type = TypeOfTenant::withCount('tenants')->findOrFail($id);
        if ($type->tenants_count > 0) {
            session()->flash('error', "Cannot delete '{$type->type}' because it is used by {$type->tenants_count} tenant(s).");
            return;
        }
        $type->delete();
        session()->flash('message', "Tenant type '{$type->type}' deleted.");
    }

    public function exportCsv()
    {
        $types = TypeOfTenant::query()
            ->select('id', 'type', 'description')
            ->withCount('tenants')
            ->when($this->search, fn($q) => $q->where('type', 'like', '%' . $this->search . '%'))
            ->orderBy('type')
            ->cursor();

        $filename = 'tenant-types-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($types) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Type', 'Description', 'Tenants']);
            foreach ($types as $type) {
                fputcsv($out, [
                    $type->type,
                    $type->description ?? '',
                    $type->tenants_count,
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
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Tenant Types</h1>
        </div>
        <a href="{{ route('superadmin.tenant-types.create') }}" wire:navigate
           class="btn-primary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add New Type
        </a>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium">
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md text-sm text-red-700 dark:text-red-300 font-medium">
            {{ session('error') }}
        </div>
    @endif

    {{-- Quick Stats --}}
    @php
        $totalTenants = Tenant::count();
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Types</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $this->stats->total_types ?? 0 }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Tenants</p>
            <p class="text-2xl font-bold text-primary-600 dark:text-primary-400 mt-2">{{ $totalTenants }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Types In Use</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-2">{{ $this->stats->types_in_use ?? 0 }}</p>
        </div>
    </div>

    {{-- Search & Export --}}
    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
        <div class="relative flex-1 max-w-md w-full">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search types..."
                   class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 pl-10 pr-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
        </div>
        <button wire:click="exportCsv" wire:loading.attr="disabled"
                class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
            <span wire:loading wire:target="exportCsv" class="inline-flex items-center gap-1">
                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                Exporting…
            </span>
        </button>
    </div>

    {{-- Table Card --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 dark:border-gray-700 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 sm:px-6 py-4 font-medium">Type</th>
                        <th class="px-4 sm:px-6 py-4 font-medium hidden sm:table-cell">Description</th>
                        <th class="px-4 sm:px-6 py-4 font-medium text-center">Tenants</th>
                        <th class="px-4 sm:px-6 py-4 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-200">
                    @forelse($this->types as $type)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 sm:px-6 py-4 font-medium text-gray-900 dark:text-white">{{ $type->type }}</td>
                            <td class="px-4 sm:px-6 py-4 hidden sm:table-cell text-gray-500 dark:text-gray-400">{{ $type->description ?? '—' }}</td>
                            <td class="px-4 sm:px-6 py-4 text-center font-semibold text-gray-900 dark:text-white">{{ $type->tenants_count }}</td>
                            <td class="px-4 sm:px-6 py-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('superadmin.tenant-types.edit', $type->id) }}" wire:navigate
                                       class="p-1.5 text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-500/10 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <button wire:click="delete({{ $type->id }})"
                                            wire:confirm="Delete this tenant type?"
                                            class="p-1.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-red-500/50" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 01.586 1.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
                                    <span class="text-gray-500 dark:text-gray-400">No tenant types found.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->types->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                {{ $this->types->links() }}
            </div>
        @endif
    </div>
</div>