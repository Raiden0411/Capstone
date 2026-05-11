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
        $this->is_active = (bool) $property->is_active;

        $this->existingImages = $property->images->map(function ($image) {
            return [
                'id'   => $image->id,
                'path' => $image->image_path,
                'url'  => Storage::url($image->image_path),
            ];
        })->toArray();
    }

    public function updated($property)
    {
        if (in_array($property, ['name', 'description'])) {
            $this->$property = trim($this->$property);
        }
    }

    public function getPropertyTypesProperty()
    {
        return PropertyType::availableForTenant(Auth::user()->tenant_id)
            ->orderByRaw('tenant_id IS NULL DESC')
            ->orderBy('name')
            ->get();
    }

    public function removeExistingImage($imageId)
    {
        $image = PropertyImage::where('id', $imageId)
            ->where('property_id', $this->property->id)
            ->first();

        if ($image) {
            Storage::disk('public')->delete($image->image_path);
            $image->delete();

            $this->existingImages = $this->property->fresh()->images->map(function ($img) {
                return [
                    'id'   => $img->id,
                    'path' => $img->image_path,
                    'url'  => Storage::url($img->image_path),
                ];
            })->toArray();
        }
    }

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
            'newImages.*' => 'nullable|image|max:5120',
        ]);

        $this->property->update([
            'name'              => $this->name,
            'description'       => $this->description,
            'property_type_id'  => $this->property_type_id,
            'capacity'          => $this->capacity,
            'price'             => $this->price,
            'status'            => $this->status,
            'is_active'         => $this->is_active,
        ]);

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

@push('styles')
<style>
    /* Fix invisible options in glass-style selects */
    select option {
        background: #1e293b;
        color: #e2e8f0;
    }
</style>
@endpush

<div class="p-4 sm:p-6 lg:p-10 max-w-4xl mx-auto space-y-6">

    {{-- Flash Message (shown after redirect) --}}
    @if (session()->has('message'))
        <div class="glass-card border-l-4 border-l-brand-400 p-4 text-sm text-white/80 flex items-center gap-3">
            <svg class="w-4 h-4 text-brand-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('message') }}
        </div>
    @endif

    <div class="flex items-center justify-between">
        <h1 class="text-2xl sm:text-3xl font-bold text-white">Edit Property</h1>
        <a href="{{ route('tenant.properties.index') }}" wire:navigate class="text-white/50 hover:text-white font-medium transition-colors">
            &larr; Back to Properties
        </a>
    </div>

    <form wire:submit="update" class="space-y-5 glass-card !rounded-xl p-5 sm:p-6">
        {{-- Name --}}
        <div>
            <label class="block text-sm font-medium text-white/70 mb-1">Property Name</label>
            <input type="text" wire:model="name"
                   class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
            @error('name') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        {{-- Property Type --}}
        <div>
            <label class="block text-sm font-medium text-white/70 mb-1">Property Type</label>
            <select wire:model="property_type_id"
                    class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition appearance-none">
                <option value="">-- Select a Type --</option>
                @foreach($this->propertyTypes as $type)
                    <option value="{{ $type->id }}">
                        {{ $type->name }}
                        {{ is_null($type->tenant_id) ? '(Global)' : '(Custom)' }}
                    </option>
                @endforeach
            </select>
            @error('property_type_id') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        {{-- Description --}}
        <div>
            <label class="block text-sm font-medium text-white/70 mb-1">Description</label>
            <textarea wire:model="description" rows="3"
                      class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"></textarea>
            @error('description') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        {{-- Capacity & Price --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-white/70 mb-1">Capacity (persons)</label>
                <input type="number" wire:model="capacity" min="1"
                       class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                @error('capacity') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-white/70 mb-1">Price (₱)</label>
                <input type="number" step="0.01" wire:model="price"
                       class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                @error('price') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Status & Active --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-white/70 mb-1">Status</label>
                <select wire:model="status"
                        class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition appearance-none">
                    <option value="available">Available</option>
                    <option value="occupied">Occupied</option>
                    <option value="maintenance">Maintenance</option>
                </select>
                @error('status') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div class="flex items-center pt-6">
                <input type="checkbox" wire:model="is_active"
                       class="rounded border-white/20 bg-white/5 text-brand-600 focus:ring-brand-500">
                <label class="ml-2 text-sm text-white/70">Active (visible to customers)</label>
            </div>
        </div>

        {{-- Image Management --}}
        <div class="border-t border-white/10 pt-6 space-y-6">
            <h2 class="text-lg font-semibold text-white">Property Images</h2>

            {{-- Existing Images --}}
            @if(count($existingImages) > 0)
            <div>
                <h3 class="text-sm font-medium text-white/70 mb-2">Current Images</h3>
                <div class="grid grid-cols-3 sm:grid-cols-4 gap-3">
                    @foreach($existingImages as $image)
                        <div class="relative group">
                            <img src="{{ $image['url'] }}" class="w-full h-24 object-cover rounded-lg border border-white/10">
                            <button type="button" wire:click="removeExistingImage({{ $image['id'] }})"
                                    class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 shadow opacity-0 group-hover:opacity-100 transition-colors"
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
                <h3 class="text-sm font-medium text-white/70 mb-2">Add New Images</h3>
                <div class="border-2 border-dashed border-white/20 rounded-xl p-4 text-center hover:border-brand-400/50 transition cursor-pointer">
                    <input type="file" wire:model="newImages" multiple accept="image/*" class="hidden" id="edit-image-upload">
                    <label for="edit-image-upload" class="cursor-pointer block">
                        <svg class="mx-auto h-8 w-8 text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <p class="mt-2 text-sm text-white/60">Click or drag images to upload</p>
                        <p class="text-xs text-white/40">PNG, JPG, GIF up to 5MB each</p>
                    </label>
                </div>
                @error('newImages.*') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror

                @if(count($newImages) > 0)
                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-3 mt-3">
                        @foreach($newImages as $index => $image)
                            <div class="relative group">
                                <img src="{{ $image->temporaryUrl() }}" class="w-full h-24 object-cover rounded-lg border border-white/10">
                                <button type="button" wire:click="removeNewImage({{ $index }})"
                                        class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 shadow opacity-0 group-hover:opacity-100 transition-colors"
                                        title="Remove image">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Form Actions --}}
        <div class="flex items-center gap-3 pt-4 border-t border-white/10">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="bg-brand-600 hover:bg-brand-500 text-white font-medium py-2.5 px-6 rounded-xl shadow-lg shadow-brand-500/20 transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove>Update Property</span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Saving...
                </span>
            </button>
            <a href="{{ route('tenant.properties.index') }}" wire:navigate class="glass px-6 py-3 rounded-xl text-white/80 hover:bg-white/10 font-medium transition">
                Cancel
            </a>
        </div>
    </form>
</div>