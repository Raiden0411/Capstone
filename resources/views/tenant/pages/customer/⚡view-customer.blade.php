<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Customer;

new 
#[Layout('tenant.layouts.app')]
#[Title('Customers')]
class extends Component {
    use WithPagination;

    public string $search = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function delete(int $id)
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();
        session()->flash('message', 'Customer deleted successfully.');
    }

    #[Computed]
    public function customers()
    {
        return Customer::query()
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('phone', 'like', '%' . $this->search . '%')
                ->orWhere('email', 'like', '%' . $this->search . '%'))
            ->orderBy('name')
            ->paginate(10);
    }
};
?>

<div class="p-6 sm:p-10 max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Customers</h1>
            <p class="text-slate-500">Manage your guest information.</p>
        </div>
        <a href="{{ route('tenant.customers.create') }}" wire:navigate class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-lg shadow-sm transition flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Add Customer
        </a>
    </div>

    @if (session()->has('message'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center gap-3 shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            {{ session('message') }}
        </div>
    @endif

    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name, phone or email..." 
               class="w-full md:w-1/3 rounded-lg border-slate-300 focus:ring-blue-500">
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-left">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Name</th>
                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Phone</th>
                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Email</th>
                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Address</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold text-slate-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($this->customers as $customer)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4 font-medium">{{ $customer->name }}</td>
                        <td class="px-6 py-4">{{ $customer->phone ?? '—' }}</td>
                        <td class="px-6 py-4">{{ $customer->email ?? '—' }}</td>
                        <td class="px-6 py-4">{{ $customer->address ?? '—' }}</td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('tenant.customers.edit', $customer->id) }}" wire:navigate class="text-blue-600 hover:underline mr-3">Edit</a>
                            <button wire:click="delete({{ $customer->id }})" wire:confirm="Delete this customer?" class="text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">No customers found.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($this->customers->hasPages())
            <div class="px-6 py-4 border-t bg-slate-50">{{ $this->customers->links() }}</div>
        @endif
    </div>
</div>