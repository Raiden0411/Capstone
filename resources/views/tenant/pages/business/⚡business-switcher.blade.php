{{-- resources/views/tenant/pages/business/⚡business-switcher.blade.php --}}
<?php

use App\Models\BusinessMembership;
use App\Models\Tenant;
use App\Services\BusinessSwitcherService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('tenant.layouts.app')]
#[Title('Your Businesses')]
class extends Component
{
    /** @var array<int, array<string, mixed>> */
    public array $businesses = [];

    public function mount(): void
    {
        $user = Auth::user();
        abort_if(! $user, 403);

        $this->businesses = $this->loadBusinesses($user);
    }

    /** @return array<int, array<string, mixed>> */
    private function loadBusinesses($user): array
    {
        return $user->businesses()
            ->with('typeOfTenant:id,type')
            ->wherePivotIn('role', [
                BusinessMembership::ROLE_OWNER,
                BusinessMembership::ROLE_ADMIN,
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Tenant $t): array => [
                'id'          => $t->id,
                'name'        => $t->name,
                'slug'        => $t->slug,
                'logo'        => $t->logo,
                'type'        => $t->typeOfTenant?->type ?? 'Uncategorized',
                'role'        => (string) $t->pivot->role,
                'is_current'  => (int) $user->tenant_id === (int) $t->id,
                'joined_at'   => $t->pivot->joined_at,
            ])
            ->all();
    }

    public function switchTo(int $tenantId): void
    {
        $user = Auth::user();
        abort_if(! $user, 403);

        if ((int) $user->tenant_id === $tenantId) {
            return;
        }

        /** @var Tenant|null $target */
        $target = Tenant::find($tenantId);

        if (! $target) {
            $this->dispatch('toast', message: 'That business no longer exists.', type: 'error');
            return;
        }

        try {
            app(BusinessSwitcherService::class)->switchTo($user, $target);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
            return;
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', message: 'Could not switch businesses. Please try again.', type: 'error');
            return;
        }

        // Full redirect — the SetPermissionsTeamId middleware only
        // re-fires on a fresh request. See BusinessSwitcherService.
        $this->redirectRoute('tenant.dashboard');
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Toast host --}}
    <div
        x-data="{ toasts: [] }"
        x-on:toast.window="
            const id = Date.now() + Math.random();
            toasts.push({ id, message: $event.detail.message, type: $event.detail.type || 'info' });
            setTimeout(() => { toasts = toasts.filter(t => t.id !== id) }, 4000);
        "
        class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 w-full max-w-sm pointer-events-none"
    >
        <template x-for="toast in toasts" :key="toast.id">
            <div
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="pointer-events-auto rounded-xl px-4 py-3 shadow-lg text-sm font-medium flex items-center gap-2 border"
                :class="{
                    'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-300': toast.type === 'success',
                    'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-500/10 dark:border-rose-500/30 dark:text-rose-300': toast.type === 'error',
                    'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-500/10 dark:border-blue-500/30 dark:text-blue-300': toast.type === 'info',
                }"
            >
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>

    {{-- Session flash --}}
    @if(session()->has('message'))
        <div class="rounded-xl border border-emerald-200 dark:border-emerald-500/30 bg-emerald-50 dark:bg-emerald-500/10 p-4 text-sm font-medium text-emerald-700 dark:text-emerald-300">
            {{ session('message') }}
        </div>
    @endif

    @if(session()->has('error'))
        <div class="rounded-xl border border-rose-200 dark:border-rose-500/30 bg-rose-50 dark:bg-rose-500/10 p-4 text-sm font-medium text-rose-700 dark:text-rose-300">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Account</span>
            </div>
            <h1 class="font-display text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Your Businesses</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Switch between the businesses you own or administer. Only one is active at a time.
            </p>
        </div>
    </div>

    {{-- Business cards --}}
    @if(count($businesses) === 0)
        <div class="card p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            <p class="text-lg text-gray-500 dark:text-gray-400 mb-1">No businesses yet</p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mb-4">Apply for a business to get started.</p>
            <a href="{{ route('register_business') }}" wire:navigate
               class="btn-primary active:scale-95 transition-transform inline-flex items-center gap-1.5">
                Register a business
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($businesses as $b)
                <div wire:key="biz-{{ $b['id'] }}"
                     class="card p-5 flex flex-col gap-4 transition-shadow duration-300 ease-out
                            {{ $b['is_current']
                                ? 'ring-2 ring-primary-500 shadow-lg'
                                : 'hover:shadow-md' }}">

                    <div class="flex items-start gap-3">
                        <div class="w-12 h-12 rounded-xl bg-primary-50 dark:bg-primary-500/10 border border-primary-200 dark:border-primary-500/20 flex items-center justify-center shrink-0 overflow-hidden">
                            @if($b['logo'])
                                <img src="/storage/{{ ltrim($b['logo'], '/') }}"
                                     alt="{{ $b['name'] }}"
                                     loading="lazy"
                                     decoding="async"
                                     class="w-full h-full object-cover">
                            @else
                                <span class="text-lg font-medium text-primary-700 dark:text-primary-300">
                                    {{ strtoupper(substr($b['name'], 0, 1)) }}
                                </span>
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            <h2 class="font-semibold text-gray-900 dark:text-white truncate">{{ $b['name'] }}</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">{{ $b['type'] }}</p>
                        </div>

                        @if($b['is_current'])
                            <span class="inline-flex items-center gap-1 rounded-full bg-primary-100 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300 border border-primary-200 dark:border-primary-500/30 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider shrink-0">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                                Current
                            </span>
                        @endif
                    </div>

                    <dl class="grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Your role</dt>
                            <dd class="text-gray-900 dark:text-white font-medium capitalize">{{ $b['role'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Member since</dt>
                            <dd class="text-gray-900 dark:text-white font-medium">
                                {{ $b['joined_at'] ? \Illuminate\Support\Carbon::parse($b['joined_at'])->format('M Y') : '—' }}
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-auto pt-3 border-t border-gray-100 dark:border-gray-700/60">
                        @if($b['is_current'])
                            <button type="button" disabled
                                    class="w-full h-11 rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 text-sm font-semibold cursor-default">
                                Currently active
                            </button>
                        @else
                            <button type="button"
                                    wire:click="switchTo({{ $b['id'] }})"
                                    wire:loading.attr="disabled"
                                    wire:target="switchTo({{ $b['id'] }})"
                                    class="w-full h-11 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold transition-all duration-300 ease-out active:scale-[0.98]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2
                                           disabled:opacity-60 disabled:cursor-wait inline-flex items-center justify-center gap-2">
                                <span wire:loading.remove wire:target="switchTo({{ $b['id'] }})">Switch to this business</span>
                                <span wire:loading wire:target="switchTo({{ $b['id'] }})" class="inline-flex items-center gap-2">
                                    <svg class="animate-spin h-4 w-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    Switching…
                                </span>
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Guidance footer --}}
    <div class="card p-4 text-xs text-gray-500 dark:text-gray-400 flex items-start gap-2">
        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p>
            Switching updates your active context everywhere on the platform: the dashboard, bookings, properties,
            and settings you see will all belong to the business you select. Employees cannot switch — only owners
            and admins can.
        </p>
    </div>
</div>