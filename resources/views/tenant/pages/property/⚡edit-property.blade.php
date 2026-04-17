<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\Property;
use App\Models\PropertyType;
use Illuminate\Support\Facades\Auth;

new 
#[Layout('tenant.layouts.app')]
#[Title('Edit Property')]
class extends Component {
    
    public Property $property;
    
    #[Validate('required|string|max:255')]
    public $name = '';
    
    #[Validate('nullable|string')]
    public $description = '';
    
    #[Validate('required')]
    public $property_type_id = '';
    
    #[Validate('required|integer|min:1')]
    public $capacity = 1;
    
    #[Validate('required|numeric|min:0')]
    public $price = 0.00;
    
    #[Validate('required|in:available,occupied,maintenance')]
    public $status = 'available';
    
    #[Validate('boolean')]
    public $is_active = true;

    public function mount(Property $property)
    {
        $this->property = $property;
        $this->name = $property->name;
        $this->description = $property->description;
        $this->property_type_id = $property->property_type_id;
        $this->capacity = $property->capacity;
        $this->price = $property->price;
        $this->status = $property->status;
        $this->is_active = $property->is_active;
    }

    public function getPropertyTypesProperty()
    {
        return PropertyType::availableForTenant(Auth::user()->tenant_id)
            ->orderByRaw('tenant_id IS NULL DESC')
            ->orderBy('name')
            ->get();
    }

    public function update()
    {
        $this->validate();

        $this->property->update([
            'name'              => $this->name,
            'description'       => $this->description,
            'property_type_id'  => $this->property_type_id,
            'capacity'          => $this->capacity,
            'price'             => $this->price,
            'status'            => $this->status,
            'is_active'         => $this->is_active,
        ]);

        session()->flash('message', 'Property updated successfully.');
        return $this->redirectRoute('tenant.properties.index', navigate: true);
    }
};
?>

<div class="p-6 max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Edit Property</h1>

    <form wire:submit="update" class="space-y-6 bg-white p-6 rounded-xl shadow">
        {{-- Name --}}
        <div>
            <label class="block text-sm font-medium mb-1">Property Name</label>
            <input type="text" wire:model="name" class="w-full rounded-lg border-slate-300">
            @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        {{-- Property Type --}}
        <div>
            <label class="block text-sm font-medium mb-1">Property Type</label>
            <select wire:model="property_type_id" class="w-full rounded-lg border-slate-300">
                <option value="">-- Select a Type --</option>
                @foreach($this->propertyTypes as $type)
                    <option value="{{ $type->id }}">
                        {{ $type->name }}
                        {{ is_null($type->tenant_id) ? '(Global)' : '(Custom)' }}
                    </option>
                @endforeach
            </select>
            @error('property_type_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        {{-- Description --}}
        <div>
            <label class="block text-sm font-medium mb-1">Description</label>
            <textarea wire:model="description" rows="3" class="w-full rounded-lg border-slate-300"></textarea>
            @error('description') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            {{-- Capacity --}}
            <div>
                <label class="block text-sm font-medium mb-1">Capacity (persons)</label>
                <input type="number" wire:model="capacity" min="1" class="w-full rounded-lg border-slate-300">
                @error('capacity') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
            {{-- Price --}}
            <div>
                <label class="block text-sm font-medium mb-1">Price (₱)</label>
                <input type="number" step="0.01" wire:model="price" class="w-full rounded-lg border-slate-300">
                @error('price') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            {{-- Status --}}
            <div>
                <label class="block text-sm font-medium mb-1">Status</label>
                <select wire:model="status" class="w-full rounded-lg border-slate-300">
                    <option value="available">Available</option>
                    <option value="occupied">Occupied</option>
                    <option value="maintenance">Maintenance</option>
                </select>
                @error('status') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
            {{-- Active --}}
            <div class="flex items-center pt-6">
                <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-blue-600">
                <label class="ml-2 text-sm">Active (visible to customers)</label>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                Update Property
            </button>
            <a href="{{ route('tenant.properties.index') }}" class="px-6 py-2 border rounded-lg hover:bg-slate-50">
                Cancel
            </a>
        </div>
    </form>
</div>