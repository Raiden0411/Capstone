<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Validation\Rule;
use App\Models\TypeOfTenant;

new 
#[Layout('superadmin.layouts.app')]
#[Title('Add Tenant Type')]
class extends Component {
    public string $type = '';
    public string $description = '';

    protected function rules()
    {
        return [
            'type' => [
                'required',
                'string',
                'max:255',
                Rule::unique('type_of_tenants', 'type'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function updated($property)
    {
        if (in_array($property, ['type', 'description'])) {
            $this->$property = trim($this->$property);
        }
    }

    public function save()
    {
        $this->validate();

        TypeOfTenant::create([
            'type'        => $this->type,
            'description' => $this->description,
        ]);

        session()->flash('message', 'Tenant type created successfully.');
        return $this->redirectRoute('superadmin.tenant-types.index', navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-2xl mx-auto space-y-6">

    {{-- Flash Message --}}
    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium">
            {{ session('message') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Add Tenant Type</h1>
        </div>
        <a href="{{ route('superadmin.tenant-types.index') }}" wire:navigate
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm font-semibold transition">
            ← Back to Tenant Types
        </a>
    </div>

    <form wire:submit="save" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm space-y-5">

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type Name *</label>
            <input type="text" wire:model="type"
                   class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition"
                   placeholder="e.g. Resort, Inn, Eco Park">
            @error('type') <span class="text-red-500 dark:text-red-400 text-xs mt-1 inline-block">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Description <span class="text-gray-400 dark:text-gray-500">(Optional)</span>
            </label>
            <textarea wire:model="description" rows="4"
                      class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition"
                      placeholder="Short description of this category"></textarea>
            @error('description') <span class="text-red-500 dark:text-red-400 text-xs mt-1 inline-block">{{ $message }}</span> @enderror
        </div>

        {{-- Form Actions --}}
        <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="bg-[#376df1] hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-full shadow-lg shadow-blue-500/20 transition hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100 flex items-center gap-2">
                <span wire:loading.remove>Save Type</span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Saving…
                </span>
            </button>
            <a href="{{ route('superadmin.tenant-types.index') }}" wire:navigate
               class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 font-medium py-2.5 px-5 rounded-full transition-colors hover:scale-[1.02]">
                Cancel
            </a>
        </div>
    </form>
</div>