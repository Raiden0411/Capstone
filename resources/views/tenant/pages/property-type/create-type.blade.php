{{-- resources/views/tenant/pages/property-type/⚡create-type.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\PropertyType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

new 
#[Layout('tenant.layouts.app')]
#[Title('Add Property Type')]
class extends Component {
    
    #[Validate('required|string|max:255')]
    public $name = '';

    public function save()
    {
        $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('property_types', 'name')->where(function ($query) {
                    return $query->where(function ($q) {
                        $q->whereNull('tenant_id')
                          ->orWhere('tenant_id', Auth::user()->tenant_id);
                    });
                }),
            ],
        ]);

        PropertyType::create([
            'tenant_id' => Auth::user()->tenant_id,
            'name' => $this->name,
        ]);

        session()->flash('success', 'Property type created successfully.');
        return $this->redirectRoute('tenant.property-types.index', navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-2xl mx-auto space-y-6">

    {{-- Flash Message --}}
    @if (session()->has('success'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium flex items-center gap-3">
            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <div class="flex items-center justify-between pb-6 border-b border-gray-200 dark:border-gray-700">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Add Property Type</h1>
        <a href="{{ route('tenant.property-types.index') }}" wire:navigate
           class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-[#376df1] dark:hover:text-blue-400 transition-colors">
            &larr; Back to Types
        </a>
    </div>

    <form wire:submit="save" class="space-y-5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm">

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type Name *</label>
            <input type="text" wire:model="name"
                   class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition"
                   placeholder="e.g. Cottage, Villa, Tent Site, Pavilion">
            @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">This type will only appear for your business properties.</p>
        </div>

        {{-- Form Actions --}}
        <div class="flex items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="bg-[#376df1] hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-full shadow-lg shadow-blue-500/20 transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove>Create Type</span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Saving...
                </span>
            </button>
            <a href="{{ route('tenant.property-types.index') }}" wire:navigate
               class="px-6 py-3 rounded-full bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 font-medium transition">
                Cancel
            </a>
        </div>
    </form>
</div>