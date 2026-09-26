{{-- resources/views/public/partials/⚡notification-bell.blade.php --}}
<?php

use App\Models\User;
use App\Models\UserNotification;
use App\Services\UserNotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    private const SCOPE = UserNotification::SCOPE_TOURIST;

    #[Computed]
    public function unreadCount(): int
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return 0;
        }

        return app(UserNotificationService::class)->unreadCount($user, self::SCOPE);
    }

    #[Computed]
    public function recent()
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return collect();
        }

        return app(UserNotificationService::class)->recent($user, self::SCOPE, 6);
    }

    /** @return array<string, string> */
    #[Computed]
    public function iconPaths(): array
    {
        return [
            'inbox'        => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>',
            'clock'        => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            'alert'        => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
            'check-circle' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            'x-circle'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        ];
    }

    /** @return array<string, string> */
    #[Computed]
    public function colorClasses(): array
    {
        return [
            'amber'   => 'bg-amber-50 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400',
            'rose'    => 'bg-rose-50 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400',
            'blue'    => 'bg-blue-50 dark:bg-blue-500/15 text-blue-600 dark:text-blue-400',
            'emerald' => 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
            'slate'   => 'bg-gray-100 dark:bg-gray-700/40 text-gray-500 dark:text-gray-400',
        ];
    }

    public function timeAgo(Carbon $time): string
    {
        return $time->diffForHumans();
    }

    public function open(int $notificationId): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $notification = UserNotification::query()
            ->forUser($user->id)
            ->forScope(self::SCOPE)
            ->whereKey($notificationId)
            ->first();

        if (! $notification) {
            return;
        }

        $notification->markRead();

        $url = $notification->resolvedUrl($user);

        if ($url) {
            $this->redirect($url, navigate: true);
        }
    }

    public function markAllRead(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return;
        }

        app(UserNotificationService::class)->markAllRead($user, self::SCOPE);
        unset($this->unreadCount, $this->recent);
    }
};
?>

@push('styles')
    @once
        <style>
            .public-notif-dropdown {
                animation: publicNotifDropdownIn .15s cubic-bezier(.16,1,.3,1);
            }
            @keyframes publicNotifDropdownIn {
                from { opacity: 0; transform: translateY(-4px); }
                to   { opacity: 1; transform: translateY(0); }
            }
            @media (prefers-reduced-motion: reduce) {
                .public-notif-dropdown { animation: none; }
            }
        </style>
    @endonce
@endpush

<div
    x-data="{
        open: false,
        toggle() {
            this.open = ! this.open;
            if (this.open) {
                $wire.markAllRead();
            }
        }
    }"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
    class="relative"
    wire:poll.30s
>
    <button type="button"
            @click="toggle()"
            :aria-expanded="open.toString()"
            aria-haspopup="true"
            aria-label="Notifications"
            class="relative flex items-center justify-center size-11 sm:size-9 rounded-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800
                   text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white
                   transition-all duration-200 active:scale-95 touch-manipulation
                   [-webkit-tap-highlight-color:transparent]
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>

        @if($this->unreadCount > 0)
            <span class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center min-w-4.5 h-4.5 px-1 rounded-full
                         bg-rose-500 text-white text-[10px] font-bold ring-2 ring-white dark:ring-gray-900 tabular-nums"
                  aria-hidden="true">
                {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
            </span>
            <span class="sr-only">{{ $this->unreadCount }} unread notifications</span>
        @endif
    </button>

    {{-- Dropdown.
         ── RESPONSIVE POSITIONING ──
         On mobile (< sm): the dropdown is positioned relative to the
         VIEWPORT via `fixed`, spanning `left-3 right-3`. This prevents
         the off-screen clipping that occurred when the dropdown was
         anchored `right-0` to a bell that isn't at the far right of
         the header — the previous 320px-wide absolute dropdown would
         extend past the left edge of the viewport and clip the header
         text. `top-[calc(...)]` puts it just below the public header,
         accounting for the notch safe-area inset.

         On desktop (sm+): reverts to bell-relative `absolute right-0`
         positioning with a fixed 384px width. There's plenty of
         horizontal room at ≥640px, so the anchor-based approach is
         correct and gives the tighter, more "attached" feel. --}}
    <div x-cloak
         :class="open ? 'public-notif-dropdown' : 'hidden'"
         class="z-50 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-2xl overflow-hidden
                fixed left-3 right-3 top-[calc(4rem+env(safe-area-inset-top)+0.5rem)]
                sm:absolute sm:left-auto sm:right-0 sm:top-full sm:mt-2 sm:w-96"
         role="menu"
         aria-label="Notifications">

        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 min-w-0">
                <span class="w-5 h-px bg-primary-600 shrink-0"></span>
                <p class="text-sm font-bold text-gray-900 dark:text-white truncate">Notifications</p>
                @if($this->unreadCount > 0)
                    <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-rose-500 text-white text-[10px] font-bold tabular-nums shrink-0">
                        {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
                    </span>
                @endif
            </div>
            <button type="button"
                    @click="open = false"
                    aria-label="Close notifications"
                    class="inline-flex items-center justify-center size-11 sm:size-9 shrink-0 rounded-md text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700
                           transition-all duration-200 touch-manipulation
                           [-webkit-tap-highlight-color:transparent]
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                </svg>
            </button>
        </div>

        <div class="max-h-[min(420px,60vh)] overflow-y-auto">
            @forelse($this->recent as $item)
                @php
                    $isUnread = $item->isUnread();
                    $itemUrl  = $item->resolvedUrl(auth()->user());
                @endphp
                <a href="{{ $itemUrl ?? '#' }}"
                   wire:key="notif-{{ $item->id }}"
                   wire:click.prevent="open({{ $item->id }})"
                   @click="open = false"
                   class="flex items-start gap-3 px-4 py-3 min-h-[44px] border-b border-gray-100 dark:border-gray-700/60 last:border-b-0
                          {{ $isUnread ? 'bg-primary-50/40 dark:bg-primary-500/5' : '' }}
                          hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors touch-manipulation
                          [-webkit-tap-highlight-color:transparent]
                          focus-visible:outline-none focus-visible:bg-gray-50 dark:focus-visible:bg-gray-700/40">
                    <span class="shrink-0 inline-flex items-center justify-center size-9 rounded-lg
                                 {{ $this->colorClasses[$item->color] ?? $this->colorClasses['slate'] }}">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            {!! $this->iconPaths[$item->icon] ?? $this->iconPaths['inbox'] !!}
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            @if($isUnread)
                                <span class="w-1.5 h-1.5 rounded-full bg-primary-500 shrink-0" aria-hidden="true"></span>
                            @endif
                            <p class="text-sm {{ $isUnread ? 'font-bold' : 'font-medium' }} text-gray-900 dark:text-white truncate">
                                {{ $item->title }}
                            </p>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2 leading-relaxed">
                            {{ $item->message }}
                        </p>
                        <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1 tabular-nums">
                            {{ $this->timeAgo($item->created_at) }}
                        </p>
                    </div>
                    <svg class="size-3.5 shrink-0 text-gray-400 mt-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            @empty
                <div class="px-5 py-10 text-center">
                    <div class="inline-flex items-center justify-center size-12 rounded-2xl bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 mb-3">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">You're all caught up</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-[240px] mx-auto">
                        Booking confirmations and platform updates will show up here.
                    </p>
                </div>
            @endforelse
        </div>

        <div class="border-t border-gray-200 dark:border-gray-700 px-3 py-2 bg-gray-50/60 dark:bg-gray-900/40">
            <a href="{{ route('notifications.index') }}"
               wire:navigate
               @click="open = false"
               class="flex items-center justify-center gap-1.5 h-11 sm:h-10 rounded-lg text-xs font-semibold text-primary-600 dark:text-primary-400
                      hover:bg-primary-50 dark:hover:bg-primary-500/10
                      transition-all duration-200 active:scale-95 touch-manipulation
                      [-webkit-tap-highlight-color:transparent]
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <span>View all notifications</span>
                <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </div>
</div>