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
        $this->type = $type; // already resolved by route model binding
        $this->typeName = $type->type;
        $this->description = $type->description ?? '';
    }

    /**
     * Trim inputs on update.
     */
    public function updated($property)
    {
        if (in_array($property, ['typeName', 'description'])) {
            $this->$property = trim($this->$property);
        }
    }

    /**
     * Define validation rules.
     */
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

<div class="p-4 sm:p-6 lg:p-10 max-w-2xl mx-auto text-gray-900 dark:text-white space-y-6">

    {{-- Flash Message --}}
    @if (session()->has('success'))
        <div class="p-4 bg-green-50 dark:bg-green-500/10 border-l-4 border-green-500 rounded-md shadow-sm">
            <p class="text-sm text-green-700 dark:text-green-400 font-medium">{{ session('success') }}</p>
        </div>
    @endif

    <div class="flex items-center justify-between">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Edit Tenant Type</h1>
        <a href="{{ route('superadmin.tenant-types.index') }}" wire:navigate class="text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-white font-medium transition-colors">
            &larr; Back to Tenant Types
        </a>
    </div>

    <form wire:submit="update" class="space-y-5 bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Type Name</label>
            <input type="text" wire:model="typeName" 
                   class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500">
            @error('typeName') <span class="text-red-500 dark:text-red-400 text-xs mt-1 inline-block">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Description <span class="text-gray-400 dark:text-slate-500">(Optional)</span></label>
            <textarea wire:model="description" rows="3" 
                      class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500"></textarea>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-xl shadow-sm transition-colors">
                Update Type
            </button>
            <a href="{{ route('superadmin.tenant-types.index') }}" wire:navigate class="py-2.5 px-6 rounded-xl border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors">
                Cancel
            </a>
        </div>
    </form>
</div>