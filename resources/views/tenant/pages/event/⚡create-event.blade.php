{{-- resources/views/tenant/pages/event/⚡create-event.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Locked;
use App\Models\Event;
use App\Models\Tenant;
use App\Traits\ChecksTenantPermissions;
use App\Traits\HandlesImageUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

new
#[Layout('tenant.layouts.app')]
#[Title('Add Event')]
class extends Component
{
    use WithFileUploads;
    use ChecksTenantPermissions;
    use HandlesImageUploads;

    public string $name        = '';
    public string $description = '';
    public string $type        = 'fiesta';
    public string $start_date  = '';
    public string $end_date    = '';
    public bool   $is_active   = true;
    public $image;

    #[Locked] public string $barangay = '';

    #[Locked] public ?float $latitude  = null;
    #[Locked] public ?float $longitude = null;

    public bool $satellite = false;

    #[Locked] public int $mapVersion = 0;
    #[Locked] public array $mapView = [
        'lat'  => 10.900977766937142,
        'lng'  => 123.07055771888716,
        'zoom' => 13,
    ];

    public function mount(): void
    {
        $this->authorizeCreateEvent();

        $tenant = Auth::user()->tenant;

        abort_unless($tenant, 403, 'No business is linked to your account.');

        $this->barangay = $tenant->barangay ?? '';

        $primary = $this->extractPrimaryCoords($tenant);

        if ($primary) {
            $this->latitude  = $primary['lat'];
            $this->longitude = $primary['lng'];
            $this->mapView   = [
                'lat'  => $this->latitude,
                'lng'  => $this->longitude,
                'zoom' => 15,
            ];
        }
    }

    public function hydrate(): void
    {
        $this->authorizeCreateEvent();
    }

    protected function authorizeCreateEvent(): void
    {
        abort_unless(
            Auth::user()?->tenant_id,
            403,
            'No business is linked to your account.'
        );

        $this->requirePermission('manage events');
    }

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

    protected function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type'        => ['required', 'string', 'max:50'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active'   => ['boolean'],
            'image'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    protected function messages(): array
    {
        return [
            'image.max'   => 'The event photo must not exceed 5 MB.',
            'image.mimes' => 'The event photo must be a JPEG, PNG, or WebP image.',
        ];
    }

    public function updated(string $field): void
    {
        if (in_array($field, ['name', 'description', 'type'], true)) {
            $this->$field = trim((string) $this->$field);
        }
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    protected function extractPrimaryCoords(?Tenant $tenant): ?array
    {
        if (!$tenant) {
            return null;
        }

        $coords = $tenant->coordinates ?? null;

        if (!is_array($coords) || empty($coords[0])) {
            return null;
        }

        $primary = $coords[0];

        if (!is_array($primary) || !isset($primary['lat'], $primary['lng'])) {
            return null;
        }

        return [
            'lat' => (float) $primary['lat'],
            'lng' => (float) $primary['lng'],
        ];
    }

    public function toggleSatellite(): void
    {
        $this->satellite = !$this->satellite;
        $this->mapVersion++;
    }

    public function save()
    {
        $this->authorizeCreateEvent();

        $this->validate();

        $tenant = Tenant::query()
            ->select('id', 'barangay', 'coordinates')
            ->find(Auth::user()->tenant_id);

        if (!$tenant) {
            session()->flash('error', 'Your business profile could not be found. Please contact support.');
            return null;
        }

        $this->barangay = $tenant->barangay ?? $this->barangay;

        $primary     = $this->extractPrimaryCoords($tenant);
        $coordinates = $primary
            ? ['lat' => $primary['lat'], 'lng' => $primary['lng']]
            : null;

        $imagePath = null;

        try {
            if ($this->image) {
                $imagePath = $this->storeImage($this->image, 'event-images', 'public', 'event');

                if (! $imagePath) {
                    throw new \RuntimeException('Failed to store the event photo.');
                }
            }

            DB::transaction(function () use ($tenant, $coordinates, $imagePath): void {
                Event::create([
                    'tenant_id'   => $tenant->id,
                    'name'        => $this->name,
                    'barangay'    => $this->barangay,
                    'description' => $this->description ?: null,
                    'type'        => $this->type,
                    'start_date'  => $this->start_date,
                    'end_date'    => $this->end_date ?: null,
                    'is_active'   => $this->is_active,
                    'image_path'  => $imagePath,
                    'coordinates' => $coordinates,
                ]);
            });
        } catch (\Throwable $e) {
            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

            Log::error('Tenant event creation failed', [
                'tenant_id' => Auth::user()->tenant_id,
                'actor_id'  => Auth::id(),
                'error'     => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);

            session()->flash('error', 'Failed to create the event. Please try again.');
            return null;
        }

        session()->flash('message', 'Event created successfully.');
        return $this->redirectRoute('tenant.events.index', navigate: true);
    }
};
?>

@push('styles')
    @once
        <style>
            .tenant-events-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.06) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.05) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.04) 0%, transparent 60%);
            }
            .dark .tenant-events-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.08) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.07) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.06) 0%, transparent 60%);
            }
        </style>
    @endonce
@endpush

<div class="relative">
    <div class="tenant-events-ambient fixed inset-0 -z-10 pointer-events-none" aria-hidden="true"></div>

    <div class="p-4 sm:p-6 lg:p-8 max-w-6xl mx-auto space-y-6
                pb-[max(1rem,env(safe-area-inset-bottom))]">

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

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200/70 dark:border-gray-800/70">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Events</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Add Event
                </h1>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Create a new event for your business.
                </p>
            </div>
            <a href="{{ route('tenant.events.index') }}" wire:navigate
               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                      transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>Back to Events</span>
            </a>
        </div>

        <form wire:submit="save">
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

                <div class="lg:col-span-3 space-y-6">

                    <div class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                                rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                                shadow-sm p-5 sm:p-6 space-y-5">
                        <div class="flex items-center gap-3">
                            <span class="w-5 h-px bg-primary-600"></span>
                            <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Event Details
                            </h2>
                        </div>

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

                        <div>
                            <label for="field-event-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Type <span class="text-rose-500">*</span>
                            </label>
                            <select id="field-event-type" wire:model="type" class="input w-full">
                                <option value="fiesta">Fiesta</option>
                                <option value="sports">Sports</option>
                                <option value="environment">Environment</option>
                                <option value="entertainment">Entertainment</option>
                                <option value="adventure">Adventure</option>
                                <option value="other">Other</option>
                            </select>
                            @error('type') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <div class="flex justify-between items-baseline mb-1">
                                <label for="field-event-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Description
                                </label>
                                <span class="text-[11px] text-gray-400 dark:text-gray-500 tabular-nums">{{ Str::length($description) }}/1000</span>
                            </div>
                            <textarea id="field-event-description" wire:model.live.debounce.300ms="description"
                                      rows="5" class="input w-full" maxlength="1000"
                                      placeholder="Describe the event…"></textarea>
                            @error('description') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

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
                    </div>

                    <div class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                                rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                                shadow-sm p-5 sm:p-6 space-y-5">
                        <div class="flex items-center gap-3">
                            <span class="w-5 h-px bg-primary-600"></span>
                            <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Publishing
                            </h2>
                        </div>

                        <label class="flex items-center gap-3 cursor-pointer select-none min-h-[44px]
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

                </div>

                <div class="lg:col-span-2 space-y-6">

                    <div
                        x-data="avatarPreview()"
                        x-on:event-image-preview.window="setUrl($event.detail.url)"
                        x-on:event-image-cleared.window="clear()"
                        class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                               rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                               shadow-sm p-5 sm:p-6 space-y-4"
                    >
                        <div class="flex items-center gap-3">
                            <span class="w-5 h-px bg-primary-600"></span>
                            <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Event Photo
                            </h2>
                        </div>

                        <div
                            x-data="{
                                ...imageCropper({
                                    wireProperty: 'image',
                                    aspect: 16 / 9,
                                    title: 'Crop event photo',
                                    description: 'Wide 16:9 crop works best',
                                    previewEvent: 'event-image-preview',
                                }),
                                dragging: false,
                            }"
                            x-init="init()"
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
                                : 'border-gray-300 dark:border-gray-600 hover:border-primary-500/50'"
                            class="relative w-full aspect-video border-2 border-dashed rounded-xl overflow-hidden transition-colors"
                        >
                            <input
                                x-ref="input"
                                id="event-image-input"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                x-on:change="pick($event)"
                            >

                            <img
                                :src="previewUrl || ''"
                                :class="previewUrl ? 'block' : 'hidden'"
                                alt="Event photo preview"
                                class="absolute inset-0 w-full h-full object-cover"
                                loading="lazy"
                                decoding="async"
                            >

                            <label
                                for="event-image-input"
                                :class="previewUrl ? 'hidden' : 'flex'"
                                class="absolute inset-0 flex-col items-center justify-center p-4 text-center cursor-pointer
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]"
                            >
                                <svg class="h-8 w-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <p class="mt-2 text-xs font-medium text-gray-700 dark:text-gray-300 leading-tight">
                                    Click or drop a photo
                                </p>
                                <p class="mt-0.5 text-[10px] text-gray-400 dark:text-gray-500">
                                    16:9 · auto-compressed
                                </p>
                            </label>

                            <div
                                wire:loading.flex
                                wire:target="image"
                                class="absolute inset-0 bg-black/45 backdrop-blur-[2px] items-center justify-center pointer-events-none"
                                aria-hidden="true"
                            >
                                <svg class="animate-spin h-5 w-5 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </div>
                        </div>

                        <p class="text-[11px] text-gray-500 dark:text-gray-400">
                            PNG, JPG, or WebP · max 5 MB · compressed to ≤2 MB.
                        </p>

                        @error('image') <span class="text-rose-500 dark:text-rose-400 text-xs block">{{ $message }}</span> @enderror

                        <div :class="previewUrl ? 'flex' : 'hidden'" class="items-center gap-2">
                            <label for="event-image-input"
                                   class="flex-1 inline-flex items-center justify-center gap-1.5 h-11 sm:h-9 px-3.5 rounded-lg
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
                                    wire:click="$set('image', null)"
                                    x-on:click="$dispatch('event-image-cleared')"
                                    class="flex-1 inline-flex items-center justify-center gap-1.5 h-11 sm:h-9 px-3.5 rounded-lg
                                           border border-rose-300 dark:border-rose-500/40
                                           bg-white dark:bg-gray-800
                                           text-rose-700 dark:text-rose-300
                                           text-xs font-semibold
                                           transition-all duration-200 active:scale-95
                                           hover:bg-rose-50 dark:hover:bg-rose-500/10
                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                <span>Remove</span>
                            </button>
                        </div>
                    </div>

                    <div class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                                rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                                shadow-sm p-5 sm:p-6 space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="w-5 h-px bg-primary-600"></span>
                            <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Location
                            </h2>
                        </div>

                        @php $tenant = Auth::user()->tenant; @endphp

                        @if($tenant)
                            <div class="rounded-xl border border-gray-200/70 dark:border-gray-700/60 bg-white/60 dark:bg-gray-900/40 overflow-hidden">
                                <div class="flex items-start gap-3 px-4 py-3 border-b border-gray-200/70 dark:border-gray-700/60">
                                    <div class="p-1.5 rounded-md bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Your Business</p>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white mt-0.5 truncate">{{ $tenant->name }}</p>
                                    </div>
                                </div>

                                <dl class="divide-y divide-gray-200/70 dark:divide-gray-700/60">
                                    <div class="flex items-start gap-2.5 px-4 py-2.5">
                                        <div class="p-1 rounded-md bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v11a2 2 0 002 2h14a2 2 0 002-2V7M3 7l9-4 9 4M3 7h18"/>
                                            </svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Barangay</dt>
                                            <dd class="text-xs font-semibold text-gray-900 dark:text-white mt-0.5">{{ $barangay ?: 'Not set' }}</dd>
                                        </div>
                                    </div>

                                    <div class="flex items-start gap-2.5 px-4 py-2.5">
                                        <div class="p-1 rounded-md bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Address</dt>
                                            <dd class="text-xs text-gray-900 dark:text-white mt-0.5 leading-snug">{{ $tenant->address ?: 'No address on file' }}</dd>
                                        </div>
                                    </div>

                                    <div class="flex items-start gap-2.5 px-4 py-2.5">
                                        <div class="p-1 rounded-md bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                            </svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Coordinates</dt>
                                            <dd class="text-[11px] font-mono text-gray-900 dark:text-white mt-0.5 tabular-nums">
                                                {{ $latitude !== null && $longitude !== null
                                                    ? number_format($latitude, 5) . ', ' . number_format($longitude, 5)
                                                    : 'Not set' }}
                                            </dd>
                                        </div>
                                    </div>
                                </dl>
                            </div>

                            @if($latitude !== null && $longitude !== null)
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                        Fixed to your business location.
                                    </p>
                                    <button type="button"
                                            wire:click="toggleSatellite"
                                            class="inline-flex items-center justify-center gap-1.5 h-11 sm:h-8 px-3 rounded-lg
                                                   border border-gray-300 dark:border-gray-600
                                                   bg-white dark:bg-gray-800
                                                   text-gray-700 dark:text-gray-200
                                                   text-[11px] font-semibold
                                                   transition-all duration-200 active:scale-95
                                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                   hover:bg-gray-50 dark:hover:bg-gray-700
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                                        </svg>
                                        <span>{{ $satellite ? 'Street' : 'Satellite' }}</span>
                                    </button>
                                </div>

                                <div class="rounded-xl overflow-hidden border border-gray-200/70 dark:border-gray-700/60 relative"
                                     style="height: 240px;">
                                    <div wire:key="event-location-preview-{{ $satellite ? 'sat' : 'std' }}-{{ $mapVersion }}">
                                        <x-map
                                            id="event-location-preview"
                                            :center="[(float) $mapView['lng'], (float) $mapView['lat']]"
                                            :zoom="$mapView['zoom']"
                                            height="240px"
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

                                            <x-map-marker
                                                wire:key="event-preview-marker"
                                                :lat="$latitude"
                                                :lng="$longitude"
                                                color="#ef4444"
                                                id="event-preview-marker"
                                                :draggable="false"
                                            >
                                                <x-marker-content>
                                                    <div class="relative flex items-center justify-center transform-gpu will-change-transform">
                                                        <svg class="h-9 w-9 drop-shadow-lg" viewBox="0 0 24 24" fill="#ef4444" stroke="white" stroke-width="1.5" aria-hidden="true">
                                                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                                                            <circle cx="12" cy="9" r="2.5" fill="white"/>
                                                        </svg>
                                                    </div>
                                                </x-marker-content>
                                                <x-marker-popup>
                                                    <div class="p-3 min-w-[200px]">
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
                                        </x-map>
                                    </div>
                                </div>
                            @else
                                <div class="rounded-xl border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/5 p-3 flex items-start gap-2.5">
                                    <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <div>
                                        <p class="text-xs font-semibold text-amber-800 dark:text-amber-200">No location set</p>
                                        <p class="text-[11px] text-amber-700 dark:text-amber-300 mt-0.5 leading-relaxed">
                                            The event will be created without a map position. Set your location in
                                            <a href="{{ route('tenant.settings.index') }}" wire:navigate class="font-semibold underline hover:no-underline">Business Profile</a>.
                                        </p>
                                    </div>
                                </div>
                            @endif
                        @else
                            <div class="rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 p-6 text-center">
                                <div class="mx-auto p-2 rounded-xl bg-gray-100 dark:bg-gray-800 w-fit text-gray-400 dark:text-gray-500 mb-2">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                                <p class="text-xs font-semibold text-gray-900 dark:text-white">No business profile</p>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                                    Contact the platform administrator.
                                </p>
                            </div>
                        @endif
                    </div>

                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3 pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                <a href="{{ route('tenant.events.index') }}" wire:navigate
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

        <x-image-crop-modal />
    </div>
</div>