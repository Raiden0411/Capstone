<?php

use App\Services\SuperadminNotificationService;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /** Polling interval in seconds. Set to 0 to disable. */
    public int $pollInterval = 60;

    #[Computed]
    public function count(): int
    {
        return app(SuperadminNotificationService::class)->pendingCount();
    }

    #[Computed]
    public function items()
    {
        return app(SuperadminNotificationService::class)->recentApplications();
    }

    public function render()
    {
        return $this->view();
    }
};
?>

<div
    @if ($pollInterval > 0) wire:poll.{{ $pollInterval }}s @endif
    x-data="{ open: false }"
    x-on:livewire:navigated.window="$wire.$refresh()"
    x-on:click.outside="open = false"
    x-on:keydown.escape.window="open = false"
    class="relative">

    {{-- Bell button --}}
    <button type="button"
            @click="open = !open"
            :aria-expanded="open.toString()"
            aria-haspopup="true"
            class="relative flex items-center gap-2 rounded-full border border-gray-300 bg-white px-2.5 py-1.5 text-gray-700 transition-all duration-200 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
            aria-label="Notifications">

        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>

        <span class="hidden text-xs font-medium md:inline">Notifications</span>

        @if ($this->count > 0)
            <span class="absolute -top-1 -right-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white ring-2 ring-white dark:ring-gray-900">
                {{ $this->count > 99 ? '99+' : $this->count }}
            </span>
        @endif
    </button>

    {{-- Dropdown --}}
    <div x-cloak
         x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95 translate-y-1"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-1"
         class="absolute right-0 z-50 mt-2 w-72 sm:w-80 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800">

        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <p class="text-sm font-semibold text-gray-900 dark:text-white">Notifications</p>
            @if ($this->count > 0)
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $this->count }} pending</span>
            @endif
        </div>

        @if ($this->items->isEmpty())
            <div class="p-6 text-center">
                <svg class="mx-auto mb-2 h-8 w-8 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <p class="text-sm text-gray-500 dark:text-gray-400">No pending applications</p>
            </div>
        @else
            <div class="max-h-80 overflow-y-auto">
                @foreach ($this->items as $application)
                    <a href="{{ route('superadmin.business-applications.show', $application) }}"
                       wire:navigate
                       wire:key="notif-app-{{ $application->id }}"
                       @click="open = false"
                       class="flex items-start gap-3 border-b border-gray-100 px-4 py-3 transition-colors last:border-0 hover:bg-gray-50 active:scale-[0.99] dark:border-gray-700 dark:hover:bg-gray-700/50">
                        @if ($application->user?->avatar)
                            <img src="{{ asset('storage/' . $application->user->avatar) }}"
                                 alt="{{ $application->user->name }}"
                                 class="h-8 w-8 shrink-0 rounded-full object-cover">
                        @else
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-bold text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">
                                {{ strtoupper(substr($application->business_name ?? $application->user?->name ?? '?', 0, 1)) }}
                            </div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                {{ $application->business_name ?? 'Untitled Application' }}
                            </p>
                            <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                                by {{ $application->user?->name ?? 'Unknown' }}
                            </p>
                            @if ($application->submitted_at)
                                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                                    {{ $application->submitted_at->diffForHumans() }}
                                </p>
                            @endif
                        </div>
                        <span class="shrink-0 text-xs font-medium text-primary-600 dark:text-primary-400">Review</span>
                    </a>
                @endforeach
            </div>

            <div class="border-t border-gray-200 p-2 dark:border-gray-700">
                <a href="{{ route('superadmin.business-applications.index') }}"
                   wire:navigate
                   @click="open = false"
                   class="flex w-full items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-primary-600 transition-colors hover:bg-primary-50 active:scale-[0.98] dark:hover:bg-primary-500/10">
                    View all applications
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        @endif
    </div>
</div>