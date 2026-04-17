<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\PropertyImage;
use Illuminate\Support\Facades\Auth;

new 
#[Layout('tenant.layouts.app')]
#[Title('Create Property')]
class extends Component {
    use WithFileUploads;

    #[Validate('required|string|max:255')]
    public $name = '';
    
    #[Validate('nullable|string')]
    public $description = '';
    
    #[Validate('required|exists:property_types,id')]
    public $property_type_id = '';
    
    #[Validate('required|integer|min:1')]
    public $capacity = 1;
    
    #[Validate('required|numeric|min:0')]
    public $price = 0.00;
    
    #[Validate('required|in:available,occupied,maintenance')]
    public $status = 'available';
    
    #[Validate('boolean')]
    public $is_active = true;

    public $images = [];
    public $temporaryImages = [];

    public function updatedImages()
    {
        $this->validate(['images.*' => 'image|max:5120']);
        
        $this->temporaryImages = [];
        foreach ($this->images as $image) {
            $this->temporaryImages[] = [
                'url' => $image->temporaryUrl(),
                'name' => $image->getClientOriginalName(),
            ];
        }
    }

    public function removeImage($index)
    {
        unset($this->images[$index], $this->temporaryImages[$index]);
        $this->images = array_values($this->images);
        $this->temporaryImages = array_values($this->temporaryImages);
    }

    public function getPropertyTypesProperty()
    {
        return PropertyType::availableForTenant(Auth::user()->tenant_id)
            ->orderByRaw('tenant_id IS NULL DESC')
            ->orderBy('name')
            ->get();
    }

    public function save()
    {
        $this->validate();

        $property = Property::create([
            'tenant_id'         => Auth::user()->tenant_id,
            'property_type_id'  => $this->property_type_id,
            'name'              => trim($this->name),
            'description'       => trim($this->description),
            'capacity'          => $this->capacity,
            'price'             => $this->price,
            'status'            => $this->status,
            'is_active'         => $this->is_active,
        ]);

        foreach ($this->images as $image) {
            $path = $image->store('property-images', 'public');
            PropertyImage::create([
                'tenant_id'   => Auth::user()->tenant_id,
                'property_id' => $property->id,
                'image_path'  => $path,
            ]);
        }

        session()->flash('message', 'Property created successfully.');
        return $this->redirectRoute('tenant.properties.index', navigate: true);
    }
};
?>

<div class="p-6 max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Add New Property</h1>

    <form wire:submit="save" class="space-y-6 bg-white p-6 rounded-xl shadow">
        {{-- Name --}}
        <div>
            <label class="block text-sm font-medium mb-1">Property Name</label>
            <input type="text" wire:model="name" class="w-full rounded-lg border-slate-300" placeholder="e.g. Room 101, Cottage A">
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
            <textarea wire:model="description" rows="3" class="w-full rounded-lg border-slate-300" placeholder="Optional details about the property"></textarea>
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

        {{-- Image Upload --}}
        <div>
            <label class="block text-sm font-medium mb-2">Property Images</label>
            
            <div class="border-2 border-dashed border-slate-300 rounded-lg p-6 text-center hover:border-blue-400 transition">
                <input type="file" wire:model="images" multiple accept="image/*" class="hidden" id="image-upload">
                <label for="image-upload" class="cursor-pointer">
                    <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <p class="mt-2 text-sm text-slate-600">Click or drag images to upload</p>
                    <p class="text-xs text-slate-400">PNG, JPG, GIF up to 5MB each</p>
                </label>
            </div>
            @error('images.*') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

            @if(count($temporaryImages) > 0)
                <div class="mt-4 grid grid-cols-3 sm:grid-cols-4 gap-4">
                    @foreach($temporaryImages as $index => $image)
                        <div class="relative group">
                            <img src="{{ $image['url'] }}" class="h-24 w-full object-cover rounded-lg border">
                            <button type="button" wire:click="removeImage({{ $index }})" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 shadow hover:bg-red-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="flex gap-3 pt-4">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-lg shadow-sm transition flex items-center gap-2 data-loading:opacity-75">
                <span class="in-data-loading:hidden">Create Property</span>
                <span class="not-in-data-loading:hidden">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Saving...
                </span>
            </button>
            <a href="{{ route('tenant.properties.index') }}" wire:navigate class="bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 font-medium py-2.5 px-6 rounded-lg shadow-sm transition">
                Cancel
            </a>
        </div>
    </form>
</div>