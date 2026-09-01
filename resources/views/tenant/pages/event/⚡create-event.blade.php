{{-- resources/views/tenant/pages/event/⚡create-event.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

new
#[Layout('tenant.layouts.app')]
#[Title('Add Event')]
class extends Component
{
    use WithFileUploads;

    public string $name = '';
    public string $barangay = '';
    public string $description = '';
    public string $type = 'fiesta';
    public string $start_date = '';
    public string $end_date = '';
    public bool $is_active = true;
    public $image;

    // Location
    public ?float $latitude = null;
    public ?float $longitude = null;
    public bool $satellite = false;
    public int $mapVersion = 0;
    public array $mapView = [
        'lat' => 10.900977766937142,
        'lng' => 123.07055771888716,
        'zoom' => 13,
    ];

    public function mount()
    {
        $tenant = Auth::user()->tenant;
        if ($tenant) {
            // Lock barangay to the tenant's business barangay
            $this->barangay = $tenant->barangay ?? '';

            // Use primary coordinates if available, otherwise fallback
            if ($primary = $tenant->getPrimaryCoordinates()) {
                $this->latitude = $primary['lat'] ?? null;
                $this->longitude = $primary['lng'] ?? null;
            } else {
                $this->latitude = null;
                $this->longitude = null;
            }

            $this->mapView = [
                'lat' => $this->latitude ?? $this->mapView['lat'],
                'lng' => $this->longitude ?? $this->mapView['lng'],
                'zoom' => 15,
            ];
        }
    }

    protected function rules()
    {
        return [
            'name'        => 'required|string|max:255',
            'barangay'    => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type'        => 'required|string',
            'start_date'  => 'required|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'is_active'   => 'boolean',
            'image'       => 'nullable|image|max:2048',
            'latitude'    => 'nullable|numeric|min:-90|max:90',
            'longitude'   => 'nullable|numeric|min:-180|max:180',
        ];
    }

    public function updatedName($value)
    {
        $this->name = trim($value);
    }

    public function updatedDescription($value)
    {
        $this->description = trim($value);
    }

    #[On('map:click')]
    public function onMapClick($lat, $lng): void
    {
        $this->latitude = round((float) $lat, 6);
        $this->longitude = round((float) $lng, 6);
        $this->mapView = [
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'zoom' => 16,
        ];
        $this->mapVersion++;
    }

    #[On('map:marker-drag-end')]
    public function onMarkerDragEnd($id, $lat, $lng): void
    {
        if ($id === 'event-location-marker') {
            $this->latitude = round((float) $lat, 6);
            $this->longitude = round((float) $lng, 6);
            $this->mapView = [
                'lat' => $this->latitude,
                'lng' => $this->longitude,
                'zoom' => $this->mapView['zoom'],
            ];
            $this->mapVersion++;
        }
    }

    #[On('map:center-changed')]
    public function onMapCenterChanged($lat, $lng): void
    {
        $this->mapView['lat'] = round((float) $lat, 6);
        $this->mapView['lng'] = round((float) $lng, 6);
    }

    #[On('map:zoom-changed')]
    public function onMapZoomChanged($zoom): void
    {
        $this->mapView['zoom'] = (int) $zoom;
    }

    public function toggleSatellite(): void
    {
        $this->satellite = ! $this->satellite;
        $this->mapVersion++;
    }

    public function useMyLocation(): void
    {
        $this->dispatch('request-geolocation');
    }

    #[On('geolocation-result')]
    public function onGeolocationResult($lat, $lng): void
    {
        $this->latitude = round((float) $lat, 6);
        $this->longitude = round((float) $lng, 6);
        $this->mapView = [
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'zoom' => 16,
        ];
        $this->mapVersion++;
        $this->dispatch('toast', message: 'Location updated from your device.', type: 'success');
    }

    public function clearLocation(): void
    {
        $this->latitude = null;
        $this->longitude = null;
        $this->mapVersion++;
        $this->dispatch('toast', message: 'Location removed.', type: 'info');
    }

    public function save()
    {
        $this->validate();

        $imagePath = $this->image ? $this->image->store('event-images', 'public') : null;

        $coordinates = null;
        if ($this->latitude !== null && $this->longitude !== null) {
            $coordinates = ['lat' => $this->latitude, 'lng' => $this->longitude];
        }

        Event::create([
            'tenant_id'   => Auth::user()->tenant_id,
            'name'        => $this->name,
            'barangay'    => $this->barangay, // locked to tenant barangay
            'description' => $this->description,
            'type'        => $this->type,
            'start_date'  => $this->start_date,
            'end_date'    => $this->end_date ?: null,
            'is_active'   => $this->is_active,
            'image_path'  => $imagePath,
            'coordinates' => $coordinates,
        ]);

        session()->flash('message', 'Event added successfully.');
        return $this->redirectRoute('tenant.events.index', navigate: true);
    }
};
?>

<div x-data="{ previewUrl: null }" class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto space-y-6">

    {{-- Toast notifications --}}
    <div
        x-data="{ toasts: [] }"
        x-on:toast.window="
            const id = Date.now() + Math.random();
            toasts.push({ id, message: $event.detail.message, type: $event.detail.type || 'info' });
            setTimeout(() => { toasts = toasts.filter(t => t.id !== id) }, 4000);
        "
        class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 w-full max-w-sm pointer-events-none"
    >
        <template x-for="toast in toasts" :key="toast.id">
            <div
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="pointer-events-auto rounded-xl px-4 py-3 shadow-lg text-sm font-medium flex items-center gap-2 border"
                :class="{
                    'bg-green-50 border-green-200 text-green-800 dark:bg-green-500/10 dark:border-green-500/30 dark:text-green-300': toast.type === 'success',
                    'bg-red-50 border-red-200 text-red-800 dark:bg-red-500/10 dark:border-red-500/30 dark:text-red-300': toast.type === 'error',
                    'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-500/10 dark:border-blue-500/30 dark:text-blue-300': toast.type === 'info',
                }"
            >
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>

    {{-- Flash Message --}}
    @if(session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('message') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Add Event</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create a new event for your business.</p>
        </div>
        <a href="{{ route('tenant.events.index') }}" wire:navigate
           class="btn-secondary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Events
        </a>
    </div>

    <form wire:submit="save" class="space-y-6">

        {{-- Event Details --}}
        <div class="card p-6 space-y-6">
            <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white mb-2">Event Details</h2>

            {{-- Event Name --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Event Name *</label>
                <input type="text" wire:model="name" class="input" placeholder="e.g. Sinulog Festival">
                @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Barangay (locked) and Type --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Barangay *</label>
                    <input type="text" value="{{ $this->barangay }}" readonly disabled
                           class="input bg-gray-100 dark:bg-gray-700 cursor-not-allowed text-gray-500 dark:text-gray-400">
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Inherited from your business profile</p>
                    @error('barangay') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type *</label>
                    <select wire:model="type" class="select">
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

            {{-- Description with counter --}}
            <div>
                <div class="flex justify-between items-baseline mb-1">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                    <span class="text-xs text-gray-400 dark:text-gray-500">{{ Str::length($description) }}/1000</span>
                </div>
                <textarea wire:model.live="description" rows="4" class="textarea"
                          placeholder="Describe the event..."></textarea>
                @error('description') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Dates --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date *</label>
                    <input type="datetime-local" wire:model="start_date" class="input">
                    @error('start_date') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Date</label>
                    <input type="datetime-local" wire:model="end_date" class="input">
                    @error('end_date') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Event Photo --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Event Photo</label>
                <input type="file" wire:model="image" accept="image/*"
                       @change="previewUrl = URL.createObjectURL($event.target.files[0])"
                       class="w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary-50 dark:file:bg-primary-500/20 file:text-primary-700 dark:file:text-primary-300 hover:file:bg-primary-100 dark:hover:file:bg-primary-500/30 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                @error('image') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror

                <div x-show="previewUrl" x-cloak class="mt-3">
                    <img :src="previewUrl" class="h-32 w-32 object-cover rounded-lg border border-gray-200 dark:border-gray-700" alt="Event Preview">
                    <button type="button" @click="previewUrl = null; $wire.set('image', null)" class="mt-2 text-xs text-red-500 dark:text-red-400 hover:text-red-700 active:scale-95 transition-transform">Remove photo</button>
                </div>
            </div>

            {{-- Active Toggle --}}
            <div class="flex items-center gap-2">
                <input type="checkbox" wire:model="is_active"
                       class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500">
                <label class="text-sm text-gray-700 dark:text-gray-300">Active</label>
            </div>
        </div>

        {{-- Location Section --}}
        <div class="card p-6 space-y-6">
            <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white mb-2">Event Location</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Click on the map to set the event location, or use the coordinates below.</p>

            <div class="flex flex-wrap gap-2 mb-4">
                <button type="button"
                        wire:click="useMyLocation"
                        class="btn-secondary text-xs active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Use my location
                </button>
                <button type="button"
                        wire:click="toggleSatellite"
                        class="btn-secondary text-xs active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/></svg>
                    {{ $satellite ? 'Street View' : 'Satellite' }}
                </button>
                @if($latitude !== null && $longitude !== null)
                    <button type="button"
                            wire:click="clearLocation"
                            class="btn-secondary text-xs text-red-600 active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-red-500/50 inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Clear Location
                    </button>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Latitude</label>
                    <input type="number" step="any" min="-90" max="90" wire:model.live.debounce.500ms="latitude" class="input font-mono" placeholder="10.900977">
                    @error('latitude') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Longitude</label>
                    <input type="number" step="any" min="-180" max="180" wire:model.live.debounce.500ms="longitude" class="input font-mono" placeholder="123.070557">
                    @error('longitude') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="card overflow-hidden relative" style="height: 400px;"
                 x-data="{ showOverlay: true }"
                 x-init="setTimeout(() => showOverlay = false, 800)">
                <div x-show="showOverlay"
                     x-transition:leave="transition-opacity duration-500"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="absolute inset-0 z-10 flex items-center justify-center bg-gray-50 dark:bg-gray-800">
                    <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Updating map…
                    </div>
                </div>

                <div wire:key="event-create-map-{{ $mapVersion }}">
                    <x-map
                        id="event-create-map"
                        :center="[(float)$mapView['lng'], (float)$mapView['lat']]"
                        :zoom="$mapView['zoom']"
                        height="400px"
                        :provider="$satellite ? 'custom' : 'carto-voyager'"
                        :style="$satellite ? route('map.satellite.style') : null"
                        :light-style="$satellite ? route('map.satellite.style') : null"
                        :dark-style="$satellite ? route('map.satellite.style') : null"
                        theme="auto"
                        class="h-full w-full"
                        :events="['click', 'marker-drag-end']"
                    >
                        <x-map-controls
                            :zoom="true"
                            :compass="true"
                            :locate="true"
                            :fullscreen="true"
                            :scale="true"
                            position="top-right"
                        />

                        @if($latitude !== null && $longitude !== null)
                            <x-map-marker
                                wire:key="event-location-marker-{{ $latitude }}-{{ $longitude }}"
                                :lat="$latitude"
                                :lng="$longitude"
                                color="#ef4444"
                                id="event-location-marker"
                                draggable
                            >
                                <x-marker-content>
                                    <div class="relative flex items-center justify-center">
                                        <svg class="h-10 w-10 drop-shadow-lg" viewBox="0 0 24 24" fill="#ef4444" stroke="white" stroke-width="1.5">
                                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                                            <circle cx="12" cy="9" r="2.5" fill="white"/>
                                        </svg>
                                    </div>
                                </x-marker-content>
                                <x-marker-popup>
                                    <div class="p-2">
                                        <strong class="text-gray-900 dark:text-white">Event Location</strong>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $latitude }}, {{ $longitude }}</p>
                                    </div>
                                </x-marker-popup>
                            </x-map-marker>
                        @endif
                    </x-map>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="pt-4 border-t border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row gap-3">
            <button type="submit" wire:loading.attr="disabled"
                    class="btn-primary w-full sm:w-auto active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
                <span wire:loading.remove>Save Event</span>
                <span wire:loading class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                    Saving…
                </span>
            </button>
            <a href="{{ route('tenant.events.index') }}" wire:navigate
               class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
                Cancel
            </a>
        </div>
    </form>

    @script
    <script>
        function notify(message, type = 'info') {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
        }

        window.addEventListener('request-geolocation', () => {
            if (!navigator.geolocation) {
                notify('Geolocation is not supported by your browser.', 'error');
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    Livewire.dispatch('geolocation-result', {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                    });
                },
                () => {
                    notify('Unable to retrieve your location. Check browser permissions.', 'error');
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
            );
        });
    </script>
    @endscript
</div>