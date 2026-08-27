{{-- resources/views/tenant/pages/property/⚡edit-property.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\PropertyImage;
use App\Models\PropertyAvailability;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

new 
#[Layout('tenant.layouts.app')]
#[Title('Edit Activity')]
class extends Component {
    use WithFileUploads;

    public Property $property;
    
    #[Validate('required|string|max:255')]
    public $name = '';
    
    #[Validate('nullable|string')]
    public $description = '';
    
    #[Validate('required|exists:property_types,id')]
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

    public ?string $unavailableFrom = null;
    public ?string $unavailableTo = null;

    public bool $showNewTypeForm = false;
    public string $newTypeName = '';

    public function mount($property)
    {
        if (!$property instanceof Property) {
            $property = Property::withoutGlobalScope(TenantScope::class)->findOrFail($property);
        }

        if ($property->tenant_id !== Auth::user()->tenant_id) {
            abort(403);
        }

        $this->property = $property;
        $this->name = $property->name;
        $this->description = $property->description;
        $this->property_type_id = (string) $property->property_type_id;
        $this->capacity = $property->capacity;
        $this->price = $property->price;
        $this->status = $property->status;
        $this->is_active = (bool) $property->is_active;

        $this->existingImages = $property->images->map(fn($img) => [
            'id'   => $img->id,
            'path' => $img->image_path,
            'url'  => asset('storage/'. $img->image_path),
        ])->toArray();
    }

    public function updated($field)
    {
        if (in_array($field, ['name', 'description'])) {
            $this->$field = trim($this->$field);
        }
    }

    public function getPropertyTypesProperty()
    {
        return PropertyType::availableForTenant(Auth::user()->tenant_id)
            ->orderByRaw('tenant_id IS NULL DESC')
            ->orderBy('name')
            ->get();
    }

    public function toggleNewTypeForm()
    {
        $this->showNewTypeForm = !$this->showNewTypeForm;
        $this->newTypeName = '';
    }

    public function createType()
    {
        $this->validate(['newTypeName' => 'required|string|max:255']);
        
        $type = PropertyType::create([
            'tenant_id' => Auth::user()->tenant_id,
            'name' => trim($this->newTypeName),
        ]);
        
        $this->property_type_id = (string) $type->id;
        $this->showNewTypeForm = false;
        $this->newTypeName = '';
        
        session()->flash('message', 'New activity type created.');
    }

    public function removeExistingImage($imageId)
    {
        $image = PropertyImage::where('id', $imageId)
            ->where('property_id', $this->property->id)
            ->first();

        if ($image) {
            Storage::disk('public')->delete($image->image_path);
            $image->delete();

            $this->existingImages = $this->property->fresh()->images->map(fn($img) => [
                'id'   => $img->id,
                'path' => $img->image_path,
                'url'  => asset('storage/'. $img->image_path),
            ])->toArray();
        }
    }

    public function removeNewImage($index)
    {
        if (isset($this->newImages[$index])) {
            unset($this->newImages[$index]);
            $this->newImages = array_values($this->newImages);
        }
    }

    public function makePrimaryNewImage($index)
    {
        if ($index > 0 && isset($this->newImages[$index])) {
            $image = $this->newImages[$index];
            array_splice($this->newImages, $index, 1);
            array_unshift($this->newImages, $image);
        }
    }

    public function resetForm()
    {
        $this->reset([
            'name', 'description', 'property_type_id', 'capacity', 'price',
            'status', 'is_active', 'newImages', 'unavailableFrom', 'unavailableTo',
        ]);
        // Re-fill from original property
        $this->name = $this->property->name;
        $this->description = $this->property->description;
        $this->property_type_id = (string) $this->property->property_type_id;
        $this->capacity = $this->property->capacity;
        $this->price = $this->property->price;
        $this->status = $this->property->status;
        $this->is_active = (bool) $this->property->is_active;
    }

    public function update()
    {
        $this->validate([
            'newImages.*' => 'nullable|image|max:5120',
        ]);

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

        foreach ($this->newImages as $image) {
            $path = $image->store('activity-images', 'public');
            PropertyImage::create([
                'tenant_id'   => Auth::user()->tenant_id,
                'property_id' => $this->property->id,
                'image_path'  => $path,
            ]);
        }

        // Optional availability blackout dates (additive only)
        if ($this->unavailableFrom && $this->unavailableTo) {
            $start = Carbon::parse($this->unavailableFrom);
            $end = Carbon::parse($this->unavailableTo);
            for ($d = $start; $d->lte($end); $d->addDay()) {
                PropertyAvailability::updateOrCreate(
                    [
                        'tenant_id'   => Auth::user()->tenant_id,
                        'property_id' => $this->property->id,
                        'date'        => $d->toDateString(),
                    ],
                    ['is_available' => false]
                );
            }
        }

        session()->flash('message', 'Activity updated successfully.');
        return $this->redirectRoute('tenant.properties.index', navigate: true);
    }
};
?>

@push('styles')
<style>
    select option {
        background: #1e293b;
        color: #e2e8f0;
    }
    .dropzone-active {
        border-color: rgba(55,109,241,.6);
        background: rgba(55,109,241,.05);
    }
</style>
@endpush

<div x-data="editProperty()" class="p-4 sm:p-6 lg:p-8 max-w-5xl mx-auto space-y-6">

    {{-- Flash Message --}}
    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium flex items-center gap-3">
            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('message') }}
        </div>
    @endif

    <div class="flex items-center justify-between pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Edit Activity</h1>
        </div>
        <a href="{{ route('tenant.properties.index') }}" wire:navigate class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-[#376df1] dark:hover:text-blue-400 transition-colors">
            &larr; Back to Activities
        </a>
    </div>

    <form wire:submit="update" class="space-y-6">

        {{-- Basic Information --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Basic Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Activity Name *</label>
                    <input type="text" wire:model="name"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition">
                    @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Activity Type *</label>
                    <div class="flex gap-2">
                        <select wire:model="property_type_id"
                                class="flex-1 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition appearance-none">
                            <option value="">-- Select a Type --</option>
                            @foreach($this->propertyTypes as $type)
                                <option value="{{ $type->id }}">
                                    {{ $type->name }}
                                    {{ is_null($type->tenant_id) ? '(Global)' : '(Custom)' }}
                                </option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="toggleNewTypeForm"
                                class="px-3 py-2 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition text-sm">
                            + New
                        </button>
                    </div>
                    @error('property_type_id') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror

                    @if($showNewTypeForm)
                        <div class="mt-2 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Type Name</label>
                            <div class="flex gap-2">
                                <input type="text" wire:model="newTypeName"
                                       class="flex-1 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg py-2 px-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition"
                                       placeholder="Type name">
                                <button type="button" wire:click="createType"
                                        class="px-3 py-2 rounded-full bg-[#376df1] hover:bg-blue-700 text-white text-sm font-semibold transition">
                                    Add
                                </button>
                            </div>
                            @error('newTypeName') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    @endif
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                <textarea wire:model="description" rows="3"
                          class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition"></textarea>
                @error('description') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Pricing & Capacity --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Pricing & Capacity</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Price (₱ / day)</label>
                    <input type="number" step="0.01" wire:model="price"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition">
                    @error('price') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Capacity (persons)</label>
                    <input type="number" wire:model="capacity" min="1"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition">
                    @error('capacity') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- Status --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Status</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Status</label>
                    <select wire:model="status"
                            class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition appearance-none">
                        <option value="available">Available</option>
                        <option value="occupied">Occupied</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                    @error('status') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="flex items-center pt-6">
                    <input type="checkbox" wire:model="is_active"
                           class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-[#376df1] focus:ring-[#376df1]">
                    <label class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active (visible to customers)</label>
                </div>
            </div>
        </div>

        {{-- Unavailable Dates (Optional) --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Unavailable Dates (Optional)</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Add blackout dates for this activity.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From</label>
                    <input type="date" wire:model="unavailableFrom"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To</label>
                    <input type="date" wire:model="unavailableTo"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition">
                </div>
            </div>
        </div>

        {{-- Images --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Activity Images</h2>

            @if(count($existingImages) > 0)
                <div>
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Current Images</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        @foreach($existingImages as $image)
                            <div class="relative group">
                                <img src="{{ $image['url'] }}" class="w-full h-24 object-cover rounded-lg border border-gray-200 dark:border-gray-700">
                                <button type="button" wire:click="removeExistingImage({{ $image['id'] }})"
                                        class="absolute top-1 right-1 bg-red-600 text-white rounded-full p-1 shadow opacity-0 group-hover:opacity-100 transition"
                                        title="Remove image">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div>
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Add New Images</h3>
                <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-4 text-center hover:border-[#376df1]/50 transition cursor-pointer"
                     @dragover.prevent="$refs.dropzone.classList.add('dropzone-active')"
                     @dragleave.prevent="$refs.dropzone.classList.remove('dropzone-active')"
                     @drop.prevent="handleDrop($event)"
                     x-ref="dropzone">
                    <input type="file" wire:model="newImages" multiple accept="image/*" class="hidden" id="edit-image-upload" @change="handleInput($event)">
                    <label for="edit-image-upload" class="cursor-pointer block">
                        <svg class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Click or drag images to upload</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">PNG, JPG up to 5MB each</p>
                    </label>
                </div>
                <div wire:loading wire:target="newImages" class="text-center text-sm text-gray-500 dark:text-gray-400">Uploading images…</div>
                @error('newImages.*') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror

                @if(count($newImages) > 0)
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-3">
                        @foreach($newImages as $index => $image)
                            <div class="relative group">
                                <img src="{{ $image->temporaryUrl() }}" class="w-full h-24 object-cover rounded-lg border border-gray-200 dark:border-gray-700">
                                @if($index === 0)
                                    <span class="absolute bottom-1 left-1 bg-[#376df1] text-white text-[10px] px-2 py-0.5 rounded-full">Primary</span>
                                @endif
                                <div class="absolute top-1 right-1 flex gap-1">
                                    @if($index > 0)
                                        <button type="button" wire:click="makePrimaryNewImage({{ $index }})" title="Make primary"
                                                class="bg-black/60 text-white rounded-full p-1 hover:bg-black/80 transition">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        </button>
                                    @endif
                                    <button type="button" wire:click="removeNewImage({{ $index }})" title="Remove"
                                            class="bg-red-600 text-white rounded-full p-1 hover:bg-red-700 transition">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Actions --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex flex-wrap gap-3">
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="bg-[#376df1] hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-full shadow-lg shadow-blue-500/20 transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove>Update Activity</span>
                    <span wire:loading class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Saving...
                    </span>
                </button>
                <button type="button" wire:click="resetForm"
                        class="px-6 py-3 rounded-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Reset
                </button>
                <a href="{{ route('tenant.properties.index') }}" wire:navigate
                   class="px-6 py-3 rounded-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 font-medium transition">
                    Cancel
                </a>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
    function editProperty() {
        return {
            handleDrop(event) {
                const files = event.dataTransfer.files;
                if (files.length > 0) {
                    const input = document.getElementById('edit-image-upload');
                    const dataTransfer = new DataTransfer();
                    for (let i = 0; i < files.length; i++) {
                        dataTransfer.items.add(files[i]);
                    }
                    input.files = dataTransfer.files;
                    input.dispatchEvent(new Event('change'));
                }
            },
            handleInput(event) {
                // Livewire handles automatically via wire:model
            }
        }
    }
</script>
@endpush