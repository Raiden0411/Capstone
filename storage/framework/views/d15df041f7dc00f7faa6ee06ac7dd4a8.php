
<?php

use App\Services\PublicNotificationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /**
     * Polling interval in seconds. Set to 0 to disable.
     *
     * Guests never see a value here — mount() zeroes it so the poll
     * directive never renders for unauthenticated visitors. That keeps
     * the header's mobile bandwidth cost at zero for guests.
     */
    public int $pollInterval = 60;

    public function mount(): void
    {
        // If the header renders this component for a guest (some
        // layouts do, as a placeholder), disable polling. There is
        // nothing to poll for, and mobile users should not pay for
        // empty network roundtrips.
        if (! Auth::check()) {
            $this->pollInterval = 0;
        }
    }

    public function hydrate(): void
    {
        // If the user logged out from another tab mid-session,
        // subsequent poll requests hydrate the component with a
        // still-live poll interval. Re-check and stop the poll.
        if (! Auth::check()) {
            $this->pollInterval = 0;
        }
    }

    #[Computed]
    public function payload(): array
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (! $user) {
            return ['items' => [], 'count' => 0];
        }

        return app(PublicNotificationService::class)->forUser($user);
    }

    #[Computed]
    public function count(): int
    {
        return (int) ($this->payload['count'] ?? 0);
    }

    #[Computed]
    public function items(): array
    {
        return $this->payload['items'] ?? [];
    }

    public function iconPath(string $icon): string
    {
        return match ($icon) {
            'clock'        => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            'check-circle' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            'alert'        => 'M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z',
            'inbox'        => 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
            default        => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
        };
    }

    /** @return array{bg: string, text: string, border: string} */
    public function colorClasses(string $color): array
    {
        return match ($color) {
            'emerald' => [
                'bg'     => 'bg-emerald-100 dark:bg-emerald-500/20',
                'text'   => 'text-emerald-700 dark:text-emerald-300',
                'border' => 'border-emerald-200 dark:border-emerald-500/30',
            ],
            'rose' => [
                'bg'     => 'bg-rose-100 dark:bg-rose-500/20',
                'text'   => 'text-rose-700 dark:text-rose-300',
                'border' => 'border-rose-200 dark:border-rose-500/30',
            ],
            'amber' => [
                'bg'     => 'bg-amber-100 dark:bg-amber-500/20',
                'text'   => 'text-amber-700 dark:text-amber-300',
                'border' => 'border-amber-200 dark:border-amber-500/30',
            ],
            'blue' => [
                'bg'     => 'bg-blue-100 dark:bg-blue-500/20',
                'text'   => 'text-blue-700 dark:text-blue-300',
                'border' => 'border-blue-200 dark:border-blue-500/30',
            ],
            default => [
                'bg'     => 'bg-gray-100 dark:bg-gray-500/20',
                'text'   => 'text-gray-700 dark:text-gray-300',
                'border' => 'border-gray-200 dark:border-gray-500/30',
            ],
        };
    }

    public function render()
    {
        return $this->view();
    }
};
?>



<?php
    // Aria-label for the bell button. The count appears in the label
    // when there are notifications, so screen-reader users get the
    // same signal that sighted users get from the badge.
    $bellAriaLabel = $this->count > 0
        ? "Notifications ({$this->count})"
        : 'Notifications';
?>

<div
    <?php if($pollInterval > 0): ?> wire:poll.<?php echo e($pollInterval); ?>s <?php endif; ?>
    x-data="{
        open: false,
        dropdownStyle: '',

        reposition() {
            if (! this.open) return;

            const dropdown = this.$refs.dropdown;
            const wrapper  = this.$refs.wrapper;
            if (! dropdown || ! wrapper) return;

            const isMobile = window.innerWidth < 640;

            if (! isMobile) {
                this.dropdownStyle = '';
                return;
            }

            const margin      = 12;
            const maxWidth    = 384;
            const wrapperRect = wrapper.getBoundingClientRect();
            const width       = Math.min(window.innerWidth - margin * 2, maxWidth);
            const left        = margin - wrapperRect.left;

            this.dropdownStyle = `width: ${width}px; left: ${left}px; right: auto;`;
        }
    }"
    x-ref="wrapper"
    x-on:resize.window.throttle.100ms="reposition()"
    x-on:click.outside="open = false"
    x-on:keydown.escape.window="open = false"
    class="relative">

    
    <button type="button"
            @click="open = !open; if (open) $nextTick(() => reposition())"
            :aria-expanded="open.toString()"
            aria-haspopup="true"
            aria-label="<?php echo e($bellAriaLabel); ?>"
            class="relative flex items-center justify-center size-9 md:size-10 rounded-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 transition-all duration-200 active:scale-95">

        <svg xmlns="http://www.w3.org/2000/svg" class="shrink-0 size-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->count > 0): ?>
            <span class="absolute -top-1 -right-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white ring-2 ring-white dark:ring-gray-900 tabular-nums"
                  aria-hidden="true">
                <?php echo e($this->count > 9 ? '9+' : $this->count); ?>

            </span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </button>

    
    <div x-ref="dropdown"
         wire:ignore.self
         x-cloak
         :style="dropdownStyle"
         :class="open
             ? 'opacity-100 scale-100 translate-y-0 pointer-events-auto'
             : 'opacity-0 scale-95 -translate-y-1 pointer-events-none'"
         class="absolute right-0 z-50 mt-2 w-80 sm:w-96 max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-2xl origin-top sm:origin-top-right transition-all duration-150 ease-out"
         role="dialog"
         aria-label="Notifications">

        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-4 py-3">
            <p class="text-sm font-semibold text-gray-900 dark:text-white">Notifications</p>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->count > 0): ?>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    <?php echo e($this->count); ?> <?php echo e(\Illuminate\Support\Str::plural('notification', $this->count)); ?>

                </span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($this->items)): ?>
            <div class="p-8 text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800 mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.143 17.082a24.248 24.248 0 003.844.148m-3.844-.148a23.856 23.856 0 01-5.455-1.31 8.964 8.964 0 002.3-5.542m3.155 6.852a3 3 0 005.667 1.97m1.965-2.277L21 21m-4.225-4.225a23.81 23.81 0 003.536-1.003A8.967 8.967 0 0118 9.75V9A6 6 0 006.53 6.53m10.245 10.245L6.53 6.53M3 3l3.53 3.53"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">You're all caught up</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">No notifications right now.</p>
            </div>
        <?php else: ?>
            <div class="max-h-[min(28rem,60vh)] overflow-y-auto">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $colors   = $this->colorClasses($notification['color'] ?? 'gray');
                        $iconPath = $this->iconPath($notification['icon'] ?? 'bell');
                        $ts       = $notification['time'] ?? null;
                    ?>
                    <a href="<?php echo e($notification['url']); ?>"
                       wire:navigate
                       <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'notif-'.e($notification['id']).''; ?>wire:key="notif-<?php echo e($notification['id']); ?>"
                       @click="open = false"
                       class="flex items-start gap-3 px-4 py-3 border-b border-gray-100 dark:border-gray-700/60 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors">
                        <div class="flex items-center justify-center shrink-0 w-9 h-9 rounded-xl border <?php echo e($colors['bg']); ?> <?php echo e($colors['text']); ?> <?php echo e($colors['border']); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="<?php echo e($iconPath); ?>"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                <?php echo e($notification['title']); ?>

                            </p>
                            <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-400 leading-relaxed line-clamp-2">
                                <?php echo e($notification['message']); ?>

                            </p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ts): ?>
                                
                                <time datetime="<?php echo e(\Carbon\Carbon::createFromTimestamp($ts)->toIso8601String()); ?>"
                                      class="mt-1 block text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                    <?php echo e(\Carbon\Carbon::createFromTimestamp($ts)->diffForHumans()); ?>

                                </time>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0 text-gray-300 dark:text-gray-600 mt-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views\public\partials\⚡notification-bell.blade.php ENDPATH**/ ?>