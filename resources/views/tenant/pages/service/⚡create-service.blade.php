<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\Service;
use Illuminate\Support\Facades\Auth;

new 
#[Layout('tenant.layouts.app')]
#[Title('Create Service')]
class extends Component {
    #[Validate('required|string|max:255')]
    public $name = '';
    
    #[Validate('required|numeric|min:0')]
    public $price = 0;
    
    #[Validate('boolean')]
    public $is_active = true;

    public function updated($property)
    {
        if ($property === 'name') {
            $this->name = trim($this->name);
        }
    }

    public function save()
    {
        $this->validate();
        Service::create([
            'tenant_id' => Auth::user()->tenant_id,
            'name' => $this->name,
            'price' => $this->price,
            'is_active' => $this->is_active,
        ]);
        session()->flash('message', 'Service created successfully.');
        return $this->redirectRoute('tenant.services.index', navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-3xl mx-auto text-gray-900 dark:text-white space-y-6">

    {{-- Flash Message (shown after redirect) --}}
    @if (session()->has('message'))
        <div class="p-4 bg-green-50 dark:bg-green-500/10 border-l-4 border-green-500 rounded-md shadow-sm">
            <p class="text-sm text-green-700 dark:text-green-400 font-medium">{{ session('message') }}</p>
        </div>
    @endif

    <div class="flex items-center justify-between">
        <h1 class="text-2xl sm:text-3xl font-bold">Add Service</h1>
        <a href="{{ route('tenant.services.index') }}" wire:navigate class="text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-white font-medium transition-colors">
            &larr; Back to Services
        </a>
    </div>

    <form wire:submit="save" class="space-y-5 bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Service Name *</label>
            <input type="text" wire:model="name" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500">
            @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Price (₱) *</label>
            <input type="number" step="0.01" wire:model="price" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 focus:ring-blue-500 focus:border-blue-500">
            @error('price') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>
        <div class="flex items-center gap-3">
            <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 dark:border-slate-700 text-blue-600 focus:ring-blue-500">
            <label class="text-sm text-gray-700 dark:text-slate-300">Active (available for bookings)</label>
        </div>
        <div class="flex items-center gap-3 pt-4 border-t border-gray-200 dark:border-slate-700/50">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-xl shadow-sm transition-colors flex items-center gap-2 data-loading:opacity-75 data-loading:cursor-not-allowed">
                <span class="in-data-loading:hidden">Save Service</span>
                <span class="not-in-data-loading:hidden flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Saving...
                </span>
            </button>
            <a href="{{ route('tenant.services.index') }}" wire:navigate class="border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 font-medium py-2.5 px-6 rounded-xl transition-colors">
                Cancel
            </a>
        </div>
    </form>
</div>