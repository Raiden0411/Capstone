{{-- resources/views/public/pages/⚡register-business.blade.php --}}
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

    // Rule J: relative /storage path — never asset('storage/').
    #[Computed]
    public function logoUrl(): ?string
    {
        $path = SiteSetting::getValue('site_logo');

        return $path ? '/storage/' . ltrim($path, '/') : null;
    }

    #[Computed]
    public function heroUrl(): string
    {
        $path = SiteSetting::getValue('hero_background_image');

        return $path
            ? '/storage/' . ltrim($path, '/')
            : 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=1600&q=80';
    }
};
?>

<main class="min-h-[100dvh] flex flex-col lg:flex-row
             bg-[#F8F7F3] dark:bg-[#0F172A]"
      x-data="revealOnScroll">

    <aside class="relative w-full shrink-0 min-h-[280px] sm:min-h-[360px] lg:min-h-0 lg:h-[100dvh] lg:w-[55%] lg:sticky lg:top-0 overflow-hidden">

        <img src="{{ $this->heroUrl }}"
             alt=""
             loading="eager"
             fetchpriority="high"
             decoding="async"
             width="1600"
             height="900"
             class="absolute inset-0 w-full h-full object-cover">

        <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/35 to-black/15" aria-hidden="true"></div>
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,transparent_40%,rgba(0,0,0,0.35)_100%)]" aria-hidden="true"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-amber-500/12 via-amber-500/4 to-transparent pointer-events-none" aria-hidden="true"></div>

        <div class="relative z-10 flex flex-col justify-end h-full min-h-[280px] sm:min-h-[360px] lg:min-h-0 px-6 sm:px-10 lg:px-16 xl:px-24 pb-8 sm:pb-12 lg:pb-16 text-white">

            <span data-reveal
                  class="inline-flex w-max items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-[11px] font-semibold tracking-wider uppercase">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400" aria-hidden="true"></span>
                {{ $this->siteName }}
            </span>

            <h1 data-reveal style="--reveal-delay: 80ms"
                class="mt-5 sm:mt-6 max-w-2xl text-3xl sm:text-4xl md:text-5xl xl:text-[56px] font-extrabold tracking-tight leading-[1.05] [text-wrap:balance]">
                Register your business &<br class="hidden sm:inline" />
                welcome the world
            </h1>

            <p data-reveal style="--reveal-delay: 140ms"
               class="mt-4 sm:mt-5 max-w-xl text-sm sm:text-base font-medium leading-relaxed text-white/75">
                Put your resort, inn, eco-park, or restaurant on the map.
                Reach more visitors and share the best of Victorias City.
            </p>
        </div>
    </aside>

    <section class="relative flex-1 flex items-center justify-center px-5 sm:px-8 lg:px-16 py-10 lg:py-16">

        <div class="absolute inset-0 -z-10 opacity-[0.35] dark:opacity-[0.06] pointer-events-none" aria-hidden="true">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(59,130,246,0.10),transparent_55%)]"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_bottom_left,rgba(245,158,11,0.08),transparent_55%)]"></div>
        </div>

        <div class="w-full max-w-md">

            <a href="{{ route('home') }}" wire:navigate
               data-reveal
               class="relative inline-flex items-center gap-1.5 -mx-1 px-1 py-2.5 -my-2.5 text-xs font-medium
                      text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400
                      transition-colors mb-8 rounded
                      before:absolute before:content-[''] before:-inset-1.5 before:rounded
                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to home
            </a>

            <div data-reveal style="--reveal-delay: 60ms" class="flex items-center gap-3 mb-8">
                @if($this->logoUrl)
                    <img src="{{ $this->logoUrl }}"
                         alt="{{ $this->siteName }}"
                         loading="lazy"
                         decoding="async"
                         width="40"
                         height="40"
                         class="w-10 h-10 rounded-full object-contain ring-1 ring-black/5 shadow-sm dark:ring-white/10 shrink-0">
                @else
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white shrink-0 font-bold text-base ring-1 ring-black/5 shadow-sm dark:ring-white/10">
                        {{ strtoupper(substr($this->siteName, 0, 1)) }}
                    </div>
                @endif
                <span class="text-base font-semibold text-gray-900 dark:text-white tracking-tight">{{ $this->siteName }}</span>
            </div>

            <p data-reveal style="--reveal-delay: 100ms"
               class="mb-2 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-primary-600 dark:text-primary-400">
                <span class="h-px w-4 bg-amber-500" aria-hidden="true"></span>
                Business Setup
            </p>

            <h2 data-reveal style="--reveal-delay: 140ms"
                class="text-3xl sm:text-4xl font-bold tracking-tight text-gray-900 dark:text-white leading-tight">
                Register Your Business
            </h2>
            <p data-reveal style="--reveal-delay: 180ms"
               class="mt-2 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                Complete our verification process to list your business on {{ $this->siteName }}.
            </p>

            @if (session()->has('message'))
                <div x-data="{ show: true }"
                     x-init="setTimeout(() => show = false, 4000)"
                     :class="show ? '' : 'hidden'"
                     role="status"
                     aria-live="polite"
                     class="mt-6 flex items-start gap-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 p-3.5 text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="flex-1 font-medium text-emerald-800 dark:text-emerald-300 leading-relaxed">{{ session('message') }}</span>
                    <button type="button"
                            @click="show = false"
                            class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 rounded-md text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/10
                                   transition-all duration-200 active:scale-95 shrink-0
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                            aria-label="Dismiss message">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            @endif

            @if (session()->has('error'))
                <div x-data="{ show: true }"
                     x-init="setTimeout(() => show = false, 5000)"
                     :class="show ? '' : 'hidden'"
                     role="alert"
                     aria-live="polite"
                     class="mt-6 flex items-start gap-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 p-3.5 text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                    </svg>
                    <span class="flex-1 font-medium text-rose-800 dark:text-rose-300 leading-relaxed">{{ session('error') }}</span>
                    <button type="button"
                            @click="show = false"
                            class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                                   transition-all duration-200 active:scale-95 shrink-0
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                            aria-label="Dismiss error">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            @endif

            @if ($this->isApproved)
                <div data-reveal class="mt-8 rounded-2xl border border-emerald-200/70 dark:border-emerald-500/25 bg-emerald-50/40 dark:bg-emerald-500/[0.06] p-6">
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 w-11 h-11 rounded-xl flex items-center justify-center bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <h3 class="text-base font-semibold tracking-tight text-gray-900 dark:text-white">
                                Your business is approved
                            </h3>
                            <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                Your business account is live on the platform. Head to your dashboard to manage properties, services, bookings, and payments.
                            </p>
                        </div>
                    </div>

                    <a href="{{ route('tenant.dashboard') }}" wire:navigate
                       class="mt-6 w-full inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-sm
                              transition-all duration-200 active:scale-95
                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        Go to business dashboard
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>

            @elseif ($this->isSubmitted)
                <div data-reveal class="mt-8 rounded-2xl border border-amber-200/70 dark:border-amber-500/25 bg-amber-50/40 dark:bg-amber-500/[0.06] p-6">
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 w-11 h-11 rounded-xl flex items-center justify-center bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-500/30">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <h3 class="text-base font-semibold tracking-tight text-gray-900 dark:text-white">
                                Application under review
                            </h3>
                            <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                Our team is reviewing your submission. We'll notify you as soon as a decision is made.
                            </p>
                            @if ($this->application?->submitted_at)
                                <p class="mt-3 text-[11px] font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-400">
                                    Submitted {{ $this->application->submitted_at->diffForHumans() }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>

            @elseif ($this->isRejected)
                <div data-reveal class="mt-8 rounded-2xl border border-rose-200/70 dark:border-rose-500/25 bg-rose-50/40 dark:bg-rose-500/[0.06] p-6">
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 w-11 h-11 rounded-xl flex items-center justify-center bg-rose-100 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-500/30">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <h3 class="text-base font-semibold tracking-tight text-gray-900 dark:text-white">
                                Application not approved
                            </h3>
                            <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                Unfortunately, your previous application was rejected. You may start a new one with corrected information.
                            </p>
                        </div>
                    </div>

                    @if ($this->application?->rejection_reason)
                        <div class="mt-5 rounded-xl bg-white/60 dark:bg-rose-500/[0.08] border border-rose-100 dark:border-rose-500/20 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-rose-700 dark:text-rose-400 mb-1.5">
                                Reason from reviewer
                            </p>
                            <p class="text-sm text-rose-900 dark:text-rose-200 leading-relaxed">
                                {{ $this->application->rejection_reason }}
                            </p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register_business.start') }}" class="mt-6"
                          x-data="{ loading: false }" @submit="loading = true">
                        @csrf
                        <button type="submit"
                                :disabled="loading"
                                :aria-busy="loading"
                                class="w-full inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold shadow-sm
                                       transition-all duration-200 active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
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

            @elseif ($this->isEditable)
                <div data-reveal class="mt-8 rounded-2xl border border-primary-200/70 dark:border-primary-500/25 bg-primary-50/40 dark:bg-primary-500/[0.06] p-6">
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 w-11 h-11 rounded-xl flex items-center justify-center bg-primary-100 dark:bg-primary-500/20 text-primary-600 dark:text-primary-400 border border-primary-200 dark:border-primary-500/30">
                            @if ($this->state === BusinessApplication::STATUS_NEEDS_REVISION)
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                                </svg>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0 pt-0.5">
                            <h3 class="text-base font-semibold tracking-tight text-gray-900 dark:text-white">
                                @if ($this->state === BusinessApplication::STATUS_NEEDS_REVISION)
                                    Revision requested
                                @else
                                    Application in progress
                                @endif
                            </h3>
                            <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                @if ($this->state === BusinessApplication::STATUS_NEEDS_REVISION)
                                    Our reviewer requested changes to your application. Review the notes, update your details, and resubmit.
                                @else
                                    You have an unfinished KYB application. Pick up right where you left off.
                                @endif
                            </p>
                        </div>
                    </div>

                    @if ($this->state === BusinessApplication::STATUS_NEEDS_REVISION && $this->application?->revision_notes)
                        <div class="mt-5 rounded-xl bg-white/60 dark:bg-amber-500/[0.08] border border-amber-100 dark:border-amber-500/20 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400 mb-1.5">
                                Reviewer notes
                            </p>
                            <p class="text-sm text-amber-900 dark:text-amber-200 leading-relaxed">
                                {{ $this->application->revision_notes }}
                            </p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register_business.start') }}" class="mt-6"
                          x-data="{ loading: false }" @submit="loading = true">
                        @csrf
                        <button type="submit"
                                :disabled="loading"
                                :aria-busy="loading"
                                class="w-full inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                       transition-all duration-200 active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                       disabled:opacity-60 disabled:cursor-not-allowed">
                            <span :class="loading ? 'hidden' : 'inline-flex items-center gap-2'">
                                @if ($this->state === BusinessApplication::STATUS_NEEDS_REVISION)
                                    Review &amp; update application
                                @else
                                    Continue application
                                @endif
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

            @else
                <div data-reveal class="mt-8 rounded-2xl border border-gray-200/70 dark:border-gray-700/70 bg-white/60 dark:bg-gray-900/40 backdrop-blur-sm p-6 shadow-sm">

                    <p class="mb-5 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-primary-600 dark:text-primary-400">
                        <span class="h-px w-4 bg-amber-500" aria-hidden="true"></span>
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

                    <form method="POST" action="{{ route('register_business.start') }}" class="mt-6"
                          x-data="{ loading: false }" @submit="loading = true">
                        @csrf
                        <button type="submit"
                                :disabled="loading"
                                :aria-busy="loading"
                                class="w-full inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                       transition-all duration-200 active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
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
            @endif

            <p class="mt-8 text-center text-xs text-gray-400 dark:text-gray-500 leading-relaxed">
                By continuing, you agree to {{ $this->siteName }}'s terms and privacy policy.
            </p>
        </div>
    </section>
</main>