<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\PropertyType;

new 
#[Layout('tenant.layouts.app')]
#[Title('Create Custom Property Type')]
class extends Component {
    
    #[Validate('required|min:2|max:255|unique:property_types,name')]
    public string $name = '';

    public function save()
    {
        $this->validate();

        PropertyType::create([
            'name' => trim($this->name),
            // tenant_id is automatically set by BelongsToTenant trait
        ]);

        session()->flash('success', 'Custom property type created successfully.');
        return $this->redirectRoute('tenant.property-types.index', navigate: true);
    }
};
?>

<div class="p-6 max-w-3xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Create Custom Property Type</h1>
        <p class="text-slate-500">Add a new category specific to your business.</p>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <form wire:submit="save" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Type Name</label>
                <input type="text" wire:model="name" class="w-full rounded-lg border-slate-300 focus:ring-blue-500 focus:border-blue-500" placeholder="e.g. Executive Suite, Family Cottage">
                @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                <p class="text-xs text-slate-400 mt-1">This type will only be available to your business.</p>
            </div>

            <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-lg shadow-sm transition flex items-center gap-2 data-loading:opacity-75">
                    <span class="in-data-loading:hidden">Create Type</span>
                    <span class="not-in-data-loading:hidden">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Creating...
                    </span>
                </button>
                <a href="{{ route('tenant.property-types.index') }}" wire:navigate class="bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 font-medium py-2.5 px-6 rounded-lg transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>