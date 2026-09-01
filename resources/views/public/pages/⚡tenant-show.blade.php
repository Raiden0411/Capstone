{{-- resources/views/public/pages/⚡tenant-show.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Support\Str;

new
#[Layout('layouts.app')]
#[Title('Business Profile')]
class extends Component
{
    public Tenant $tenant;

    public function mount($slug)
    {
        $this->tenant = Tenant::query()
            ->where('slug', $slug)
            ->select('id', 'name', 'slug', 'logo', 'address', 'contact_number', 'email', 'type_of_tenant_id')
            ->with([
                'typeOfTenant:id,type',
                'settings' => fn ($q) => $q->where('key', 'business_info')->select('tenant_id', 'value'),
            ])
            ->firstOrFail();
    }

    #[Computed]
    public function tenantDescription(): string
    {
        return $this->tenant->settings?->first()?->value['description'] ?? '';
    }
};
?>

<div class="max-w-5xl mx-auto px-4 py-10 sm:py-16">
    <div class="card overflow-hidden">
        {{-- Cover / logo area --}}
        <div class="h-48 bg-gradient-to-r from-blue-600 to-blue-400 flex items-center justify-center">
            @if($tenant->logo)
                <img src="{{ asset('storage/' . $tenant->logo) }}"
                     alt="{{ $tenant->name }}"
                     class="h-32 w-32 rounded-full object-cover border-4 border-white dark:border-gray-800 shadow-xl">
            @else
                <span class="h-32 w-32 rounded-full bg-white/20 flex items-center justify-center text-5xl font-bold text-white">
                    {{ strtoupper(substr($tenant->name, 0, 1)) }}
                </span>
            @endif
        </div>

        <div class="p-6 sm:p-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">{{ $tenant->name }}</h1>
            <p class="mt-2 text-sm font-medium text-primary-600 dark:text-primary-400">
                {{ $tenant->typeOfTenant->type ?? 'Business' }}
            </p>

            @if($this->tenantDescription)
                <p class="mt-4 text-gray-700 dark:text-gray-300 leading-relaxed">
                    {{ $this->tenantDescription }}
                </p>
            @endif

            <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                @if($tenant->address)
                    <div class="flex items-start gap-2.5 bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3">
                        <svg class="w-5 h-5 text-primary-600 dark:text-primary-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span class="text-gray-600 dark:text-gray-300">{{ $tenant->address }}</span>
                    </div>
                @endif
                @if($tenant->contact_number)
                    <div class="flex items-start gap-2.5 bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3">
                        <svg class="w-5 h-5 text-primary-600 dark:text-primary-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        <a href="tel:{{ $tenant->contact_number }}" class="text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                            {{ $tenant->contact_number }}
                        </a>
                    </div>
                @endif
                @if($tenant->email)
                    <div class="flex items-start gap-2.5 bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3">
                        <svg class="w-5 h-5 text-primary-600 dark:text-primary-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <a href="mailto:{{ $tenant->email }}" class="text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                            {{ $tenant->email }}
                        </a>
                    </div>
                @endif
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('business.offerings', $tenant->slug) }}" wire:navigate
                   class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-primary-600 text-white font-semibold hover:bg-primary-700 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 shadow-lg shadow-primary-500/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    View Offerings
                </a>
                <a href="{{ route('explore.map', ['q' => $tenant->name]) }}" wire:navigate
                   class="inline-flex items-center gap-2 px-6 py-3 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    See on Map
                </a>
            </div>
        </div>
    </div>
</div>