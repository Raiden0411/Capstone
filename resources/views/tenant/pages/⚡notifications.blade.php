{{-- resources/views/tenant/pages/⚡notifications.blade.php --}}
<?php

use App\Models\User;
use App\Models\UserNotification;
use App\Services\UserNotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
    #[Layout('tenant::layouts.app')]
    #[Title('Notifications')]
class extends Component
{
    use WithPagination;

    private const SCOPE = UserNotification::SCOPE_BUSINESS;

    #[Url(keep: true)]
    public string $filter = 'all';

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

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
    public function notifications()
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return UserNotification::query()->whereRaw('1 = 0')->paginate(20);
        }

        return app(UserNotificationService::class)->paginate(
            $user,
            self::SCOPE,
            perPage: 20,
            unreadOnly: $this->filter === 'unread',
        );
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

    public function markRead(int $notificationId): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return;
        }

        app(UserNotificationService::class)->markRead($user, $notificationId);
        unset($this->unreadCount, $this->notifications);
    }

    /**
     * Mark as read and navigate in a single server round-trip.
     * Splitting mark-read and navigation across two parallel requests
     * (wire:click + wire:navigate) races — the update lands on a DOM
     * the SPA fetch is already replacing, and the badge stays stale.
     */
    public function openNotification(int $notificationId): void
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
        unset($this->unreadCount, $this->notifications);

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
        unset($this->unreadCount, $this->notifications);

        $this->dispatch('toast', message: 'All notifications marked as read.', type: 'success');
    }

    public function clearAll(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return;
        }

        app(UserNotificationService::class)->clearAll($user, self::SCOPE);
        unset($this->unreadCount, $this->notifications);

        $this->dispatch('toast', message: 'Notifications cleared.', type: 'success');
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto space-y-6">

    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
            <div class="flex items-center gap-4 min-w-0">
                <div class="hidden sm:flex w-12 h-12 rounded-xl bg-primary-600 text-white items-center justify-center shrink-0 shadow-md shadow-primary-500/30">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Inbox</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="font-display text-2xl sm:text-3xl font-semibold text-gray-900 dark:text-white tracking-tight leading-tight">
                            Notifications
                        </h1>
                        @if($this->unreadCount > 0)
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                                         bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300
                                         border border-rose-200 dark:border-rose-500/30 tabular-nums">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse motion-reduce:animate-none"></span>
                                {{ $this->unreadCount }} unread
                            </span>
                        @endif
                    </div>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Booking requests, guest activity, and platform reminders.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
                @if($this->unreadCount > 0)
                    <button type="button"
                            wire:click="markAllRead"
                            wire:loading.attr="disabled"
                            wire:target="markAllRead"
                            class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                   transition-all duration-200 active:scale-95 touch-manipulation [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="markAllRead" class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Mark all read
                        </span>
                        <span wire:loading wire:target="markAllRead" class="inline-flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Working…
                        </span>
                    </button>
                @endif
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4">
        <div class="flex flex-wrap gap-2 items-center">
            @foreach(['all' => 'All', 'unread' => 'Unread'] as $val => $label)
                @php $isActive = $filter === $val; @endphp
                <button type="button"
                        wire:click="$set('filter', '{{ $val }}')"
                        wire:key="filter-{{ $val }}"
                        aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                        class="inline-flex items-center gap-2 h-11 sm:h-9 px-3.5 rounded-full text-xs font-semibold uppercase tracking-wide border
                               transition-all duration-200 active:scale-95 touch-manipulation [-webkit-tap-highlight-color:transparent] shrink-0
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $isActive
                                  ? 'bg-primary-600 border-primary-600 text-white shadow-sm'
                                  : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400' }}">
                    <span>{{ $label }}</span>
                    @if($val === 'unread' && $this->unreadCount > 0)
                        <span class="inline-flex items-center justify-center min-w-4.5 h-4.5 px-1 rounded-full
                                     {{ $isActive ? 'bg-white/25 text-white' : 'bg-rose-500 text-white' }} text-[10px] font-bold tabular-nums">
                            {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
                        </span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm overflow-hidden">
        @if($this->notifications->isEmpty())
            <div class="px-6 py-16 text-center">
                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-400 dark:text-gray-500">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                </div>
                <p class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ $filter === 'unread' ? 'Nothing unread' : 'No notifications yet' }}
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $filter === 'unread'
                        ? "You've read everything. Nice."
                        : 'Booking requests, status changes, and platform reminders will show up here.' }}
                </p>
            </div>
        @else
            <div class="divide-y divide-gray-100 dark:divide-gray-700/60">
                @foreach($this->notifications as $item)
                    @php
                        $isUnread = $item->isUnread();
                        $icon     = $item->icon;
                        $color    = $item->color;
                    @endphp
                    <div wire:key="notif-{{ $item->id }}"
                         class="flex items-start gap-3 px-4 sm:px-5 py-4
                                {{ $isUnread ? 'bg-primary-50/40 dark:bg-primary-500/5' : '' }}
                                hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors">
                        <span class="shrink-0 inline-flex items-center justify-center size-10 rounded-lg
                                     {{ $this->colorClasses[$color] ?? $this->colorClasses['slate'] }}">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                {!! $this->iconPaths[$icon] ?? $this->iconPaths['inbox'] !!}
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                @if($isUnread)
                                    <span class="w-1.5 h-1.5 rounded-full bg-primary-500 shrink-0" aria-hidden="true"></span>
                                @endif
                                <p class="text-sm {{ $isUnread ? 'font-bold' : 'font-medium' }} text-gray-900 dark:text-white">
                                    {{ $item->title }}
                                </p>
                            </div>
                            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1 leading-relaxed">
                                {{ $item->message }}
                            </p>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1.5 tabular-nums">
                                {{ $this->timeAgo($item->created_at) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            @if($item->url)
                                <a href="{{ $item->url }}"
                                   wire:click.prevent="openNotification({{ $item->id }})"
                                   class="inline-flex items-center justify-center h-11 sm:h-9 px-3 rounded-lg
                                          border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200
                                          text-xs font-semibold
                                          transition-all duration-200 active:scale-95
                                          hover:bg-gray-50 dark:hover:bg-gray-700
                                          touch-manipulation [-webkit-tap-highlight-color:transparent]
                                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                    Open
                                </a>
                            @endif
                            @if($isUnread)
                                <button type="button"
                                        wire:click="markRead({{ $item->id }})"
                                        aria-label="Mark as read"
                                        title="Mark as read"
                                        class="inline-flex items-center justify-center size-11 sm:size-9 rounded-lg
                                               text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700
                                               transition-all duration-200 active:scale-95
                                               touch-manipulation [-webkit-tap-highlight-color:transparent]
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if($this->notifications->hasPages())
                <div class="px-5 py-4 border-t border-gray-200/80 dark:border-gray-700/80 bg-gray-50/50 dark:bg-gray-900/30">
                    {{ $this->notifications->links() }}
                </div>
            @endif
        @endif
    </div>

    @if($this->notifications->total() > 0)
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Clear everything</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Permanently delete all notifications. This cannot be undone.
                    </p>
                </div>
                <button type="button"
                        x-data="{
                            armed: false,
                            _t: null,
                            arm() { this.armed = true; clearTimeout(this._t); this._t = setTimeout(() => { this.armed = false; this._t = null; }, 4000); },
                            unarm() { clearTimeout(this._t); this._t = null; this.armed = false; },
                            destroy() { clearTimeout(this._t); }
                        }"
                        @click="armed ? (unarm(), $wire.clearAll()) : arm()"
                        wire:loading.attr="disabled"
                        wire:target="clearAll"
                        :class="armed
                            ? 'bg-amber-500 hover:bg-amber-600 border-amber-500 ring-2 ring-amber-400/60'
                            : 'bg-rose-600 hover:bg-rose-700 border-rose-600'"
                        class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border text-white text-sm font-semibold shadow-sm
                               transition-all duration-200 active:scale-95 touch-manipulation [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                               disabled:opacity-60 disabled:cursor-not-allowed shrink-0">
                    <svg :class="armed ? 'hidden' : 'block'" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <svg :class="armed ? 'block' : 'hidden'" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span :class="armed ? 'hidden' : 'inline'">Clear all</span>
                    <span :class="armed ? 'inline' : 'hidden'">Click again to confirm</span>
                </button>
            </div>
        </div>
    @endif
</div>