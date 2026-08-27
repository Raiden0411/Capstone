{{-- resources/views/tenant/pages/event/⚡create-event.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;

new
#[Layout('tenant.layouts.app')]
#[Title('Add Event')]
class extends Component
{
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

    public function save()
    {
        $this->validate();

        Event::create([
            'tenant_id'   => Auth::user()->tenant_id,
            'name'        => $this->name,
            'barangay'    => $this->barangay,
            'description' => $this->description,
            'type'        => $this->type,
            'start_date'  => $this->start_date,
            'end_date'    => $this->end_date ?: null,
            'is_active'   => $this->is_active,
        ]);

        session()->flash('message', 'Event added successfully.');
        return $this->redirectRoute('tenant.events.index', navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-3xl mx-auto space-y-6">

    <div class="flex items-center justify-between pb-6 border-b border-gray-200 dark:border-gray-700">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Add Event</h1>
        <a href="{{ route('tenant.events.index') }}" wire:navigate
           class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-[#376df1] dark:hover:text-blue-400 transition-colors">
            &larr; Back to Events
        </a>
    </div>

    <form wire:submit="save" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm space-y-5">

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Event Name *</label>
            <input type="text" wire:model="name"
                   class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition">
            @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Barangay *</label>
                <input type="text" wire:model="barangay"
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition">
                @error('barangay') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type *</label>
                <select wire:model="type"
                        class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition appearance-none">
                    <option value="fiesta">Fiesta</option>
                    <option value="sports">Sports</option>
                    <option value="environment">Environment</option>
                    <option value="entertainment">Entertainment</option>
                    <option value="adventure">Adventure</option>
                </select>
                @error('type') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
            <textarea wire:model="description" rows="3"
                      class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition"></textarea>
            @error('description') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date *</label>
                <input type="datetime-local" wire:model="start_date"
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition">
                @error('start_date') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Date</label>
                <input type="datetime-local" wire:model="end_date"
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition">
                @error('end_date') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" wire:model="is_active"
                   class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-[#376df1] focus:ring-[#376df1]">
            <label class="text-sm text-gray-700 dark:text-gray-300">Active</label>
        </div>

        <div class="pt-4 border-t border-gray-200 dark:border-gray-700 flex gap-3">
            <button type="submit"
                    class="bg-[#376df1] hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-full shadow-lg shadow-blue-500/20 transition">
                Save Event
            </button>
            <a href="{{ route('tenant.events.index') }}" wire:navigate
               class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 px-6 py-2.5 rounded-full transition">
                Cancel
            </a>
        </div>
    </form>
</div>