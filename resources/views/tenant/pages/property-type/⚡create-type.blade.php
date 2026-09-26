{{-- resources/views/tenant/pages/property-type/⚡create-type.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\PropertyType;
use App\Traits\ChecksTenantPermissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

new
#[Layout('tenant.layouts.app')]
#[Title('Add Property Type')]
class extends Component {
    use ChecksTenantPermissions;

    public string $name = '';

    public function mount(): void
    {
        abort_unless(Auth::check() && Auth::user()->tenant_id, 403);
    }

    public function save()
    {
        $this->requirePermission('manage properties');

        /** @var \App\Models\User $user */
        $user     = Auth::user();
        $tenantId = $user->tenant_id;

        $this->name = trim($this->name);

        $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('property_types', 'name')->where(
                    fn ($query) => $query->where(
                        fn ($q) => $q->whereNull('tenant_id')
                                     ->orWhere('tenant_id', $tenantId)
                    )
                ),
            ],
        ]);

        PropertyType::create([
            'tenant_id' => $tenantId,
            'name'      => $this->name,
        ]);

        session()->flash('success', 'Property type created successfully.');

        return $this->redirectRoute('tenant.property-types.index', navigate: true);
    }
};
?>

@push('styles')
    @once
        <style>
            .tenant-property-types-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.06) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.05) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.04) 0%, transparent 60%);
            }
            .dark .tenant-property-types-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.08) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.07) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.06) 0%, transparent 60%);
            }
        </style>
    @endonce
@endpush

<div class="relative">
    <div class="tenant-property-types-ambient fixed inset-0 -z-10 pointer-events-none" aria-hidden="true"></div>

    <div class="p-4 sm:p-6 lg:p-8 max-w-3xl mx-auto space-y-6
                pb-[max(1rem,env(safe-area-inset-bottom))]">

        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200/70 dark:border-gray-800/70">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Property Management</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Add Property Type
                </h1>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Create a custom type for your properties.
                </p>
            </div>
            <a href="{{ route('tenant.property-types.index') }}" wire:navigate
               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                      transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>Back to Types</span>
            </a>
        </div>

        @if(session()->has('success'))
            <div x-data="{ show: true }"
                 x-init="setTimeout(() => show = false, 4000)"
                 :class="show ? '' : 'hidden'"
                 class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
                <button type="button" @click="show = false"
                        class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 rounded-md text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/10
                               transition-all duration-200 active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                        aria-label="Dismiss">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        @if($errors->any())
            <div x-data="{ show: true }"
                 x-init="setTimeout(() => show = false, 6000)"
                 :class="show ? '' : 'hidden'"
                 class="flex items-start justify-between gap-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl">
                <div class="flex items-start gap-2.5 min-w-0">
                    <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div class="text-xs sm:text-sm text-rose-800 dark:text-rose-300 min-w-0">
                        <p class="font-semibold mb-1">Please fix the following:</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($errors->all() as $err)
                                <li wire:key="err-{{ $loop->index }}">{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <button type="button" @click="show = false"
                        class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 shrink-0 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                               transition-all duration-200 active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                        aria-label="Dismiss">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        <form wire:submit="save" class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                                         rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                                         shadow-sm p-5 sm:p-6 space-y-5">

            <div>
                <label for="type-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Type Name <span class="text-rose-500">*</span>
                </label>
                <input id="type-name"
                       type="text"
                       wire:model="name"
                       maxlength="255"
                       autocomplete="off"
                       class="input w-full"
                       placeholder="e.g. Cottage, Villa, Tent Site, Pavilion">
                @error('name') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">
                    This type will only appear for your business properties.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3 pt-5 mt-6 border-t border-gray-100/80 dark:border-white/[0.04]">
                <a href="{{ route('tenant.property-types.index') }}" wire:navigate
                   class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                          transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    Cancel
                </a>
                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                               transition-all duration-200 active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                               disabled:opacity-60 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Create Type
                    </span>
                    <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Saving…
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>