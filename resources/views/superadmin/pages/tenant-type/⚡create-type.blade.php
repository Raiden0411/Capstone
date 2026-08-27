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
           class="btn-secondary focus-visible:ring-2 focus-visible:ring-primary-500/50">
            ← Back to Business Types
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
        <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="btn-primary focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <span wire:loading.remove>Save Business Type</span>
                <span wire:loading class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Saving…
                </span>
            </button>
            <a href="{{ route('superadmin.tenant-types.index') }}" wire:navigate
               class="btn-secondary focus-visible:ring-2 focus-visible:ring-primary-500/50">
                Cancel
            </a>
        </div>
    </form>
</div>