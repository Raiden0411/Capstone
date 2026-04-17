<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\Service;

new 
#[Layout('tenant.layouts.app')]
#[Title('Edit Service')]
class extends Component {
    public Service $service;
    
    #[Validate('required|string|max:255')]
    public $name = '';
    
    #[Validate('required|numeric|min:0')]
    public $price = 0;
    
    #[Validate('boolean')]
    public $is_active = true;

    public function mount(Service $service)
    {
        $this->service = $service;
        $this->name = $service->name;
        $this->price = $service->price;
        $this->is_active = $service->is_active;
    }

    public function update()
    {
        $this->validate();
        $this->service->update([
            'name' => $this->name,
            'price' => $this->price,
            'is_active' => $this->is_active,
        ]);
        session()->flash('message', 'Service updated.');
        return $this->redirectRoute('tenant.services.index', navigate: true);
    }
};
?>

<div class="p-6 max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Edit Service</h1>
    <form wire:submit="update" class="bg-white p-6 rounded-xl shadow space-y-4">
        <div>
            <label class="block text-sm font-medium mb-1">Service Name *</label>
            <input type="text" wire:model="name" class="w-full rounded-lg border-slate-300">
            @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Price (₱) *</label>
            <input type="number" step="0.01" wire:model="price" class="w-full rounded-lg border-slate-300">
            @error('price') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>
        <div class="flex items-center gap-2">
            <input type="checkbox" wire:model="is_active" class="rounded border-slate-300">
            <label class="text-sm">Active (available for bookings)</label>
        </div>
        <div class="flex gap-3 pt-4">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">Update Service</button>
            <a href="{{ route('tenant.services.index') }}" wire:navigate class="border px-6 py-2 rounded-lg hover:bg-slate-50">Cancel</a>
        </div>
    </form>
</div>