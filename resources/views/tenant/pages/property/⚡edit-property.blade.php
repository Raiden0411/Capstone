{{-- resources/views/tenant/pages/property/⚡edit-property.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Computed;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\PropertyImage;
use App\Models\PropertyAvailability;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\CarbonPeriod;

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

    #[Validate('required|integer|min:1')]
    public $quantity = 1;

    #[Validate('required|numeric|min:0|max:99999999.99')]
    public $price = 0.00;

    #[Validate('required|in:available,occupied,reserved,maintenance')]
    public $status = 'available';

    #[Validate('boolean')]
    public $is_active = true;

    #[Validate(['newImages.*' => 'nullable|image|max:5120'])]
    public $newImages = [];

    public $existingImages = [];

    #[Validate('nullable|date')]
    public ?string $unavailableFrom = null;

    #[Validate('nullable|date|after_or_equal:unavailableFrom')]
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
        $this->quantity = $property->quantity ?? 1;
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
        if (in_array($field, ['name', 'description', 'newTypeName'])) {
            $this->$field = trim($this->$field);
        }
    }

    #[Computed]
    public function propertyTypes()
    {
        return PropertyType::availableForTenant(Auth::user()->tenant_id)
            ->select('id', 'name', 'tenant_id')
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
        $this->validate(['newTypeName' => 'required|string|max:255|unique:property_types,name']);

        $type = PropertyType::create([
            'tenant_id' => Auth::user()->tenant_id,
            'name'      => $this->newTypeName,
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
        $this->reset();
        $this->mount($this->property);
    }

    public function update()
    {
        $this->validate();

        DB::transaction(function () {
            $tenantId = Auth::user()->tenant_id;

            $this->property->update([
                'name'             => $this->name,
                'description'      => $this->description,
                'property_type_id' => $this->property_type_id,
                'capacity'         => $this->capacity,
                'quantity'         => $this->quantity,
                'price'            => $this->price,
                'status'           => $this->status,
                'is_active'        => $this->is_active,
            ]);

            $imageRecords = [];
            foreach ($this->newImages as $image) {
                $path = $image->store('activity-images', 'public');
                $imageRecords[] = [
                    'tenant_id'   => $tenantId,
                    'property_id' => $this->property->id,
                    'image_path'  => $path,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }
            if (!empty($imageRecords)) {
                PropertyImage::insert($imageRecords);
            }

            if ($this->unavailableFrom && $this->unavailableTo) {
                $period = CarbonPeriod::create($this->unavailableFrom, $this->unavailableTo);
                $availabilityRecords = [];

                foreach ($period as $date) {
                    $availabilityRecords[] = [
                        'tenant_id'    => $tenantId,
                        'property_id'  => $this->property->id,
                        'date'         => $date->toDateString(),
                        'is_available' => false,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ];
                }

                if (!empty($availabilityRecords)) {
                    PropertyAvailability::insert($availabilityRecords);
                }
            }
        });

        session()->flash('message', 'Activity updated successfully.');
        return $this->redirectRoute('tenant.properties.index', navigate: true);
    }
};
?>

<div x-data="{
    previews: [],
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
        this.previews = [];
        const files = event.target.files;
        for (let i = 0; i < files.length; i++) {
            this.previews.push(URL.createObjectURL(files[i]));
        }
    },
    removeClientPreview(index) {
        this.previews.splice(index, 1);
        this.$wire.removeNewImage(index);
    },
    makePrimaryClient(index) {
        const url = this.previews.splice(index, 1)[0];
        this.previews.unshift(url);
        this.$wire.makePrimaryNewImage(index);
    }
}" class="p-4 sm:p-6 lg:p-8 max-w-5xl mx-auto space-y-6">

    {{-- Flash Message --}}
    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium flex items-center gap-3">
            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('message') }}
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Edit Activity</h1>
        </div>
        <a href="{{ route('tenant.properties.index') }}" wire:navigate
           class="btn-secondary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Activities
        </a>
    </div>

    <form wire:submit="update" class="space-y-6">

        {{-- Basic Information --}}
        <div class="card p-5 sm:p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Basic Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Activity Name *</label>
                    <input type="text" wire:model="name" class="input">
                    @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Activity Type *</label>
                    <div class="flex gap-2">
                        <select wire:model="property_type_id" class="select flex-1">
                            <option value="">-- Select a Type --</option>
                            @foreach($this->propertyTypes as $type)
                                <option value="{{ $type->id }}">
                                    {{ $type->name }}
                                    {{ is_null($type->tenant_id) ? '(Global)' : '(Custom)' }}
                                </option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="toggleNewTypeForm"
                                class="btn-secondary text-sm px-3 py-2 active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            + New
                        </button>
                    </div>
                    @error('property_type_id') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror

                    @if($showNewTypeForm)
                        <div class="mt-2 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Type Name</label>
                            <div class="flex gap-2">
                                <input type="text" wire:model="newTypeName" class="input" placeholder="Type name">
                                <button type="button" wire:click="createType"
                                        class="btn-primary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50">
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
                <textarea wire:model="description" rows="3" class="textarea"></textarea>
                @error('description') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Pricing & Capacity --}}
        <div class="card p-5 sm:p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Pricing & Capacity</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Price (₱ / day)</label>
                    <input type="number" step="0.01" wire:model="price" class="input">
                    @error('price') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Capacity (persons)</label>
                    <input type="number" wire:model="capacity" min="1" class="input">
                    @error('capacity') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Quantity (units available)</label>
                    <input type="number" wire:model="quantity" min="1" class="input">
                    @error('quantity') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- Status --}}
        <div class="card p-5 sm:p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Status</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Status</label>
                    <select wire:model="status" class="select">
                        <option value="available">Available</option>
                        <option value="occupied">Occupied</option>
                        <option value="reserved">Reserved</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                    @error('status') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="flex items-center sm:pt-6">
                    <input type="checkbox" wire:model="is_active"
                           class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500">
                    <label class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active (visible to customers)</label>
                </div>
            </div>
        </div>

        {{-- Unavailable Dates --}}
        <div class="card p-5 sm:p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Unavailable Dates (Optional)</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Add blackout dates for this activity.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From</label>
                    <input type="date" wire:model="unavailableFrom" class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To</label>
                    <input type="date" wire:model="unavailableTo" class="input">
                </div>
            </div>
        </div>

        {{-- Images --}}
        <div class="card p-5 sm:p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Activity Images</h2>

            @if(count($existingImages) > 0)
                <div>
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Current Images</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        @foreach($existingImages as $image)
                            <div class="relative group">
                                <img src="{{ $image['url'] }}" class="w-full h-24 object-cover rounded-lg border border-gray-200 dark:border-gray-700">
                                <button type="button" wire:click="removeExistingImage({{ $image['id'] }})"
                                        class="absolute top-1 right-1 bg-red-600 text-white rounded-full p-1 shadow opacity-0 group-hover:opacity-100 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-red-500/50"
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
                <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-4 text-center transition-colors cursor-pointer"
                     :class="dragging ? 'border-primary-600 bg-primary-50 dark:bg-primary-500/10' : 'hover:border-primary-500/50'"
                     x-data="{ dragging: false }"
                     @dragover.prevent="dragging = true"
                     @dragleave.prevent="dragging = false"
                     @drop.prevent="dragging = false; handleDrop($event)">
                    <input type="file" wire:model="newImages" multiple accept="image/*" class="hidden" id="edit-image-upload" @change="handleInput($event)">
                    <label for="edit-image-upload" class="cursor-pointer block">
                        <svg class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Click or drag images to upload</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">PNG, JPG up to 5MB each</p>
                    </label>
                </div>
                <div wire:loading wire:target="newImages" class="text-center text-sm text-gray-500 dark:text-gray-400">Uploading images…</div>
                @error('newImages.*') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-3" x-show="previews.length > 0" x-cloak>
                    <template x-for="(url, index) in previews" :key="index">
                        <div class="relative group">
                            <img :src="url" class="w-full h-24 object-cover rounded-lg border border-gray-200 dark:border-gray-700">
                            <span x-show="index === 0" class="absolute bottom-1 left-1 bg-primary-600 text-white text-[10px] px-2 py-0.5 rounded-full">Primary</span>
                            <div class="absolute top-1 right-1 flex gap-1">
                                <button type="button" x-show="index > 0" @click="makePrimaryClient(index)" title="Make primary"
                                        class="bg-black/60 text-white rounded-full p-1 hover:bg-black/80 transition active:scale-95">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </button>
                                <button type="button" @click="removeClientPreview(index)" title="Remove"
                                        class="bg-red-600 text-white rounded-full p-1 hover:bg-red-700 transition active:scale-95">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="card p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex flex-col sm:flex-row gap-3">
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="btn-primary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    <span wire:loading.remove>Update Activity</span>
                    <span wire:loading class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        Saving...
                    </span>
                </button>
                <button type="button" wire:click="resetForm"
                        class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    Reset
                </button>
                <a href="{{ route('tenant.properties.index') }}" wire:navigate
                   class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    Cancel
                </a>
            </div>
        </div>
    </form>
</div>