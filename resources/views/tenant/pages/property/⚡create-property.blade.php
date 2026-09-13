{{-- resources/views/tenant/pages/property/⚡create-property.blade.php --}}
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\CarbonPeriod;
use Carbon\Carbon;

new
#[Layout('tenant.layouts.app')]
#[Title('Create Activity')]
class extends Component {
    use WithFileUploads;

    #[Validate('required|string|max:255')]
    public $name = '';

    #[Validate('nullable|string|max:2000')]
    public $description = '';

    #[Validate('required|exists:property_types,id')]
    public $property_type_id = '';

    #[Validate('required|integer|min:1|max:100000')]
    public $capacity = 1;

    #[Validate('required|integer|min:1|max:100000')]
    public $quantity = 1;

    #[Validate('required|numeric|min:0|max:99999999.99')]
    public $price = 0.00;

    #[Validate('required|in:available,occupied,reserved,maintenance')]
    public $status = 'available';

    #[Validate('boolean')]
    public $is_active = true;

    #[Validate(['images.*' => 'image|max:5120'])]
    public $images = [];

    #[Validate('nullable|date')]
    public ?string $unavailableFrom = null;

    #[Validate('nullable|date|after_or_equal:unavailableFrom')]
    public ?string $unavailableTo = null;

    // New type modal
    public bool $showNewTypeModal = false;
    public string $newTypeName = '';

    public function mount(): void
    {
        $user = Auth::user();
        if (!$user || !$user->tenant_id) {
            abort(403);
        }

        $canManage = $user->hasAnyRole(['admin', 'super-admin'])
            || $user->getAllPermissions()->contains('name', 'manage properties');

        if (!$canManage) {
            abort(403, 'You are not authorized to create activities.');
        }
    }

    public function updated($property): void
    {
        if (in_array($property, ['name', 'description', 'newTypeName'], true)) {
            $this->$property = trim((string) $this->$property);
        }
    }

    public function removeImage(int $index): void
    {
        if (!isset($this->images[$index])) {
            return;
        }

        unset($this->images[$index]);
        $this->images = array_values($this->images);
    }

    public function makePrimary(int $index): void
    {
        if ($index > 0 && isset($this->images[$index])) {
            $image = $this->images[$index];
            array_splice($this->images, $index, 1);
            array_unshift($this->images, $image);
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PropertyType>
     */
    #[Computed]
    public function propertyTypes()
    {
        return PropertyType::availableForTenant(Auth::user()->tenant_id)
            ->orderByRaw('tenant_id IS NULL DESC')
            ->orderBy('name')
            ->get();
    }

    public function openNewTypeModal(): void
    {
        $this->reset(['newTypeName']);
        $this->resetErrorBag(['newTypeName']);
        $this->showNewTypeModal = true;
    }

    public function closeNewTypeModal(): void
    {
        $this->showNewTypeModal = false;
        $this->reset(['newTypeName']);
        $this->resetErrorBag(['newTypeName']);
    }

    public function createType(): void
    {
        $this->validate([
            'newTypeName' => 'required|string|min:2|max:255',
        ]);

        // Uniqueness scoped to tenant (globals OR this tenant's own types)
        $tenantId = Auth::user()->tenant_id;
        $exists = PropertyType::query()
            ->where('name', $this->newTypeName)
            ->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            })
            ->exists();

        if ($exists) {
            $this->addError('newTypeName', 'A type with this name already exists.');
            return;
        }

        try {
            $type = PropertyType::create([
                'tenant_id' => $tenantId,
                'name'      => $this->newTypeName,
            ]);

            $this->property_type_id = (string) $type->id;
            $this->closeNewTypeModal();

            session()->flash('message', "Activity type '{$type->name}' created and selected.");
        } catch (\Exception $e) {
            Log::error('Property type creation failed: ' . $e->getMessage(), [
                'tenant_id' => $tenantId,
                'name'      => $this->newTypeName,
            ]);
            $this->addError('newTypeName', 'Failed to create type. Please try again.');
        }
    }

    public function save()
    {
        $this->validate();

        try {
            DB::transaction(function () {
                $tenantId = Auth::user()->tenant_id;

                $property = Property::create([
                    'tenant_id'        => $tenantId,
                    'property_type_id' => $this->property_type_id,
                    'name'             => $this->name,
                    'description'      => $this->description ?: null,
                    'capacity'         => $this->capacity,
                    'quantity'         => $this->quantity,
                    'price'            => $this->price,
                    'status'           => $this->status,
                    'is_active'        => $this->is_active,
                ]);

                $imageRecords = [];
                foreach ($this->images as $image) {
                    $path = $image->store('activity-images', 'public');
                    $imageRecords[] = [
                        'tenant_id'   => $tenantId,
                        'property_id' => $property->id,
                        'image_path'  => $path,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ];
                }

                if (!empty($imageRecords)) {
                    PropertyImage::insert($imageRecords);
                }

                if ($this->unavailableFrom && $this->unavailableTo) {
                    $period = CarbonPeriod::create(
                        Carbon::parse($this->unavailableFrom),
                        Carbon::parse($this->unavailableTo)
                    );

                    $availabilityRecords = [];
                    foreach ($period as $date) {
                        $availabilityRecords[] = [
                            'tenant_id'    => $tenantId,
                            'property_id'  => $property->id,
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
        } catch (\Exception $e) {
            Log::error('Activity creation failed: ' . $e->getMessage(), [
                'tenant_id' => Auth::user()->tenant_id,
                'name'      => $this->name,
            ]);
            session()->flash('error', 'Failed to create activity. Please try again.');
            return;
        }

        session()->flash('message', 'Activity created successfully.');
        return $this->redirectRoute('tenant.properties.index', navigate: true);
    }
};
?>

<div x-data="{
        previews: [],
        handleDrop(event) {
            const files = event.dataTransfer.files;
            if (files.length > 0) {
                const input = document.getElementById('image-upload');
                const dt = new DataTransfer();
                for (let i = 0; i < files.length; i++) dt.items.add(files[i]);
                input.files = dt.files;
                input.dispatchEvent(new Event('change'));
            }
        },
        handleInput(event) {
            this.previews = [];
            const files = event.target.files;
            for (let i = 0; i < files.length; i++) {
                this.previews.push({ url: URL.createObjectURL(files[i]) });
            }
        },
        removeClientPreview(index) {
            URL.revokeObjectURL(this.previews[index]?.url);
            this.previews.splice(index, 1);
            this.$wire.removeImage(index);
        },
        makePrimaryClient(index) {
            const item = this.previews.splice(index, 1)[0];
            this.previews.unshift(item);
            this.$wire.makePrimary(index);
        }
    }"
    class="p-4 sm:p-6 lg:p-8 max-w-5xl mx-auto space-y-6"
    x-on:livewire-upload-start.window="if ($event.detail?.property === 'images') { $dispatch('toast', { message: 'Uploading images…', type: 'info' }) }"
>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 border-l-4 border-l-emerald-500 p-4 rounded-md text-sm text-emerald-700 dark:text-emerald-300 font-medium flex items-center gap-3">
            <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 border-l-4 border-l-rose-500 p-4 rounded-md text-sm text-rose-700 dark:text-rose-300 font-medium flex items-center gap-3">
            <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Header — matches view-role pattern --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-800">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Add New Activity</h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">Create a bookable activity your customers can reserve.</p>
        </div>
        <a href="{{ route('tenant.properties.index') }}" wire:navigate
           class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 font-semibold text-xs sm:text-sm shadow-sm transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Activities
        </a>
    </div>

    <form wire:submit="save" class="space-y-6">

        {{-- ========== BASIC INFORMATION ========== --}}
        <div class="card p-5 sm:p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Basic Information</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="field-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Activity Name *</label>
                    <input type="text" id="field-name" wire:model="name" class="input" placeholder="e.g. Gawahon Falls Tour">
                    @error('name') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- ✅ Type field with dedicated "Add New Type" trigger --}}
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="field-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Activity Type *</label>
                        <button type="button" wire:click="openNewTypeModal"
                                class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95 transition-transform">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                            Add New Type
                        </button>
                    </div>
                    <select id="field-type" wire:model="property_type_id" class="select">
                        <option value="">-- Select a Type --</option>
                        @foreach($this->propertyTypes as $type)
                            <option value="{{ $type->id }}">
                                {{ $type->name }}{{ is_null($type->tenant_id) ? ' (Global)' : ' (Custom)' }}
                            </option>
                        @endforeach
                    </select>
                    @error('property_type_id') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div>
                <label for="field-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                <textarea id="field-description" wire:model="description" rows="3" class="textarea" placeholder="Optional details about this activity"></textarea>
                @error('description') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- ========== PRICING & CAPACITY ========== --}}
        <div class="card p-5 sm:p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Pricing & Capacity</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="field-price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Price (₱ / day)</label>
                    <input type="number" id="field-price" step="0.01" min="0" wire:model="price" class="input" placeholder="0.00">
                    @error('price') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="field-capacity" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Capacity (persons)</label>
                    <input type="number" id="field-capacity" wire:model="capacity" min="1" class="input">
                    @error('capacity') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="field-quantity" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Quantity (units available)</label>
                    <input type="number" id="field-quantity" wire:model="quantity" min="1" class="input">
                    @error('quantity') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- ========== STATUS ========== --}}
        <div class="card p-5 sm:p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Status</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="field-status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Status</label>
                    <select id="field-status" wire:model="status" class="select">
                        <option value="available">Available</option>
                        <option value="occupied">Occupied</option>
                        <option value="reserved">Reserved</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                    @error('status') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="flex items-center sm:pt-6">
                    <label class="relative inline-flex items-center cursor-pointer focus-within:ring-2 focus-within:ring-primary-500/50 rounded-full">
                        <input type="checkbox" wire:model="is_active" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full peer peer-checked:bg-primary-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active (visible to customers)</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- ========== BLACKOUT DATES ========== --}}
        <div class="card p-5 sm:p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Unavailable Dates (Optional)</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 -mt-2">Block out dates when this activity is not available.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="field-unavail-from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From</label>
                    <input type="date" id="field-unavail-from" wire:model="unavailableFrom" class="input">
                    @error('unavailableFrom') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="field-unavail-to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To</label>
                    <input type="date" id="field-unavail-to" wire:model="unavailableTo" class="input">
                    @error('unavailableTo') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- ========== IMAGE UPLOAD ========== --}}
        <div class="card p-5 sm:p-6 space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Activity Images</h2>

            <div x-data="{ dragging: false }"
                 @dragover.prevent="dragging = true"
                 @dragleave.prevent="dragging = false"
                 @drop.prevent="dragging = false; handleDrop($event)"
                 :class="dragging ? 'border-primary-600 bg-primary-50 dark:bg-primary-500/10' : 'border-gray-300 dark:border-gray-600 hover:border-primary-500/50'"
                 class="relative border-2 border-dashed rounded-xl p-6 text-center transition-colors cursor-pointer">
                <input type="file" id="image-upload" wire:model="images" multiple accept="image/*" class="hidden" @change="handleInput($event)">
                <label for="image-upload" class="cursor-pointer block">
                    <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <p class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">Click or drag images to upload</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">PNG, JPG, WebP up to 5MB each</p>
                </label>
            </div>

            <div wire:loading wire:target="images" class="text-center text-sm text-primary-600 dark:text-primary-400 flex items-center justify-center gap-2">
                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                Uploading images…
            </div>

            @error('images.*') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror

            {{-- Preview grid --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4" x-show="previews.length > 0" x-cloak>
                <template x-for="(item, index) in previews" :key="index">
                    <div class="relative group">
                        <img :src="item.url" class="h-24 w-full object-cover rounded-lg border border-gray-200 dark:border-gray-700">
                        <span x-show="index === 0"
                              class="absolute bottom-1 left-1 bg-primary-600 text-white text-[10px] font-semibold px-2 py-0.5 rounded-full">
                            Primary
                        </span>
                        <div class="absolute top-1 right-1 flex gap-1">
                            <button type="button"
                                    x-show="index > 0"
                                    @click="makePrimaryClient(index)"
                                    title="Make primary"
                                    class="bg-black/60 text-white rounded-full p-1 hover:bg-black/80 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/50">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </button>
                            <button type="button"
                                    @click="removeClientPreview(index)"
                                    title="Remove"
                                    class="bg-rose-600 text-white rounded-full p-1 hover:bg-rose-700 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/50">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ========== FORM ACTIONS ========== --}}
        <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="btn-primary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="save">Create Activity</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Saving…
                </span>
            </button>
            <a href="{{ route('tenant.properties.index') }}" wire:navigate
               class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                Cancel
            </a>
        </div>
    </form>

    {{-- ========== NEW TYPE MODAL ========== --}}
    @if($showNewTypeModal)
        <div class="fixed inset-0 z-[200] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
             x-on:keydown.escape.window="$wire.closeNewTypeModal()"
             @click.self="$wire.closeNewTypeModal()">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6 border border-gray-200 dark:border-gray-700"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <div class="p-2 bg-primary-50 dark:bg-primary-500/10 rounded-lg text-primary-600 dark:text-primary-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 01.586 1.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Add Activity Type</h3>
                    </div>
                    <button type="button" wire:click="closeNewTypeModal"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition active:scale-95 rounded-lg p-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                    Create a custom type for your business. It will be selectable only within your tenant.
                </p>

                <div class="space-y-4">
                    <div>
                        <label for="field-new-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type Name *</label>
                        <input type="text"
                               id="field-new-type"
                               wire:model="newTypeName"
                               wire:keydown.enter.prevent="createType"
                               class="input"
                               placeholder="e.g. Island Hopping"
                               autofocus>
                        @error('newTypeName') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    @if(!empty($this->propertyTypes->whereNull('tenant_id')->all()))
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700 p-3">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Existing Global Types</p>
                            <div class="flex flex-wrap gap-1">
                                @foreach($this->propertyTypes->whereNull('tenant_id') as $global)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-[10px] font-medium text-gray-600 dark:text-gray-300">
                                        {{ $global->name }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" wire:click="closeNewTypeModal"
                            class="btn-secondary active:scale-95 transition-transform">
                        Cancel
                    </button>
                    <button type="button" wire:click="createType" wire:loading.attr="disabled" wire:target="createType"
                            class="btn-primary active:scale-95 transition-transform inline-flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="createType">Create Type</span>
                        <span wire:loading wire:target="createType" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            Creating…
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>