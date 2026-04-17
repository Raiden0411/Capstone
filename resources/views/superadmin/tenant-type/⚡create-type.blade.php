<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\TypeOfTenant;

new 
#[Layout('superadmin.layouts.app')]
#[Title('Create Tenant Type')]
class extends Component {
    #[Validate('required|string|max:255|unique:type_of_tenants,type')]
    public string $type = '';
    
    #[Validate('nullable|string')]
    public string $description = '';

    public function save()
    {
        $this->validate();
        TypeOfTenant::create(['type' => $this->type, 'description' => $this->description]);
        session()->flash('success', 'Tenant type created.');
        return $this->redirectRoute('superadmin.tenant-types.index', navigate: true);
    }
};
?>

<div class="p-6 max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Add Tenant Type</h1>
    <form wire:submit="save" class="space-y-4 bg-white p-6 rounded-xl shadow">
        <div>
            <label class="block text-sm font-medium mb-1">Type Name</label>
            <input type="text" wire:model="type" class="w-full rounded-lg border-slate-300">
            @error('type') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Description (Optional)</label>
            <textarea wire:model="description" class="w-full rounded-lg border-slate-300"></textarea>
        </div>
        <div class="flex gap-3">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg">Save</button>
            <a href="{{ route('superadmin.tenant-types.index') }}" class="px-6 py-2 border rounded-lg">Cancel</a>
        </div>
    </form>
</div>