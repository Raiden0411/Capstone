{{-- resources/views/superadmin/pages/tenant-type/⚡create-type.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Validation\Rule;
use App\Models\TypeOfTenant;

new 
#[Layout('superadmin.layouts.app')]
#[Title('Add Business Type')]
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

        session()->flash('message', 'Business type created successfully.');
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
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Add Business Type</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create a new category for businesses on the platform.</p>
        </div>
        <a href="{{ route('superadmin.tenant-types.index') }}" wire:navigate
           class="btn-secondary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Business Types
        </a>
    </div>

    <form wire:submit="save" class="card p-6 space-y-5">

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Business Type Name <span class="text-red-500">*</span></label>
            <input type="text" wire:model="type"
                   class="input"
                   placeholder="e.g. Resort, Inn, Eco Park">
            @error('type') <span class="text-red-500 dark:text-red-400 text-xs mt-1 inline-block">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Description <span class="text-gray-400 dark:text-gray-500">(Optional)</span>
            </label>
            <textarea wire:model="description" rows="4"
                      class="textarea"
                      placeholder="Briefly describe this business type"></textarea>
            @error('description') <span class="text-red-500 dark:text-red-400 text-xs mt-1 inline-block">{{ $message }}</span> @enderror
        </div>

        {{-- Form Actions --}}
        <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="btn-primary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <span wire:loading.remove>Save Business Type</span>
                <span wire:loading class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Saving…
                </span>
            </button>
            <a href="{{ route('superadmin.tenant-types.index') }}" wire:navigate
               class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                Cancel
            </a>
        </div>
    </form>
</div>