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
use App\Scopes\TenantScope;
use App\Traits\ChecksTenantPermissions;
use App\Traits\HandlesImageUploads;
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
    use ChecksTenantPermissions;
    use HandlesImageUploads;

    #[Validate('required|string|max:255')]
    public $name = '';

    public $property_type_id = '';

    #[Validate('nullable|string|max:2000')]
    public $description = '';

    #[Validate('required|numeric|min:0|max:99999999.99')]
    public $price = 0.00;

    #[Validate('required|integer|min:1|max:100000')]
    public $capacity = 1;

    #[Validate('required|integer|min:1|max:100000')]
    public $quantity = 1;

    #[Validate('required|in:available,occupied,reserved,maintenance')]
    public $status = 'available';

    #[Validate('boolean')]
    public $is_active = true;

    #[Validate('nullable|date')]
    public ?string $unavailableFrom = null;

    #[Validate('nullable|date')]
    public ?string $unavailableTo = null;

    public $image;

    public bool $showNewTypeModal = false;
    public string $newTypeName = '';

    public function mount(): void
    {
        $this->authorizeManageProperties();
    }

    public function hydrate(): void
    {
        $this->authorizeManageProperties();
    }

    protected function authorizeManageProperties(): void
    {
        $user = Auth::user();

        abort_unless($user && $user->tenant_id, 403);

        abort_unless(
            $this->tenantCan('manage properties'),
            403,
            'You are not authorized to create activities.'
        );
    }

    public function updated($property): void
    {
        if (in_array($property, ['name', 'description', 'newTypeName'], true)) {
            $this->$property = trim((string) $this->$property);
        }
    }

    protected function rules(): array
    {
        $tenantId = Auth::user()->tenant_id;

        return [
            'property_type_id' => [
                'required',
                function ($attribute, $value, $fail) use ($tenantId): void {
                    $exists = PropertyType::withoutGlobalScope(TenantScope::class)
                        ->where('id', $value)
                        ->where(function ($q) use ($tenantId) {
                            $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
                        })
                        ->exists();

                    if (! $exists) {
                        $fail('The selected activity type is not available for your business.');
                    }
                },
            ],

            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'unavailableTo' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail): void {
                    if (! $value || ! $this->unavailableFrom) {
                        return;
                    }
                    try {
                        $from = Carbon::parse($this->unavailableFrom);
                        $to   = Carbon::parse($value);
                        if ($to->lt($from)) {
                            $fail('The "to" date must be on or after the "from" date.');
                        }
                    } catch (\Throwable) {
                        // Invalid date — the `date` rule already handles it.
                    }
                },
            ],
        ];
    }

    public function removeImage(): void
    {
        $this->authorizeManageProperties();

        $this->image = null;

        $this->dispatch('property-image-cleared');
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

    public function openNewTypeModal(): void
    {
        $this->authorizeManageProperties();

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
        $this->authorizeManageProperties();

        $this->validate([
            'newTypeName' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $tenantId = Auth::user()->tenant_id;
        $exists = PropertyType::withoutGlobalScope(TenantScope::class)
            ->where('name', $this->newTypeName)
            ->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            })
            ->exists();

        if ($exists) {
            $this->addError('newTypeName', 'A type with this name already exists for your business.');
            return;
        }

        try {
            $type = PropertyType::create([
                'tenant_id' => $tenantId,
                'name'      => $this->newTypeName,
            ]);

            $this->property_type_id = (string) $type->id;
            $this->closeNewTypeModal();

            unset($this->propertyTypes);

            session()->flash('message', "Activity type '{$type->name}' created and selected.");
        } catch (\Throwable $e) {
            Log::error('Property type creation failed: ' . $e->getMessage(), [
                'tenant_id' => $tenantId,
                'name'      => $this->newTypeName,
            ]);
            $this->addError('newTypeName', 'Failed to create type. Please try again.');
        }
    }

    public function save()
    {
        $this->authorizeManageProperties();

        $this->validate();

        $tenantId = Auth::user()->tenant_id;

        $storedPath = null;
        try {
            if ($this->image) {
                $storedPath = $this->storeImage($this->image, 'activity-images', 'public', 'property');

                if (! $storedPath) {
                    throw new \RuntimeException('Failed to store the uploaded image.');
                }
            }
        } catch (\Throwable $e) {
            if ($storedPath && Storage::disk('public')->exists($storedPath)) {
                Storage::disk('public')->delete($storedPath);
            }
            Log::error('Activity image store failed: ' . $e->getMessage(), [
                'tenant_id' => $tenantId,
            ]);
            session()->flash('error', 'Failed to upload the image. Please try again.');
            return null;
        }

        try {
            DB::transaction(function () use ($tenantId, $storedPath): void {
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

                if ($storedPath) {
                    PropertyImage::create([
                        'tenant_id'   => $tenantId,
                        'property_id' => $property->id,
                        'image_path'  => $storedPath,
                    ]);
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

                    if (! empty($availabilityRecords)) {
                        PropertyAvailability::insert($availabilityRecords);
                    }
                }
            });
        } catch (\Throwable $e) {
            if ($storedPath && Storage::disk('public')->exists($storedPath)) {
                Storage::disk('public')->delete($storedPath);
            }

            Log::error('Activity creation failed: ' . $e->getMessage(), [
                'tenant_id' => $tenantId,
                'name'      => $this->name,
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);
            session()->flash('error', 'Failed to create activity. Please try again.');
            return null;
        }

        session()->flash('message', 'Activity created successfully.');
        return $this->redirectRoute('tenant.properties.index', navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-6xl mx-auto space-y-6
            pb-[max(1rem,env(safe-area-inset-bottom))]">

    @if (session()->has('message'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 4000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>{{ session('message') }}</span>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 rounded-md text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/10
                           transition-all duration-200 active:scale-95
                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    @if (session()->has('error'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 5000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                           transition-all duration-200 active:scale-95
                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-800">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Inventory</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                Add New Activity
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                Create a bookable activity your customers can reserve.
            </p>
        </div>
        <a href="{{ route('tenant.properties.index') }}" wire:navigate
           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                  transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span>Back to Activities</span>
        </a>
    </div>

    <form wire:submit="save">
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

            <div class="lg:col-span-3 space-y-6">

                <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Activity Details
                        </h2>
                    </div>

                    <div>
                        <label for="field-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Activity Name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="field-name" wire:model="name" class="input" placeholder="e.g. Gawahon Falls Tour">
                        @error('name') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="field-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Activity Type <span class="text-rose-500">*</span>
                            </label>
                            <button type="button"
                                    wire:click="openNewTypeModal"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline
                                           py-2.5 -my-2.5 px-1 -mx-1 rounded
                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95 transition-transform">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                </svg>
                                <span>Add New Type</span>
                            </button>
                        </div>
                        <select id="field-type" wire:model="property_type_id" class="select">
                            <option value="">— Select a Type —</option>
                            @foreach($this->propertyTypes as $type)
                                <option value="{{ $type->id }}" wire:key="type-opt-{{ $type->id }}">
                                    {{ $type->name }}{{ is_null($type->tenant_id) ? ' (Global)' : ' (Custom)' }}
                                </option>
                            @endforeach
                        </select>
                        @error('property_type_id') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="field-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Description
                        </label>
                        <textarea id="field-description"
                                  wire:model="description"
                                  rows="4"
                                  class="textarea"
                                  placeholder="Optional details about this activity"></textarea>
                        @error('description') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Pricing &amp; Capacity
                        </h2>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="field-price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Price (₱ / day)
                            </label>
                            <input type="number" id="field-price" step="0.01" min="0" wire:model="price" class="input" placeholder="0.00">
                            @error('price') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="field-capacity" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Capacity (persons)
                            </label>
                            <input type="number" id="field-capacity" wire:model="capacity" min="1" class="input">
                            @error('capacity') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="field-quantity" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Quantity (units)
                            </label>
                            <input type="number" id="field-quantity" wire:model="quantity" min="1" class="input">
                            @error('quantity') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

            </div>

            <div class="lg:col-span-2 space-y-6">

                {{--
                    ═══ Activity Photo ═══
                    One Alpine scope owns both the picker and the preview URL.
                    Two functions spread into it:
                      • imageCropper({...})  — pick, crop, upload
                      • avatarPreview()      — previewUrl state + object-URL lifecycle
                --}}
                <div
                    x-data="{
                        ...imageCropper({
                            wireProperty: 'image',
                            aspect: 16 / 9,
                            title: 'Crop activity photo',
                            description: 'Wide 16:9 crop works best',
                            previewEvent: 'property-image-preview',
                        }),
                        ...avatarPreview(),
                        dragging: false,
                    }"
                    x-init="init()"
                    x-on:property-image-preview.window="setUrl($event.detail.url)"
                    x-on:property-image-cleared.window="clear()"
                    class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-4"
                >
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Activity Photo
                        </h2>
                    </div>

                    <div
                        x-on:dragover.prevent="dragging = true"
                        x-on:dragleave.prevent="dragging = false"
                        x-on:drop.prevent="
                            dragging = false;
                            const dt = new DataTransfer();
                            for (const f of $event.dataTransfer.files) dt.items.add(f);
                            $refs.input.files = dt.files;
                            $refs.input.dispatchEvent(new Event('change'));
                        "
                        :class="dragging
                            ? 'border-primary-600 bg-primary-50 dark:bg-primary-500/10'
                            : 'border-gray-300 dark:border-gray-600'"
                        class="relative aspect-video border-2 border-dashed rounded-xl overflow-hidden transition-colors"
                    >
                        <input
                            x-ref="input"
                            id="property-image-input"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            x-on:change="pick($event)"
                        >

                        <img
                            :src="previewUrl || ''"
                            :class="previewUrl ? 'block' : 'hidden'"
                            alt="Activity photo preview"
                            class="absolute inset-0 w-full h-full object-cover"
                            loading="lazy"
                            decoding="async"
                        >

                        <label
                            for="property-image-input"
                            :class="previewUrl ? 'hidden' : 'flex'"
                            class="absolute inset-0 flex-col items-center justify-center p-6 text-center cursor-pointer
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]"
                        >
                            <svg class="h-10 w-10 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Click or drag an image here
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 max-w-xs mx-auto">
                                PNG, JPG, or WebP · max 5 MB · auto-cropped + compressed
                            </p>
                        </label>

                        <div
                            wire:loading.flex
                            wire:target="image"
                            class="absolute inset-0 bg-black/45 backdrop-blur-[2px] items-center justify-center pointer-events-none"
                            aria-hidden="true"
                        >
                            <span class="inline-flex items-center gap-2 text-xs font-semibold text-white">
                                <svg class="animate-spin h-4 w-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Uploading…
                            </span>
                        </div>
                    </div>

                    @error('image') <span class="text-rose-500 dark:text-rose-400 text-xs block">{{ $message }}</span> @enderror

                    <div :class="previewUrl ? 'flex' : 'hidden'" class="items-center justify-end gap-2">
                        <label for="property-image-input"
                               class="inline-flex items-center justify-center gap-1.5 h-11 sm:h-9 px-3.5 rounded-lg
                                      border border-gray-300 dark:border-gray-600
                                      bg-white dark:bg-gray-800
                                      text-gray-700 dark:text-gray-200
                                      text-xs font-semibold cursor-pointer
                                      transition-all duration-200 active:scale-95
                                      hover:bg-gray-50 dark:hover:bg-gray-700
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                      focus-within:outline-none focus-within:ring-2 focus-within:ring-primary-500/50">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            <span>Replace</span>
                        </label>

                        <button type="button"
                                wire:click="removeImage"
                                class="inline-flex items-center justify-center gap-1.5 h-11 sm:h-9 px-3.5 rounded-lg
                                       border border-rose-300 dark:border-rose-500/40
                                       bg-white dark:bg-gray-800
                                       text-rose-700 dark:text-rose-300
                                       text-xs font-semibold
                                       transition-all duration-200 active:scale-95
                                       hover:bg-rose-50 dark:hover:bg-rose-500/10
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            <span>Remove</span>
                        </button>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Publishing
                        </h2>
                    </div>

                    <div>
                        <label for="field-status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Current Status
                        </label>
                        <select id="field-status" wire:model="status" class="select">
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="reserved">Reserved</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                        @error('status') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <label class="flex items-center gap-3 cursor-pointer select-none pt-1 min-h-[44px]
                                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                        <span class="relative inline-flex items-center shrink-0">
                            <input type="checkbox" wire:model="is_active" class="sr-only peer">
                            <span class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full
                                         peer peer-checked:bg-primary-600
                                         after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                         after:bg-white after:rounded-full after:h-5 after:w-5
                                         after:transition-all peer-checked:after:translate-x-full"></span>
                        </span>
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            Active
                            <span class="text-gray-400 dark:text-gray-500">— visible to customers</span>
                        </span>
                    </label>
                </div>

                <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Availability <span class="text-gray-400 dark:text-gray-500 font-medium normal-case tracking-normal">(optional)</span>
                        </h2>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400 -mt-2">
                        Block out dates when this activity isn't bookable.
                    </p>

                    <div>
                        <label for="field-unavail-from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            From
                        </label>
                        <input type="date" id="field-unavail-from" wire:model="unavailableFrom" class="input">
                        @error('unavailableFrom') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="field-unavail-to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            To
                        </label>
                        <input type="date" id="field-unavail-to" wire:model="unavailableTo" class="input">
                        @error('unavailableTo') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

            </div>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3 pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
            <a href="{{ route('tenant.properties.index') }}" wire:navigate
               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                      transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                <span>Cancel</span>
            </a>

            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                           transition-all duration-200 active:scale-95
                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                           disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="save">Create Activity</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Saving…
                </span>
            </button>
        </div>
    </form>

    @if($showNewTypeModal)
        <div class="fixed inset-0 z-[200] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
             x-on:keydown.escape.window="$wire.closeNewTypeModal()"
             x-on:click.self="$wire.closeNewTypeModal()">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <div class="p-2 bg-primary-50 dark:bg-primary-500/10 rounded-lg text-primary-600 dark:text-primary-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 01.586 1.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Add Activity Type</h3>
                    </div>
                    <button type="button"
                            wire:click="closeNewTypeModal"
                            aria-label="Close"
                            class="inline-flex items-center justify-center h-11 w-11 sm:h-9 sm:w-9 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700
                                   transition-all duration-200 active:scale-95
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                    Create a custom type for your business. It will be selectable only within your tenant.
                </p>

                <div class="space-y-4">
                    <div>
                        <label for="field-new-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Type Name <span class="text-rose-500">*</span>
                        </label>
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
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
                                Existing Global Types
                            </p>
                            <div class="flex flex-wrap gap-1">
                                @foreach($this->propertyTypes->whereNull('tenant_id') as $global)
                                    <span wire:key="global-type-{{ $global->id }}"
                                          class="inline-flex items-center px-2 py-0.5 rounded-md bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-[10px] font-medium text-gray-600 dark:text-gray-300">
                                        {{ $global->name }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button"
                            wire:click="closeNewTypeModal"
                            class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                   transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <span>Cancel</span>
                    </button>
                    <button type="button"
                            wire:click="createType"
                            wire:loading.attr="disabled"
                            wire:target="createType"
                            class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                   transition-all duration-200 active:scale-95
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="createType">Create Type</span>
                        <span wire:loading wire:target="createType" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Creating…
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <x-image-crop-modal />
</div>