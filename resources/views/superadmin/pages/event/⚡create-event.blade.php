{{-- resources/views/superadmin/pages/event/⚡create-event.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use App\Models\Event;
use App\Models\Tenant;
use App\Traits\HandlesImageUploads;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

new
#[Layout('superadmin.layouts.app')]
#[Title('Add Event')]
class extends Component
{
    use WithFileUploads;
    use HandlesImageUploads;

    // ── Event fields ──
    public string $name        = '';
    public string $description = '';
    public string $type        = '';
    public string $start_date  = '';
    public string $end_date    = '';

    public ?int $tenant_id = null;

    public bool $is_active = true;
    public bool $featured  = false;

    public $image;

    public string $tenantSearch = '';

    // ── Derived from the selected tenant (never trusted from the client) ──
    #[Locked] public string $barangay  = '';
    #[Locked] public ?float $latitude  = null;
    #[Locked] public ?float $longitude = null;

    public bool $satellite = false;

    #[Locked] public int $mapVersion = 0;

    #[Locked] public array $mapView = [
        'lat'  => 10.900977766937142,
        'lng'  => 123.07055771888716,
        'zoom' => 13,
    ];

    // ─────────────────────────────────────────────────────────
    //  Lifecycle
    // ─────────────────────────────────────────────────────────

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');
    }

    /**
     * Four-layer pattern, Layer 3 — re-verify on every Livewire update
     * request. Route middleware only runs on the initial GET.
     */
    public function hydrate(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');
    }

    // ─────────────────────────────────────────────────────────
    //  Mapcn bridge methods (Rule 36 / bug 6.144)
    //
    //  The mapcn Alpine factory calls these on the parent component
    //  during map initialization. Missing any of them → the first
    //  POST /livewire-{hash}/update returns 500 with
    //  MethodNotFoundException. They delegate to $mapView, which is
    //  the SFC's source of truth for the current viewport.
    // ─────────────────────────────────────────────────────────

    public function getZoom(mixed $value = null): int
    {
        return (int) ($this->mapView['zoom'] ?? 13);
    }

    /** @return array{0: float, 1: float} */
    public function getCenter(mixed $value = null): array
    {
        return [
            (float) ($this->mapView['lng'] ?? 0.0),
            (float) ($this->mapView['lat'] ?? 0.0),
        ];
    }

    public function getBearing(mixed $value = null): float
    {
        return 0.0;
    }

    public function getPitch(mixed $value = null): float
    {
        return 0.0;
    }

    // ─────────────────────────────────────────────────────────
    //  Validation
    // ─────────────────────────────────────────────────────────

    protected function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type'        => ['required', 'string', 'min:2', 'max:255'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
            'tenant_id'   => ['required', 'integer', 'exists:tenants,id'],
            'is_active'   => ['boolean'],
            'featured'    => ['boolean'],
            // 5 MB ceiling on the raw upload, raster only. The cropped
            // blob that arrives here is already JPEG ≤4096px on the long
            // edge (cropModal's MAX_OUTPUT), and storeImage further
            // compresses it against the 'event' context (≤2 MB /
            // 2560×1440) before it lands on disk.
            'image'       => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    protected function messages(): array
    {
        return [
            'image.max'          => 'The event photo must not exceed 5MB.',
            'image.mimes'        => 'The event photo must be a valid image (JPEG, PNG, or WebP).',
            'tenant_id.required' => 'Please choose the tourist spot this event belongs to.',
            'tenant_id.exists'   => 'The selected tourist spot no longer exists.',
        ];
    }

    public function updated(string $field): void
    {
        if (in_array($field, ['name', 'description', 'type', 'tenantSearch'], true)) {
            $this->$field = trim((string) $this->$field);
        }
    }

    // ─────────────────────────────────────────────────────────
    //  Computed
    // ─────────────────────────────────────────────────────────

    #[Computed]
    public function allTenants()
    {
        return Tenant::query()
            ->select('id', 'name', 'barangay', 'address', 'coordinates', 'slug')
            ->where('is_active', true)
            ->orderBy('name')
            ->when($this->tenantSearch !== '', fn ($q) =>
                $q->where('name', 'like', '%' . $this->tenantSearch . '%')
            )
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function selectedTenant(): ?Tenant
    {
        if (!$this->tenant_id) {
            return null;
        }

        return Tenant::query()
            ->select('id', 'name', 'barangay', 'address', 'contact_number', 'email', 'coordinates', 'slug')
            ->find($this->tenant_id);
    }

    #[Computed]
    public function selectedTenantHasLocation(): bool
    {
        return $this->selectedTenant?->getPrimaryCoordinates() !== null;
    }

    // ─────────────────────────────────────────────────────────
    //  Tenant selection
    // ─────────────────────────────────────────────────────────

    public function updatedTenantId($value): void
    {
        if (!$value) {
            $this->reset(['barangay', 'latitude', 'longitude']);
            $this->mapVersion++;
            return;
        }

        $tenant = Tenant::query()
            ->select('id', 'barangay', 'coordinates')
            ->find($value);

        if (!$tenant) {
            $this->reset(['barangay', 'latitude', 'longitude']);
            $this->mapVersion++;
            return;
        }

        $this->barangay = $tenant->barangay ?? '';
        $primary        = $tenant->getPrimaryCoordinates();

        if ($primary) {
            $this->latitude  = (float) $primary['lat'];
            $this->longitude = (float) $primary['lng'];
            $this->mapView   = [
                'lat'  => $this->latitude,
                'lng'  => $this->longitude,
                'zoom' => 15,
            ];
        } else {
            $this->latitude  = null;
            $this->longitude = null;
            $this->mapView   = [
                'lat'  => 10.900977766937142,
                'lng'  => 123.07055771888716,
                'zoom' => 13,
            ];
        }

        $this->mapVersion++;
    }

    public function clearTenant(): void
    {
        $this->reset(['tenant_id', 'tenantSearch', 'barangay', 'latitude', 'longitude']);

        $this->mapView = [
            'lat'  => 10.900977766937142,
            'lng'  => 123.07055771888716,
            'zoom' => 13,
        ];

        $this->mapVersion++;
    }

    public function toggleSatellite(): void
    {
        $this->satellite = !$this->satellite;
        $this->mapVersion++;
    }

    // ─────────────────────────────────────────────────────────
    //  Save
    // ─────────────────────────────────────────────────────────

    public function save()
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');

        $this->validate();

        // Re-derive coordinates + barangay directly from the tenant to
        // guarantee data integrity. Never trust the client's Locked props.
        $tenant = Tenant::query()
            ->select('id', 'barangay', 'coordinates')
            ->find($this->tenant_id);

        if (!$tenant) {
            $this->addError('tenant_id', 'The selected tourist spot no longer exists.');
            return null;
        }

        $this->barangay = $tenant->barangay ?? $this->barangay;

        $coordinates = null;
        $primary     = $tenant->getPrimaryCoordinates();
        if ($primary) {
            $coordinates = [
                'lat' => (float) $primary['lat'],
                'lng' => (float) $primary['lng'],
            ];
        }

        $imagePath = null;

        try {
            if ($this->image) {
                // Route through HandlesImageUploads — compresses against
                // the 'event' context (≤2 MB / 2560×1440). The cropped
                // blob has already been JPEG-encoded by cropModal at
                // quality 0.95; this second pass enforces the ceiling.
                $imagePath = $this->storeImage($this->image, 'event-images', 'public', 'event');

                if (!$imagePath) {
                    throw new \RuntimeException('Failed to store the event image.');
                }
            }

            DB::transaction(function () use ($tenant, $coordinates, $imagePath): void {
                Event::create([
                    'name'        => $this->name,
                    'barangay'    => $this->barangay,
                    'description' => $this->description ?: null,
                    'type'        => $this->type,
                    'start_date'  => $this->start_date,
                    'end_date'    => $this->end_date ?: null,
                    'tenant_id'   => $tenant->id,
                    'is_active'   => $this->is_active,
                    'featured'    => $this->featured,
                    'image_path'  => $imagePath,
                    'coordinates' => $coordinates,
                ]);
            });
        } catch (\Throwable $e) {
            // Cleanup the orphaned image on failure.
            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

            Log::error('Event creation failed', [
                'tenant_id' => $tenant->id,
                'actor_id'  => Auth::id(),
                'error'     => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);

            session()->flash('error', 'Failed to create the event. Please try again.');
            return null;
        }

        session()->flash('message', 'Event created successfully.');
        return $this->redirectRoute('superadmin.events.index', navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto space-y-6">

    {{-- ═══ Flash messages ═══ --}}
    @if(session()->has('message'))
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
                    class="inline-flex items-center justify-center h-7 w-7 rounded-md text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/10
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    @if(session()->has('error'))
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
                    class="inline-flex items-center justify-center h-7 w-7 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    @if($errors->any())
        <div class="bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl">
            <div class="flex items-start gap-2.5">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div class="text-xs sm:text-sm text-rose-800 dark:text-rose-300">
                    <p class="font-semibold mb-1">Please fix the following:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $err)
                            <li wire:key="err-{{ $loop->index }}">{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══ Page header ═══ --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-800">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                Add Event
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                Create a new event tied to a tourist spot on the platform.
            </p>
        </div>
        <a href="{{ route('superadmin.events.index') }}" wire:navigate
           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                  transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span>Back to Events</span>
        </a>
    </div>

    {{-- ═══ Form ═══ --}}
    <form wire:submit="save" class="space-y-6">

        {{-- ─────────── Event Details ─────────── --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6 space-y-6">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Event Details
                </h2>
            </div>

            {{-- Event name with counter --}}
            <div>
                <div class="flex justify-between items-baseline mb-1">
                    <label for="field-event-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Event Name <span class="text-rose-500">*</span>
                    </label>
                    <span class="text-[11px] text-gray-400 dark:text-gray-500 tabular-nums">{{ Str::length($name) }}/255</span>
                </div>
                <input type="text" id="field-event-name" wire:model.live.debounce.300ms="name"
                       maxlength="255" class="input w-full" placeholder="e.g. Sinulog Festival">
                @error('name') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Type + Tourist spot --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="field-event-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Event Type <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="field-event-type" wire:model="type"
                           list="event-type-suggestions" class="input w-full"
                           placeholder="e.g. Fiesta, Sports, Environment">
                    <datalist id="event-type-suggestions">
                        <option value="Fiesta"></option>
                        <option value="Festival"></option>
                        <option value="Sports"></option>
                        <option value="Environment"></option>
                        <option value="Cultural"></option>
                        <option value="Religious"></option>
                        <option value="Exhibition"></option>
                    </datalist>
                    @error('type') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="field-event-tenant" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Tourist Spot <span class="text-rose-500">*</span>
                    </label>

                    <div class="relative mb-2">
                        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text"
                               wire:model.live.debounce.300ms="tenantSearch"
                               placeholder="Search tourist spots…"
                               aria-label="Search tourist spots"
                               enterkeyhint="search"
                               class="w-full h-11 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl pl-10 pr-4 text-sm text-gray-900 dark:text-white placeholder-gray-400
                                      focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                    </div>

                    <select id="field-event-tenant" wire:model.live="tenant_id" class="input w-full">
                        <option value="">— Select a tourist spot —</option>
                        @foreach($this->allTenants as $t)
                            <option value="{{ $t->id }}" wire:key="tenant-option-{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                    @error('tenant_id') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                        The event inherits this spot's barangay and map location.
                    </p>
                </div>
            </div>

            {{-- Description --}}
            <div>
                <div class="flex justify-between items-baseline mb-1">
                    <label for="field-event-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Description
                    </label>
                    <span class="text-[11px] text-gray-400 dark:text-gray-500 tabular-nums">{{ Str::length($description) }}/1000</span>
                </div>
                <textarea id="field-event-description" wire:model.live.debounce.300ms="description"
                          rows="4" class="input w-full" maxlength="1000"
                          placeholder="Describe the event…"></textarea>
                @error('description') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Dates --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="field-event-start" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Start Date <span class="text-rose-500">*</span>
                    </label>
                    <input type="datetime-local" id="field-event-start" wire:model="start_date" class="input w-full">
                    @error('start_date') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="field-event-end" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        End Date <span class="text-gray-400 dark:text-gray-500 font-normal">(optional)</span>
                    </label>
                    <input type="datetime-local" id="field-event-end" wire:model="end_date" class="input w-full">
                    @error('end_date') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            {{--
                ═══ Event Photo ═══
                Crop modal pipeline (Rule 87):
                  1. User picks a file via the label's hidden input.
                  2. imageCropper().pick() dispatches `crop:request` with
                     a token + the File.
                  3. The singleton <x-image-crop-modal /> (bottom of this
                     file) catches it, opens the crop UI with aspect 16/9.
                  4. On confirm, the modal dispatches `crop:result` with
                     the cropped blob. The picker $wire.upload()s it.
                  5. On upload success, the picker dispatches
                     `event-image-preview` with an object URL of the
                     cropped blob. avatarPreview() catches it and shows it.
                  6. On "Remove photo", we $wire.set('image', null) and
                     $dispatch('event-image-cleared') → avatarPreview()
                     revokes the URL and hides the preview.

                The 16/9 aspect matches the 'event' context ceiling
                (2560×1440) and the front-end event card display.
            --}}
            <div
                x-data="avatarPreview()"
                x-on:event-image-preview.window="setUrl($event.detail.url)"
                x-on:event-image-cleared.window="clear()"
            >
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Event Photo
                </label>

                <div
                    x-data="imageCropper({
                        wireProperty: 'image',
                        aspect: 16 / 9,
                        title: 'Crop event photo',
                        description: 'Wide 16:9 crop works best',
                        previewEvent: 'event-image-preview',
                    })"
                    x-init="init()"
                    class="flex flex-wrap items-center gap-2"
                >
                    <label for="event-image-upload"
                           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm cursor-pointer
                                  transition-all duration-200 active:scale-95
                                  focus-within:outline-none focus-within:ring-2 focus-within:ring-primary-500/50 focus-within:ring-offset-2 dark:focus-within:ring-offset-gray-900">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span>Upload photo</span>
                        <input type="file"
                               id="event-image-upload"
                               x-ref="input"
                               x-on:change="pick($event)"
                               accept="image/jpeg,image/png,image/webp"
                               class="sr-only">
                    </label>

                    <div wire:loading wire:target="image" class="inline-flex items-center gap-1.5 text-xs text-primary-600 dark:text-primary-400">
                        <svg class="animate-spin w-3.5 h-3.5 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Uploading…
                    </div>
                </div>

                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-2">
                    Max 5 MB. JPEG, PNG, or WebP. Auto-cropped to 16:9 and compressed on upload.
                </p>

                @error('image') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror

                {{-- Preview — visible only when a picker has produced an object URL --}}
                <div
                    :class="previewUrl ? 'flex' : 'hidden'"
                    class="mt-3 items-start gap-3"
                >
                    <img :src="previewUrl || ''"
                         alt="Event photo preview"
                         class="w-full max-w-md aspect-video object-cover rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm"
                         loading="lazy"
                         decoding="async">

                    <button type="button"
                            @click="$wire.set('image', null); $dispatch('event-image-cleared')"
                            class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg border border-rose-300 dark:border-rose-500/40 bg-white dark:bg-gray-800 text-rose-700 dark:text-rose-300 text-xs font-semibold shrink-0
                                   transition-all duration-200 active:scale-95 hover:bg-rose-50 dark:hover:bg-rose-500/10
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <span>Remove</span>
                    </button>
                </div>
            </div>

            {{-- Toggles --}}
            <div class="flex flex-wrap items-center gap-6 pt-4 border-t border-gray-100 dark:border-gray-700">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="is_active"
                           class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500 cursor-pointer">
                    <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="featured"
                           class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500 cursor-pointer">
                    <span class="text-sm text-gray-700 dark:text-gray-300">Featured</span>
                </label>
            </div>
        </div>

        {{-- ─────────── Location Preview ─────────── --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6 space-y-5">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Location Preview
                </h2>
            </div>

            @if($this->selectedTenant)
                @php $tenant = $this->selectedTenant; @endphp

                {{-- Info card --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/60 overflow-hidden">
                    <div class="flex items-start gap-3 px-4 py-3.5 border-b border-gray-200 dark:border-gray-700">
                        <div class="p-2 rounded-lg bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tourist Spot</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white mt-0.5 truncate">{{ $tenant->name }}</p>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-500/10 border border-primary-200 dark:border-primary-500/30 px-2 py-0.5 rounded-full shrink-0">
                            Read-only
                        </span>
                    </div>

                    <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                        <div class="flex items-start gap-3 px-4 py-3">
                            <div class="p-1.5 rounded-md bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v11a2 2 0 002 2h14a2 2 0 002-2V7M3 7l9-4 9 4M3 7h18"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Barangay</dt>
                                <dd class="text-sm font-semibold text-gray-900 dark:text-white mt-0.5">
                                    {{ $barangay ?: 'Not set for this tourist spot' }}
                                </dd>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 px-4 py-3">
                            <div class="p-1.5 rounded-md bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Full Address</dt>
                                <dd class="text-sm text-gray-900 dark:text-white mt-0.5 leading-snug">
                                    {{ $tenant->address ?: 'No address on file' }}
                                </dd>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 px-4 py-3">
                            <div class="p-1.5 rounded-md bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Coordinates</dt>
                                <dd class="text-xs font-mono text-gray-900 dark:text-white mt-0.5 tabular-nums">
                                    {{ $latitude !== null && $longitude !== null
                                        ? number_format($latitude, 5) . ', ' . number_format($longitude, 5)
                                        : 'Not set' }}
                                </dd>
                            </div>
                        </div>

                        @if($tenant->contact_number || $tenant->email)
                            <div class="flex items-start gap-3 px-4 py-3">
                                <div class="p-1.5 rounded-md bg-purple-50 dark:bg-purple-500/10 text-purple-600 dark:text-purple-400 shrink-0 mt-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Contact</dt>
                                    <dd class="text-xs text-gray-900 dark:text-white mt-0.5 space-y-0.5">
                                        @if($tenant->contact_number)
                                            <p>{{ $tenant->contact_number }}</p>
                                        @endif
                                        @if($tenant->email)
                                            <p class="truncate">{{ $tenant->email }}</p>
                                        @endif
                                    </dd>
                                </div>
                            </div>
                        @endif
                    </dl>
                </div>

                @if($this->selectedTenantHasLocation)
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            The marker is fixed to the tourist spot's location.
                        </p>
                        <button type="button" wire:click="toggleSatellite"
                                class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-xs font-semibold
                                       transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                            </svg>
                            <span>{{ $satellite ? 'Street View' : 'Satellite' }}</span>
                        </button>
                    </div>

                    <div class="rounded-2xl overflow-hidden border border-gray-200/80 dark:border-gray-700/80 relative" style="height: 360px;">
                        <div wire:key="event-location-preview-{{ $tenant->id }}-{{ $satellite ? 'sat' : 'std' }}-{{ $mapVersion }}">
                            <x-map
                                id="event-location-preview"
                                :center="[(float) $mapView['lng'], (float) $mapView['lat']]"
                                :zoom="$mapView['zoom']"
                                height="360px"
                                :provider="$satellite ? 'custom' : 'carto-voyager'"
                                :style="$satellite ? route('map.satellite.style') : null"
                                :light-style="$satellite ? route('map.satellite.style') : null"
                                :dark-style="$satellite ? route('map.satellite.style') : null"
                                theme="auto"
                                class="h-full w-full"
                                :interactive="false"
                            >
                                <x-map-controls
                                    :zoom="false"
                                    :compass="false"
                                    :locate="false"
                                    :fullscreen="true"
                                    :scale="false"
                                    position="top-right"
                                />

                                @if($latitude !== null && $longitude !== null)
                                    <x-map-marker
                                        wire:key="event-preview-marker-{{ $tenant->id }}"
                                        :lat="$latitude"
                                        :lng="$longitude"
                                        color="#ef4444"
                                        id="event-preview-marker"
                                        :draggable="false"
                                    >
                                        <x-marker-content>
                                            {{-- No transition-transform — see bug 6.143. --}}
                                            <div class="relative flex items-center justify-center transform-gpu will-change-transform">
                                                <svg class="h-11 w-11 drop-shadow-lg" viewBox="0 0 24 24" fill="#ef4444" stroke="white" stroke-width="1.5" aria-hidden="true">
                                                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                                                    <circle cx="12" cy="9" r="2.5" fill="white"/>
                                                </svg>
                                            </div>
                                        </x-marker-content>
                                        <x-marker-popup>
                                            <div class="p-3 min-w-[220px]">
                                                <strong class="text-gray-900 dark:text-white text-sm block">{{ $tenant->name }}</strong>
                                                @if($barangay)
                                                    <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                                        <span class="font-semibold">{{ $barangay }}</span>
                                                    </p>
                                                @endif
                                                @if($tenant->address)
                                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 leading-snug">{{ $tenant->address }}</p>
                                                @endif
                                                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-2 font-mono tabular-nums">
                                                    {{ number_format($latitude, 5) }}, {{ number_format($longitude, 5) }}
                                                </p>
                                            </div>
                                        </x-marker-popup>
                                    </x-map-marker>
                                @endif
                            </x-map>
                        </div>
                    </div>
                @else
                    <div class="rounded-xl border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/5 p-4 flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">No map location set</p>
                            <p class="text-xs text-amber-700 dark:text-amber-300 mt-0.5 leading-relaxed">
                                This tourist spot has no coordinates on file. The event will still be created without a map position.
                                Add a location on the
                                <a href="{{ route('superadmin.map-markers.index') }}" wire:navigate class="font-semibold underline hover:no-underline">Map Markers</a>
                                page to display it on the map.
                            </p>
                        </div>
                    </div>
                @endif

                <div class="flex justify-end pt-1">
                    <button type="button" wire:click="clearTenant"
                            class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-xs font-semibold
                                   transition-all duration-200 active:scale-95
                                   hover:bg-rose-50 dark:hover:bg-rose-500/10
                                   hover:text-rose-700 dark:hover:text-rose-300
                                   hover:border-rose-300 dark:hover:border-rose-500/40
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <span>Change tourist spot</span>
                    </button>
                </div>
            @else
                <div class="rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 p-8 text-center">
                    <div class="mx-auto p-3 rounded-2xl bg-gray-100 dark:bg-gray-800 w-fit text-gray-400 dark:text-gray-500 mb-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">No tourist spot selected</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">
                        Pick a tourist spot above. Its barangay, address, and map location will appear here automatically.
                    </p>
                </div>
            @endif
        </div>

        {{-- ─────────── Actions ─────────── --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3 pt-5 border-t border-gray-200 dark:border-gray-700">
            <a href="{{ route('superadmin.events.index') }}" wire:navigate
               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                      transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                <span>Cancel</span>
            </a>

            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                           disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="save">Save Event</span>
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

    {{-- Image crop modal — singleton for this page (Rule 87) --}}
    <x-image-crop-modal />
</div>