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

<div class="max-w-3xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">

    {{-- Flash Message (shown after redirect) --}}
    @if (session()->has('message'))
        <div class="glass-card border-l-4 border-l-brand-400 p-4 text-sm text-white/80 flex items-center gap-3">
            <svg class="w-4 h-4 text-brand-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('message') }}
        </div>
    @endif

    <div class="flex items-center justify-between">
        <h1 class="text-2xl sm:text-3xl font-bold text-white">Add Service</h1>
        <a href="{{ route('tenant.services.index') }}" wire:navigate class="text-sm font-medium text-white/50 hover:text-brand-400 transition-colors flex items-center gap-1">
            &larr; Back to Services
        </a>
    </div>

    <form wire:submit="save" class="space-y-5 glass-card !rounded-xl p-5 sm:p-6">
        <div>
            <label class="block text-sm font-medium text-white/70 mb-1">Service Name *</label>
            <input type="text" wire:model="name"
                   class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
            @error('name') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-white/70 mb-1">Price (₱) *</label>
            <input type="number" step="0.01" wire:model="price"
                   class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
            @error('price') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>
        <div class="flex items-center gap-3">
            <input type="checkbox" wire:model="is_active"
                   class="rounded border-white/20 bg-white/5 text-brand-600 focus:ring-brand-500">
            <label class="text-sm text-white/70">Active (available for bookings)</label>
        </div>
        <div class="flex items-center gap-3 pt-4 border-t border-white/10">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="bg-brand-600 hover:bg-brand-500 text-white font-medium py-2.5 px-6 rounded-xl shadow-lg shadow-brand-500/20 transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove>Save Service</span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Saving...
                </span>
            </button>
            <a href="{{ route('tenant.services.index') }}" wire:navigate class="glass px-6 py-3 rounded-xl text-white/80 hover:bg-white/10 font-medium transition">
                Cancel
            </a>
        </div>
    </form>
</div>