
<?php

use App\Models\BusinessApplication;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.auth')]
#[Title('Register Your Business')]
class extends Component
{
    /**
     * The user's most relevant KYB application.
     *
     * Preference order:
     *   1. An *editable* application (draft or needs_revision) — because
     *      that's the one the user can actually act on. Even if the same
     *      user has a `rejected` record that was touched more recently
     *      (e.g. a reviewer added a rejection reason after the user
     *      started a fresh draft), the editable record is what the UI
     *      should point at — otherwise the "Continue Application" CTA
     *      would be replaced by "Start New Application", which the
     *      server-side RegisterBusinessController::store() would then
     *      attach to the SAME draft anyway. That mismatch is a UX bug.
     *   2. Otherwise the latest record of any status (submitted, approved,
     *      rejected) — the panels for those states already handle the
     *      "read-only" narrative correctly.
     */
    #[Computed]
    public function application(): ?BusinessApplication
    {
        $user = Auth::user();

        if (!$user) {
            return null;
        }

        $editable = $user->businessApplications()
            ->whereIn('status', [
                BusinessApplication::STATUS_DRAFT,
                BusinessApplication::STATUS_NEEDS_REVISION,
            ])
            ->latest('updated_at')
            ->first();

        if ($editable) {
            return $editable;
        }

        return $user->businessApplications()
            ->latest('updated_at')
            ->first();
    }

    /** Current state key for the panel switch. */
    #[Computed]
    public function state(): string
    {
        return $this->application?->status ?? 'none';
    }

    #[Computed]
    public function isEditable(): bool
    {
        return in_array($this->state, [
            BusinessApplication::STATUS_DRAFT,
            BusinessApplication::STATUS_NEEDS_REVISION,
        ], true);
    }

    #[Computed]
    public function isSubmitted(): bool
    {
        return in_array($this->state, [
            BusinessApplication::STATUS_PENDING,
            BusinessApplication::STATUS_UNDER_REVIEW,
        ], true);
    }

    #[Computed]
    public function isApproved(): bool
    {
        return $this->state === BusinessApplication::STATUS_APPROVED;
    }

    #[Computed]
    public function isRejected(): bool
    {
        return $this->state === BusinessApplication::STATUS_REJECTED;
    }

    #[Computed]
    public function siteName(): string
    {
        return (string) SiteSetting::getValue('site_name', config('app.name'));
    }

    #[Computed]
    public function logoUrl(): ?string
    {
        $path = SiteSetting::getValue('site_logo');

        return $path ? asset('storage/' . $path) : null;
    }

    #[Computed]
    public function heroUrl(): string
    {
        $path = SiteSetting::getValue('hero_background_image');

        return $path
            ? asset('storage/' . $path)
            : 'https://images.unsplash.com/photo-1506748686214-e9df14d4d9d0?auto=format&fit=crop&w=1600&q=80';
    }
};
?>

<main class="min-h-[100dvh] flex flex-col lg:flex-row bg-white dark:bg-gray-950">

    
    <aside class="relative w-full shrink-0 min-h-[280px] sm:min-h-[360px] lg:min-h-0 lg:h-[100dvh] lg:w-[55%] lg:sticky lg:top-0 overflow-hidden">

        <img src="<?php echo e($this->heroUrl); ?>"
             alt=""
             loading="eager"
             fetchpriority="high"
             decoding="async"
             width="1600"
             height="900"
             class="absolute inset-0 w-full h-full object-cover">

        
        <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/35 to-black/15" aria-hidden="true"></div>

        
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,transparent_40%,rgba(0,0,0,0.35)_100%)]" aria-hidden="true"></div>

        
        <div class="relative z-10 flex flex-col justify-end h-full min-h-[280px] sm:min-h-[360px] lg:min-h-0 px-6 sm:px-10 lg:px-16 xl:px-24 pb-8 sm:pb-12 lg:pb-16 text-white">

            
            <span class="inline-flex w-max items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-[11px] font-semibold tracking-wider uppercase">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                <?php echo e($this->siteName); ?>

            </span>

            
            <h1 class="mt-5 sm:mt-6 max-w-2xl text-3xl sm:text-4xl md:text-5xl xl:text-[56px] font-extrabold tracking-tight leading-[1.05]">
                Register your business &<br class="hidden sm:inline" />
                welcome the world
            </h1>

            
            <p class="mt-4 sm:mt-5 max-w-xl text-sm sm:text-base font-medium leading-relaxed text-white/75">
                Put your resort, inn, eco-park, or restaurant on the map.
                Reach more visitors and share the best of Victorias City.
            </p>
        </div>
    </aside>

    
    <section class="relative flex-1 flex items-center justify-center px-5 sm:px-8 lg:px-16 py-10 lg:py-16">

        
        <div class="absolute inset-0 -z-10 opacity-[0.35] dark:opacity-[0.06] pointer-events-none" aria-hidden="true">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(99,102,241,0.10),transparent_55%)]"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_bottom_left,rgba(16,185,129,0.08),transparent_55%)]"></div>
        </div>

        <div class="w-full max-w-md">

            
            <a href="<?php echo e(route('home')); ?>" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-all duration-200 active:scale-95 rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 mb-8">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to home
            </a>

            
            <div class="flex items-center gap-3 mb-8">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->logoUrl): ?>
                    <img src="<?php echo e($this->logoUrl); ?>"
                         alt="<?php echo e($this->siteName); ?>"
                         loading="lazy"
                         decoding="async"
                         width="40"
                         height="40"
                         class="w-10 h-10 object-contain rounded-xl shrink-0">
                <?php else: ?>
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white shrink-0 font-bold text-base shadow-sm">
                        <?php echo e(strtoupper(substr($this->siteName, 0, 1))); ?>

                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <span class="text-base font-semibold text-gray-900 dark:text-white tracking-tight"><?php echo e($this->siteName); ?></span>
            </div>

            
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-gray-900 dark:text-white leading-tight">
                Register Your Business
            </h2>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                Complete our verification process to list your business on <?php echo e($this->siteName); ?>.
            </p>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('message')): ?>
                <div x-data="{ show: true }"
                     x-init="setTimeout(() => show = false, 4000)"
                     :class="show ? '' : 'hidden'"
                     role="status"
                     aria-live="polite"
                     class="mt-6 flex items-start gap-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 p-3.5 text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="flex-1 font-medium text-emerald-800 dark:text-emerald-300 leading-relaxed"><?php echo e(session('message')); ?></span>
                    <button type="button"
                            @click="show = false"
                            class="inline-flex items-center justify-center h-7 w-7 rounded-md text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/10
                                   transition-all duration-200 active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50 shrink-0"
                            aria-label="Dismiss message">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('error')): ?>
                <div x-data="{ show: true }"
                     x-init="setTimeout(() => show = false, 5000)"
                     :class="show ? '' : 'hidden'"
                     role="alert"
                     aria-live="polite"
                     class="mt-6 flex items-start gap-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 p-3.5 text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                    </svg>
                    <span class="flex-1 font-medium text-rose-800 dark:text-rose-300 leading-relaxed"><?php echo e(session('error')); ?></span>
                    <button type="button"
                            @click="show = false"
                            class="inline-flex items-center justify-center h-7 w-7 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                                   transition-all duration-200 active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 shrink-0"
                            aria-label="Dismiss error">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->isApproved): ?>
                
                <div class="mt-8 rounded-2xl border border-emerald-200/70 dark:border-emerald-500/25 bg-emerald-50/40 dark:bg-emerald-500/[0.06] p-6">
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 w-11 h-11 rounded-xl flex items-center justify-center bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                Your business is approved
                            </h3>
                            <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                Your business account is live on the platform. Head to your dashboard to manage properties, services, bookings, and payments.
                            </p>
                        </div>
                    </div>

                    <a href="<?php echo e(route('tenant.dashboard')); ?>" wire:navigate
                       class="mt-6 w-full inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-sm
                              transition-all duration-200 active:scale-95
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-950">
                        Go to business dashboard
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>

            <?php elseif($this->isSubmitted): ?>
                
                <div class="mt-8 rounded-2xl border border-amber-200/70 dark:border-amber-500/25 bg-amber-50/40 dark:bg-amber-500/[0.06] p-6">
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 w-11 h-11 rounded-xl flex items-center justify-center bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-500/30">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                Application under review
                            </h3>
                            <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                Our team is reviewing your submission. We'll notify you as soon as a decision is made.
                            </p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->application?->submitted_at): ?>
                                <p class="mt-3 text-[11px] font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-400">
                                    Submitted <?php echo e($this->application->submitted_at->diffForHumans()); ?>

                                </p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php elseif($this->isRejected): ?>
                
                <div class="mt-8 rounded-2xl border border-rose-200/70 dark:border-rose-500/25 bg-rose-50/40 dark:bg-rose-500/[0.06] p-6">
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 w-11 h-11 rounded-xl flex items-center justify-center bg-rose-100 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-500/30">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                Application not approved
                            </h3>
                            <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                Unfortunately, your previous application was rejected. You may start a new one with corrected information.
                            </p>
                        </div>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->application?->rejection_reason): ?>
                        <div class="mt-5 rounded-xl bg-white/60 dark:bg-rose-500/[0.08] border border-rose-100 dark:border-rose-500/20 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-rose-700 dark:text-rose-400 mb-1.5">
                                Reason from reviewer
                            </p>
                            <p class="text-sm text-rose-900 dark:text-rose-200 leading-relaxed">
                                <?php echo e($this->application->rejection_reason); ?>

                            </p>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <form method="POST" action="<?php echo e(route('register_business.start')); ?>" class="mt-6"
                          x-data="{ loading: false }" @submit="loading = true">
                        <?php echo csrf_field(); ?>
                        <button type="submit"
                                :disabled="loading"
                                :aria-busy="loading"
                                class="w-full inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold shadow-sm
                                       transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-950
                                       disabled:opacity-60 disabled:cursor-not-allowed">
                            
                            <span :class="loading ? 'hidden' : 'inline-flex items-center gap-2'">
                                Start new application
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </span>
                            <span :class="loading ? 'inline-flex items-center gap-2' : 'hidden'">
                                <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin w-4 h-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                Starting…
                            </span>
                        </button>
                    </form>
                </div>

            <?php elseif($this->isEditable): ?>
                
                <div class="mt-8 rounded-2xl border border-indigo-200/70 dark:border-indigo-500/25 bg-indigo-50/40 dark:bg-indigo-500/[0.06] p-6">
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 w-11 h-11 rounded-xl flex items-center justify-center bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/30">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->state === BusinessApplication::STATUS_NEEDS_REVISION): ?>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                                </svg>
                            <?php else: ?>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->state === BusinessApplication::STATUS_NEEDS_REVISION): ?>
                                    Revision requested
                                <?php else: ?>
                                    Application in progress
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </h3>
                            <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->state === BusinessApplication::STATUS_NEEDS_REVISION): ?>
                                    Our reviewer requested changes to your application. Review the notes, update your details, and resubmit.
                                <?php else: ?>
                                    You have an unfinished KYB application. Pick up right where you left off.
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->state === BusinessApplication::STATUS_NEEDS_REVISION && $this->application?->revision_notes): ?>
                        <div class="mt-5 rounded-xl bg-white/60 dark:bg-amber-500/[0.08] border border-amber-100 dark:border-amber-500/20 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400 mb-1.5">
                                Reviewer notes
                            </p>
                            <p class="text-sm text-amber-900 dark:text-amber-200 leading-relaxed">
                                <?php echo e($this->application->revision_notes); ?>

                            </p>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <form method="POST" action="<?php echo e(route('register_business.start')); ?>" class="mt-6"
                          x-data="{ loading: false }" @submit="loading = true">
                        <?php echo csrf_field(); ?>
                        <button type="submit"
                                :disabled="loading"
                                :aria-busy="loading"
                                class="w-full inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-sm
                                       transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-950
                                       disabled:opacity-60 disabled:cursor-not-allowed">
                            <span :class="loading ? 'hidden' : 'inline-flex items-center gap-2'">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->state === BusinessApplication::STATUS_NEEDS_REVISION): ?>
                                    Review &amp; update application
                                <?php else: ?>
                                    Continue application
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </span>
                            <span :class="loading ? 'inline-flex items-center gap-2' : 'hidden'">
                                <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin w-4 h-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                Opening…
                            </span>
                        </button>
                    </form>
                </div>

            <?php else: ?>
                
                <div class="mt-8 rounded-2xl border border-gray-200/70 dark:border-gray-700/70 bg-white/60 dark:bg-gray-900/40 backdrop-blur-sm p-6 shadow-sm">

                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-5">
                        What to expect
                    </p>

                    <ol class="space-y-4">
                        <li class="flex items-start gap-3">
                            <span class="shrink-0 w-6 h-6 rounded-full bg-primary-50 dark:bg-primary-500/15 text-primary-700 dark:text-primary-400 text-[11px] font-bold flex items-center justify-center border border-primary-100 dark:border-primary-500/25">1</span>
                            <div class="min-w-0 pt-0.5">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Business details</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                    Name, type, address, and ownership information.
                                </p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="shrink-0 w-6 h-6 rounded-full bg-primary-50 dark:bg-primary-500/15 text-primary-700 dark:text-primary-400 text-[11px] font-bold flex items-center justify-center border border-primary-100 dark:border-primary-500/25">2</span>
                            <div class="min-w-0 pt-0.5">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Upload documents</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                    DTI/SEC/CDA, BIR 2303, Mayor's Permit, and valid owner ID.
                                </p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="shrink-0 w-6 h-6 rounded-full bg-primary-50 dark:bg-primary-500/15 text-primary-700 dark:text-primary-400 text-[11px] font-bold flex items-center justify-center border border-primary-100 dark:border-primary-500/25">3</span>
                            <div class="min-w-0 pt-0.5">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Get verified</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                    Our team reviews your submission and activates your business.
                                </p>
                            </div>
                        </li>
                    </ol>

                    <form method="POST" action="<?php echo e(route('register_business.start')); ?>" class="mt-6"
                          x-data="{ loading: false }" @submit="loading = true">
                        <?php echo csrf_field(); ?>
                        <button type="submit"
                                :disabled="loading"
                                :aria-busy="loading"
                                class="w-full inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                       transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-950
                                       disabled:opacity-60 disabled:cursor-not-allowed">
                            <span :class="loading ? 'hidden' : 'inline-flex items-center gap-2'">
                                Start application
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </span>
                            <span :class="loading ? 'inline-flex items-center gap-2' : 'hidden'">
                                <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin w-4 h-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                Starting…
                            </span>
                        </button>
                    </form>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <p class="mt-8 text-center text-xs text-gray-400 dark:text-gray-500 leading-relaxed">
                By continuing, you agree to <?php echo e($this->siteName); ?>'s terms and privacy policy.
            </p>
        </div>
    </section>
</main><?php /**PATH C:\laragon\www\Capstone\resources\views\public\pages\⚡register-business.blade.php ENDPATH**/ ?>