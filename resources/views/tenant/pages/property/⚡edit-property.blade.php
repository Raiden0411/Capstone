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
use Illuminate\Support\Facades\Storage;

new 
#[Layout('tenant.layouts.app')]
#[Title('Edit Property')]
class extends Component {
    use WithFileUploads;

    public Property $property;
    
    #[Validate('required|string|max:255')]
    public $name = '';
    
    #[Validate('nullable|string')]
    public $description = '';
    
    #[Validate('required')]
    public $property_type_id = '';
    
    #[Validate('required|integer|min:1')]
    public $capacity = 1;
    
    #[Validate('required|numeric|min:0|max:99999999.99')]
    public $price = 0.00;
    
    #[Validate('required|in:available,occupied,maintenance')]
    public $status = 'available';
    
    #[Validate('boolean')]
    public $is_active = true;

    // Image handling
    public $newImages = [];
    public $existingImages = [];

    public function mount(Property $property)
    {
        $this->property = $property;
        $this->name = $property->name;
        $this->description = $property->description;
        $this->property_type_id = $property->property_type_id;
        $this->capacity = $property->capacity;
        $this->price = $property->price;
        $this->status = $property->status;
        // Cast to boolean so checkbox reflects correctly
        $this->is_active = (bool) $property->is_active;

        // Load existing images
        $this->existingImages = $property->images->map(function ($image) {
            return [
                'id'   => $image->id,
                'path' => $image->image_path,
                'url'  => Storage::url($image->image_path),
            ];
        })->toArray();
    }

    public function getPropertyTypesProperty()
    {
        return PropertyType::availableForTenant(Auth::user()->tenant_id)
            ->orderByRaw('tenant_id IS NULL DESC')
            ->orderBy('name')
            ->get();
    }

    // Remove an existing image
    public function removeExistingImage($imageId)
    {
        $image = PropertyImage::where('id', $imageId)
            ->where('property_id', $this->property->id)
            ->first();

        if ($image) {
            Storage::disk('public')->delete($image->image_path);
            $image->delete();

            // Refresh the existing images list
            $this->existingImages = $this->property->fresh()->images->map(function ($img) {
                return [
                    'id'   => $img->id,
                    'path' => $img->image_path,
                    'url'  => Storage::url($img->image_path),
                ];
            })->toArray();
        }
    }

    // Remove a newly uploaded (not yet saved) image
    public function removeNewImage($index)
    {
        if (isset($this->newImages[$index])) {
            unset($this->newImages[$index]);
            $this->newImages = array_values($this->newImages);
        }
    }

    public function update()
    {
        $this->validate([
            'newImages.*' => 'nullable|image|max:5120', // 5MB max per image
        ]);

        // Update property details
        $this->property->update([
            'name'              => $this->name,
            'description'       => $this->description,
            'property_type_id'  => $this->property_type_id,
            'capacity'          => $this->capacity,
            'price'             => $this->price,
            'status'            => $this->status,
            'is_active'         => $this->is_active,
        ]);

        // Store new images
        foreach ($this->newImages as $image) {
            $path = $image->store('property-images', 'public');
            PropertyImage::create([
                'tenant_id'   => Auth::user()->tenant_id,
                'property_id' => $this->property->id,
                'image_path'  => $path,
            ]);
        }

        session()->flash('message', 'Property updated successfully.');
        return $this->redirectRoute('tenant.properties.index', navigate: true);
    }
};
?>

<div class="p-6 max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Edit Property</h1>

    @if (session()->has('message'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
            {{ session('message') }}
        </div>
    @endif

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

        {{-- ========== IMAGE MANAGEMENT ========== --}}
        <div class="border-t border-slate-200 pt-6 space-y-6">
            <h2 class="text-lg font-semibold text-slate-800">Property Images</h2>

            {{-- Existing Images --}}
            @if(count($existingImages) > 0)
            <div>
                <h3 class="text-sm font-medium text-slate-700 mb-2">Current Images</h3>
                <div class="grid grid-cols-3 gap-3">
                    @foreach($existingImages as $image)
                        <div class="relative group">
                            <img src="{{ $image['url'] }}" class="w-full h-24 object-cover rounded-lg border border-slate-200">
                            <button type="button" wire:click="removeExistingImage({{ $image['id'] }})"
                                    class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 shadow opacity-0 group-hover:opacity-100 transition"
                                    title="Remove image">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Upload New Images --}}
            <div>
                <h3 class="text-sm font-medium text-slate-700 mb-2">Add New Images</h3>
                <input type="file" wire:model="newImages" multiple accept="image/*" class="w-full text-sm">
                @error('newImages.*') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror

                @if(count($newImages) > 0)
                    <div class="grid grid-cols-3 gap-3 mt-3">
                        @foreach($newImages as $index => $image)
                            <div class="relative group">
                                <img src="{{ $image->temporaryUrl() }}" class="w-full h-24 object-cover rounded-lg border border-slate-200">
                                <button type="button" wire:click="removeNewImage({{ $index }})"
                                        class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 shadow opacity-0 group-hover:opacity-100 transition"
                                        title="Remove image">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Submit / Cancel --}}
        <div class="flex gap-3">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2 data-loading:opacity-75">
                <span class="in-data-loading:hidden">Update Property</span>
                <span class="not-in-data-loading:hidden">Saving...</span>
            </button>
            <a href="{{ route('tenant.properties.index') }}" wire:navigate class="px-6 py-2 border rounded-lg hover:bg-slate-50">
                Cancel
            </a>
        </div>
    </form>
</div>