{{-- resources/views/tenant/pages/event/⚡create-event.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Locked;
use App\Models\Event;
use App\Models\Tenant;
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

    // ── Event fields ──
    public string $name        = '';
    public string $description = '';
    public string $type        = 'fiesta';
    public string $start_date  = '';
    public string $end_date    = '';
    public bool   $is_active   = true;
    public $image;

    /** Inherited from the tenant — never editable here. */
    #[Locked] public string $barangay = '';

    /** Derived from the tenant's business profile — never editable here. */
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
        abort_unless(Auth::user()?->tenant_id, 403, 'No business is linked to your account.');

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

    // ─────────────────────────────────────────────────────────
    //  Validation
    // ─────────────────────────────────────────────────────────

    protected function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type'        => ['required', 'string', 'max:50'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active'   => ['boolean'],
            'image'       => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:10240'],
        ];
    }

    protected function messages(): array
    {
        return [
            'image.max'   => 'The event photo must not exceed 10MB.',
            'image.mimes' => 'The event photo must be a valid image (JPEG, PNG, JPG, GIF, or WebP).',
        ];
    }

    public function updated(string $field): void
    {
        if (in_array($field, ['name', 'description', 'type'], true)) {
            $this->$field = trim((string) $this->$field);
        }
    }

    // ─────────────────────────────────────────────────────────
    //  Coordinate helper
    // ─────────────────────────────────────────────────────────

    /**
     * Extract the primary lat/lng pair from a Tenant's coordinates array.
     *
     * Reads the model's `coordinates` attribute directly (cast to array),
     * so it works regardless of how the model was hydrated.
     *
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

    // ─────────────────────────────────────────────────────────
    //  Map — read-only, so only satellite toggle remains
    // ─────────────────────────────────────────────────────────

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
        abort_unless(Auth::user()?->tenant_id, 403, 'No business is linked to your account.');

        $this->validate();

        // Re-derive location from the tenant's profile at save time.
        // Never trust the client's #[Locked] props as authoritative.
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
                $imagePath = $this->image->store('event-images', 'public');
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

<div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto space-y-6">

    {{-- Flash messages --}}
    @if(session()->has('message'))
        <div class="flex items-start gap-3 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 border-l-4 border-l-emerald-500 p-4 rounded-md">
            <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <p class="text-sm text-emerald-700 dark:text-emerald-300 font-medium">{{ session('message') }}</p>
        </div>
    @endif
    @if(session()->has('error'))
        <div class="flex items-start gap-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 border-l-4 border-l-rose-500 p-4 rounded-md">
            <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-rose-700 dark:text-rose-300 font-medium">{{ session('error') }}</p>
        </div>
    @endif
    @if($errors->any())
        <div class="bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 border-l-4 border-l-rose-500 p-4 rounded-md">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="text-sm text-rose-700 dark:text-rose-300">
                    <p class="font-semibold mb-1">Please fix the following:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- Header — tenant eyebrow pattern --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                Events
            </p>
            <h1 class="mt-1 text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
                Add Event
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Create a new event for your business.
            </p>
        </div>
        <a href="{{ route('tenant.events.index') }}" wire:navigate
           class="btn-secondary active:scale-95 transition-transform
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                  inline-flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Events
        </a>
    </div>

    <form wire:submit="save" class="space-y-6">

        {{-- ═══════════════ EVENT DETAILS ═══════════════ --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6 space-y-6">
            <div class="flex items-center gap-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <h2 class="text-base font-bold text-gray-900 dark:text-white">Event Details</h2>
            </div>

            {{-- Event name --}}
            <div>
                <div class="flex justify-between items-baseline mb-1">
                    <label for="field-event-name" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                        Event Name <span class="text-red-500">*</span>
                    </label>
                    <span class="text-[11px] text-gray-400 dark:text-gray-500 tabular-nums">{{ Str::length($name) }}/255</span>
                </div>
                <input type="text" id="field-event-name" wire:model.live.debounce.300ms="name"
                       maxlength="255" class="input w-full" placeholder="e.g. Sinulog Festival">
                @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Barangay (locked) + Type --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="field-event-barangay" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5 uppercase tracking-wider">
                        Barangay
                    </label>
                    <input type="text" id="field-event-barangay"
                           value="{{ $barangay ?: '—' }}"
                           readonly disabled
                           class="input w-full bg-gray-100 dark:bg-gray-900 cursor-not-allowed text-gray-500 dark:text-gray-400">
                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                        Inherited from your business profile.
                    </p>
                </div>
                <div>
                    <label for="field-event-type" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5 uppercase tracking-wider">
                        Type <span class="text-red-500">*</span>
                    </label>
                    <select id="field-event-type" wire:model="type" class="input w-full">
                        <option value="fiesta">Fiesta</option>
                        <option value="sports">Sports</option>
                        <option value="environment">Environment</option>
                        <option value="entertainment">Entertainment</option>
                        <option value="adventure">Adventure</option>
                        <option value="other">Other</option>
                    </select>
                    @error('type') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Description --}}
            <div>
                <div class="flex justify-between items-baseline mb-1">
                    <label for="field-event-description" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                        Description
                    </label>
                    <span class="text-[11px] text-gray-400 dark:text-gray-500 tabular-nums">{{ Str::length($description) }}/1000</span>
                </div>
                <textarea id="field-event-description" wire:model.live.debounce.300ms="description"
                          rows="4" class="input w-full" maxlength="1000"
                          placeholder="Describe the event…"></textarea>
                @error('description') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Dates --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="field-event-start" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5 uppercase tracking-wider">
                        Start Date <span class="text-red-500">*</span>
                    </label>
                    <input type="datetime-local" id="field-event-start" wire:model="start_date" class="input w-full">
                    @error('start_date') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="field-event-end" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5 uppercase tracking-wider">
                        End Date <span class="text-gray-400 dark:text-gray-500 font-normal normal-case">(optional)</span>
                    </label>
                    <input type="datetime-local" id="field-event-end" wire:model="end_date" class="input w-full">
                    @error('end_date') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Image --}}
            <div>
                <label for="field-event-image" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5 uppercase tracking-wider">
                    Event Photo
                </label>
                <input type="file" id="field-event-image"
                       wire:model="image"
                       x-ref="imageInput"
                       accept="image/*"
                       class="w-full text-sm text-gray-700 dark:text-gray-300
                              file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                              file:bg-primary-50 dark:file:bg-primary-500/20 file:text-primary-700 dark:file:text-primary-300
                              hover:file:bg-primary-100 dark:hover:file:bg-primary-500/30
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 transition">
                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">Max 10MB. Supported: JPEG, PNG, JPG, GIF, WebP.</p>
                @error('image') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror

                <div wire:loading wire:target="image" class="mt-2 text-xs text-primary-600 dark:text-primary-400 flex items-center gap-1.5">
                    <svg class="animate-spin w-3.5 h-3.5 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Uploading…
                </div>

                @if($image)
                    <div class="mt-3 flex items-center gap-3">
                        <img src="{{ $image->temporaryUrl() }}"
                             class="h-24 w-24 object-cover rounded-lg border border-gray-200 dark:border-gray-700"
                             alt="New event preview">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">New photo (not saved yet)</p>
                            <button type="button"
                                    @click="$refs.imageInput.value = ''; $wire.set('image', null)"
                                    class="mt-1 text-xs font-semibold text-rose-500 hover:text-rose-700 active:scale-95 transition-transform
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 rounded">
                                Remove photo
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Active toggle --}}
            <div class="flex items-center gap-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                <input type="checkbox" id="field-event-active" wire:model="is_active"
                       class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500">
                <label for="field-event-active" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                    Active
                </label>
            </div>
        </div>

        {{-- ═══════════════ LOCATION (READ-ONLY) ═══════════════ --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6 space-y-5">
            <div class="flex items-center gap-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <h2 class="text-base font-bold text-gray-900 dark:text-white">Location Preview</h2>
            </div>

            @php $tenant = Auth::user()->tenant; @endphp

            @if($tenant)
                {{-- Info card --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/60 overflow-hidden">

                    {{-- Header row --}}
                    <div class="flex items-start gap-3 px-4 py-3.5 border-b border-gray-200 dark:border-gray-700">
                        <div class="p-2 rounded-lg bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Your Business</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white mt-0.5 truncate">{{ $tenant->name }}</p>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-500/10 border border-primary-200 dark:border-primary-500/30 px-2 py-0.5 rounded-full shrink-0">
                            Read-only
                        </span>
                    </div>

                    <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                        {{-- Barangay --}}
                        <div class="flex items-start gap-3 px-4 py-3">
                            <div class="p-1.5 rounded-md bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v11a2 2 0 002 2h14a2 2 0 002-2V7M3 7l9-4 9 4M3 7h18"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Barangay</dt>
                                <dd class="text-sm font-semibold text-gray-900 dark:text-white mt-0.5">
                                    {{ $barangay ?: 'Not set' }}
                                </dd>
                            </div>
                        </div>

                        {{-- Full address --}}
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

                        {{-- Coordinates --}}
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
                    </dl>
                </div>

                {{-- Map (read-only) --}}
                @if($latitude !== null && $longitude !== null)
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            The marker is fixed to your business location.
                        </p>
                        <button type="button" wire:click="toggleSatellite"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg
                                       bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700
                                       text-xs font-medium text-gray-700 dark:text-gray-300 shadow-sm
                                       hover:bg-gray-50 dark:hover:bg-gray-800 transition active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                            </svg>
                            {{ $satellite ? 'Street View' : 'Satellite' }}
                        </button>
                    </div>

                    <div class="rounded-2xl overflow-hidden border border-gray-200/80 dark:border-gray-700/80 relative"
                         style="height: 400px;">
                        <div wire:key="event-location-preview-{{ $satellite ? 'sat' : 'std' }}-{{ $mapVersion }}">
                            <x-map
                                id="event-location-preview"
                                :center="[(float) $mapView['lng'], (float) $mapView['lat']]"
                                :zoom="$mapView['zoom']"
                                height="400px"
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
                                        <div class="relative flex items-center justify-center transform-gpu will-change-transform transition-transform duration-200">
                                            <svg class="h-11 w-11 drop-shadow-lg" viewBox="0 0 24 24" fill="#ef4444" stroke="white" stroke-width="1.5" aria-hidden="true">
                                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                                                <circle cx="12" cy="9" r="2.5" fill="white"/>
                                            </svg>
                                        </div>
                                    </x-marker-content>
                                    <x-marker-popup>
                                        <div class="p-3 min-w-55">
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
                    {{-- Tenant has no coordinates --}}
                    <div class="rounded-xl border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/5 p-4 flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">No location set</p>
                            <p class="text-xs text-amber-700 dark:text-amber-300 mt-0.5 leading-relaxed">
                                Your business has no coordinates on file. The event will still be created without a map position.
                                Set your location in
                                <a href="{{ route('tenant.settings.index') }}" wire:navigate class="font-semibold underline hover:no-underline">
                                    Business Profile
                                </a>
                                to display it on the map.
                            </p>
                        </div>
                    </div>
                @endif
            @else
                <div class="rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 p-8 text-center">
                    <div class="mx-auto p-3 rounded-2xl bg-gray-100 dark:bg-gray-800 w-fit text-gray-400 dark:text-gray-500 mb-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">No business profile</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">
                        Contact the platform administrator.
                    </p>
                </div>
            @endif
        </div>

        {{-- ═══════════════ ACTIONS ═══════════════ --}}
        <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="btn-primary w-full sm:w-auto active:scale-95 transition-transform
                           inline-flex items-center justify-center gap-2
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                           disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="save">Save Event</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    Saving…
                </span>
            </button>
            <a href="{{ route('tenant.events.index') }}" wire:navigate
               class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform
                      inline-flex items-center justify-center gap-2
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                Cancel
            </a>
        </div>
    </form>
</div>