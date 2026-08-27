{{-- resources/views/tenant/pages/event/⚡edit-event.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Event;
use Illuminate\Support\Str;

new
#[Layout('tenant.layouts.app')]
#[Title('Edit Event')]
class extends Component
{
    public Event $event;
    public string $name = '';
    public string $barangay = '';
    public string $description = '';
    public string $type = 'fiesta';
    public string $start_date = '';
    public string $end_date = '';
    public bool $is_active = true;

    protected function rules()
    {
        return [
            'name'        => 'required|string|max:255',
            'barangay'    => 'required|string|max:255',
            'type'        => 'required|string',
            'start_date'  => 'required|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'is_active'   => 'boolean',
        ];
    }

    public function getBarangaysProperty()
    {
        return collect(config('barangays', []))->sort()->values();
    }

    public function mount(Event $event): void
    {
        $this->authorize('update', $event);

        $this->event = $event;
        $this->name = $event->name;
        $this->barangay = $event->barangay;
        $this->description = $event->description;
        $this->type = $event->type;
        $this->start_date = $event->start_date->format('Y-m-d\TH:i');
        $this->end_date = $event->end_date?->format('Y-m-d\TH:i');
        $this->is_active = (bool) $event->is_active;
    }

    public function updatedName($value)
    {
        $this->name = trim($value);
    }

    public function updatedDescription($value)
    {
        $this->description = trim($value);
    }

    public function update()
    {
        $this->authorize('update', $this->event);

        $this->validate();

        $this->event->update([
            'name'        => $this->name,
            'barangay'    => $this->barangay,
            'description' => $this->description,
            'type'        => $this->type,
            'start_date'  => $this->start_date,
            'end_date'    => $this->end_date ?: null,
            'is_active'   => $this->is_active,
        ]);

        session()->flash('message', 'Event updated successfully.');
        return $this->redirectRoute('tenant.events.index', navigate: true);
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
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update the details of your event.</p>
        </div>
        <a href="{{ route('tenant.events.index') }}" wire:navigate
           class="btn-secondary focus-visible:ring-2 focus-visible:ring-primary-500/50">
            ← Back to Events
        </a>
    </div>

    <form wire:submit="update" class="card p-6 space-y-5">

        {{-- Event Name --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Event Name *</label>
            <input type="text" wire:model="name" class="input" placeholder="e.g. Sinulog Festival">
            @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        {{-- Barangay and Type --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Barangay *</label>
                <input type="text" wire:model="barangay" list="barangays-list" class="input" placeholder="Type or select barangay">
                <datalist id="barangays-list">
                    @foreach($this->barangays as $b)
                        <option value="{{ $b }}">
                    @endforeach
                </datalist>
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

        {{-- Active Toggle --}}
        <div class="flex items-center gap-2">
            <input type="checkbox" wire:model="is_active"
                   class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500">
            <label class="text-sm text-gray-700 dark:text-gray-300">Active</label>
        </div>

        {{-- Actions --}}
        <div class="pt-4 border-t border-gray-200 dark:border-gray-700 flex flex-wrap gap-3">
            <button type="submit" wire:loading.attr="disabled"
                    class="btn-primary focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <span wire:loading.remove>Update Event</span>
                <span wire:loading class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                    Updating…
                </span>
            </button>
            <a href="{{ route('tenant.events.index') }}" wire:navigate
               class="btn-secondary focus-visible:ring-2 focus-visible:ring-primary-500/50">
                Cancel
            </a>
        </div>
    </form>
</div>