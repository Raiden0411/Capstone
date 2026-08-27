{{-- resources/views/superadmin/pages/event/⚡edit-event.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Event;
use App\Models\Tenant;

new
#[Layout('superadmin.layouts.app')]
#[Title('Edit Event')]
class extends Component
{
    use WithFileUploads;

    public Event $event;
    public string $name = '';
    public string $barangay = '';
    public string $description = '';
    public string $type = '';
    public string $start_date = '';
    public string $end_date = '';
    public ?int $tenant_id = null;
    public bool $is_active = true;
    public bool $featured = false;
    public $image;
    public bool $remove_existing_image = false;

    protected function rules()
    {
        return [
            'name'        => 'required|string|max:255',
            'barangay'    => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type'        => 'required|string|max:255',
            'start_date'  => 'required|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'tenant_id'   => 'nullable|exists:tenants,id',
            'is_active'   => 'boolean',
            'featured'    => 'boolean',
            'image'       => 'nullable|image|max:2048',
        ];
    }

    public function mount(Event $event): void
    {
        $this->event = $event;
        $this->name = $event->name;
        $this->barangay = $event->barangay;
        $this->description = $event->description;
        $this->type = $event->type;
        $this->start_date = $event->start_date->format('Y-m-d\TH:i');
        $this->end_date = $event->end_date?->format('Y-m-d\TH:i');
        $this->tenant_id = $event->tenant_id;
        $this->is_active = $event->is_active;
        $this->featured = (bool) $event->featured;
    }

    public function update()
    {
        $this->validate();

        $imagePath = $this->event->image_path;
        if ($this->remove_existing_image) {
            $imagePath = null;
        }
        if ($this->image) {
            $imagePath = $this->image->store('event-images', 'public');
        }

        $this->event->update([
            'name'        => $this->name,
            'barangay'    => $this->barangay,
            'description' => $this->description,
            'type'        => $this->type,
            'start_date'  => $this->start_date,
            'end_date'    => $this->end_date ?: null,
            'tenant_id'   => $this->tenant_id,
            'is_active'   => $this->is_active,
            'featured'    => $this->featured,
            'image_path'  => $imagePath,
        ]);

        session()->flash('message', 'Event updated successfully.');
        return $this->redirectRoute('superadmin.events.index', navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-3xl mx-auto space-y-6">

    {{-- Flash Message --}}
    @if(session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium">
            {{ session('message') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Edit Event</h1>
        </div>
        <a href="{{ route('superadmin.events.index') }}" wire:navigate
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-primary-500/50">
            ← Back to Events
        </a>
    </div>

    {{-- Form --}}
    <form wire:submit="update" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm space-y-6">

        {{-- Event Name --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Event Name *</label>
            <input type="text" wire:model="name"
                   class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-gray-900 dark:text-white text-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
            @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        {{-- Barangay and Type --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Barangay *</label>
                <input type="text" wire:model="barangay" list="barangays"
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-gray-900 dark:text-white text-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                <datalist id="barangays">
                    <option value="Barangay I">
                    <option value="Barangay II">
                    <option value="Barangay III">
                    <option value="Barangay IV">
                    <option value="Barangay V">
                    <option value="Barangay VI">
                    <option value="Barangay VII">
                    <option value="Barangay VIII">
                    <option value="Barangay IX">
                    <option value="Barangay X">
                    <option value="Barangay XI">
                    <option value="Barangay XII">
                    <option value="Barangay XIII">
                </datalist>
                @error('barangay') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type *</label>
                <input type="text" wire:model="type" placeholder="e.g. Fiesta, Sports, Environment"
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-gray-900 dark:text-white text-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                @error('type') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Description --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
            <textarea wire:model="description" rows="4"
                      class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-gray-900 dark:text-white text-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"
                      placeholder="Describe the event..."></textarea>
            @error('description') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        {{-- Dates --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date *</label>
                <input type="datetime-local" wire:model="start_date"
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                @error('start_date') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Date</label>
                <input type="datetime-local" wire:model="end_date"
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                @error('end_date') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Event Photo --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Event Photo</label>

            @if($event->image_path && !$remove_existing_image)
                <div class="mb-3">
                    <img src="{{ asset('storage/' . $event->image_path) }}" class="h-32 w-32 object-cover rounded-lg border border-gray-200 dark:border-gray-700" alt="{{ $event->name }}">
                    <button type="button" wire:click="$set('remove_existing_image', true)" class="mt-2 text-xs text-red-500 dark:text-red-400 hover:text-red-700">Remove existing photo</button>
                </div>
            @endif

            <input type="file" wire:model="image" accept="image/*"
                   class="w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary-50 dark:file:bg-primary-500/20 file:text-primary-700 dark:file:text-primary-300 hover:file:bg-primary-100 dark:hover:file:bg-primary-500/30 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
            @error('image') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror

            @if ($image)
                <div class="mt-3">
                    <img src="{{ $image->temporaryUrl() }}" class="h-32 w-32 object-cover rounded-lg border border-gray-200 dark:border-gray-700" alt="New Event Preview">
                    <button type="button" wire:click="$set('image', null)" class="mt-2 text-xs text-red-500 dark:text-red-400 hover:text-red-700">Remove new photo</button>
                </div>
            @endif
        </div>

        {{-- Tenant Assignment --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Assign to Tenant (optional)</label>
            <select wire:model="tenant_id"
                    class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition appearance-none">
                <option value="">None (Platform‑wide)</option>
                @foreach(Tenant::orderBy('name')->get() as $t)
                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
            </select>
            @error('tenant_id') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        {{-- Active / Featured toggles --}}
        <div class="flex flex-wrap items-center gap-6">
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500">
                <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
            </label>
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox" wire:model="featured" class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500">
                <span class="text-sm text-gray-700 dark:text-gray-300">Featured</span>
            </label>
        </div>

        {{-- Actions --}}
        <div class="pt-4 border-t border-gray-200 dark:border-gray-700 flex flex-wrap gap-3">
            <button type="submit" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white font-medium py-2.5 px-6 rounded-full shadow-lg shadow-primary-500/20 transition hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <span wire:loading.remove>Update Event</span>
                <span wire:loading class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                    Updating…
                </span>
            </button>
            <a href="{{ route('superadmin.events.index') }}" wire:navigate
               class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 font-medium py-2.5 px-6 rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50">
                Cancel
            </a>
        </div>
    </form>
</div>