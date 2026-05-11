<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\TypeOfTenant;
use Illuminate\Validation\Rule;

new 
#[Layout('superadmin.layouts.app')]
#[Title('Edit Tenant Type')]
class extends Component {

    public TypeOfTenant $type;

    #[Validate]
    public $typeName = '';
    
    #[Validate('nullable|string')]
    public $description = '';

    public function mount(TypeOfTenant $type)
    {
        $this->type = $type;
        $this->typeName = $type->type;
        $this->description = $type->description ?? '';
    }

    public function updated($property)
    {
        if (in_array($property, ['typeName', 'description'])) {
            $this->$property = trim($this->$property);
        }
    }

    protected function rules(): array
    {
        return [
            'typeName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('type_of_tenants', 'type')->ignore($this->type->id),
            ],
        ];
    }

    public function update()
    {
        $this->validate();

        $this->type->update([
            'type'        => $this->typeName,
            'description' => $this->description,
        ]);

        session()->flash('success', 'Tenant type updated successfully.');
        return $this->redirectRoute('superadmin.tenant-types.index', navigate: true);
    }
};
?>

<div class="max-w-2xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">

    {{-- Flash Message --}}
    @if (session()->has('success'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-400 font-medium">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex items-center justify-between">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Edit Tenant Type</h1>
        <a href="{{ route('superadmin.tenant-types.index') }}" wire:navigate class="text-sm font-medium text-gray-500 dark:text-white/50 hover:text-brand-600 dark:hover:text-brand-400 transition-colors flex items-center gap-1">
            &larr; Back to Tenant Types
        </a>
    </div>

    <form wire:submit="update" class="space-y-5 bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl p-5 sm:p-6 shadow-sm dark:shadow-none">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-white/70 mb-1">Type Name</label>
            <input type="text" wire:model="typeName" 
                   class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
            @error('typeName') <span class="text-red-500 dark:text-red-400 text-xs mt-1 inline-block">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-white/70 mb-1">Description <span class="text-gray-400 dark:text-white/40">(Optional)</span></label>
            <textarea wire:model="description" rows="3" 
                      class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"></textarea>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="bg-brand-600 hover:bg-brand-500 text-white font-medium py-2.5 px-6 rounded-xl shadow-lg shadow-brand-500/20 transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove>Update Type</span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Saving…
                </span>
            </button>
            <a href="{{ route('superadmin.tenant-types.index') }}" wire:navigate class="bg-white dark:bg-white/5 text-gray-700 dark:text-white/70 border border-gray-300 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-white/10 font-medium py-2.5 px-6 rounded-xl transition-colors">
                Cancel
            </a>
        </div>
    </form>
</div>