<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\PropertyType;
use Illuminate\Support\Facades\Auth;

new 
#[Layout('tenant.layouts.app')]
#[Title('Edit Custom Property Type')]
class extends Component {
    
    public PropertyType $type;
    
    #[Validate('required|min:2|max:255')]
    public string $name = '';

    public function mount(PropertyType $type)
    {
        // Tenants can only edit their own custom types
        if ($type->tenant_id !== Auth::user()->tenant_id) {
            abort(403, 'You can only edit your own custom property types.');
        }
        
        $this->type = $type;
        $this->name = $type->name;
    }

    public function rules()
    {
        return [
            'name' => 'required|min:2|max:255|unique:property_types,name,' . $this->type->id,
        ];
    }

    public function update()
    {
        $this->validate();

        $this->type->update([
            'name' => trim($this->name),
        ]);

        session()->flash('success', 'Property type updated successfully.');
        return $this->redirectRoute('tenant.property-types.index', navigate: true);
    }
};
?>

<div class="p-6 max-w-3xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Edit Custom Property Type</h1>
        <p class="text-slate-500">Update this business-specific category.</p>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <form wire:submit="update" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Type Name</label>
                <input type="text" wire:model="name" class="w-full rounded-lg border-slate-300 focus:ring-blue-500 focus:border-blue-500">
                @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-lg shadow-sm transition flex items-center gap-2 data-loading:opacity-75">
                    <span class="in-data-loading:hidden">Update Type</span>
                    <span class="not-in-data-loading:hidden">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Updating...
                    </span>
                </button>
                <a href="{{ route('tenant.property-types.index') }}" wire:navigate class="bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 font-medium py-2.5 px-6 rounded-lg transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>