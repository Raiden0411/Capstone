<?php

use App\Models\BusinessApplication;
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
     * The user's most recent KYB application, if any.
     */
    #[Computed]
    public function application(): ?BusinessApplication
    {
        return Auth::user()
            ?->businessApplications()
            ->latest('updated_at')
            ->first();
    }

    /**
     * Current state key for the panel switch.
     */
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
};
?>

@php
    $siteName = \App\Models\SiteSetting::getValue('site_name', config('app.name'));
    $logoPath = \App\Models\SiteSetting::getValue('site_logo');
    $logoUrl  = $logoPath ? asset('storage/' . $logoPath) : null;

    $heroPath = \App\Models\SiteSetting::getValue('hero_background_image');
    $heroUrl  = $heroPath
        ? asset('storage/' . $heroPath)
        : 'https://images.unsplash.com/photo-1506748686214-e9df14d4d9d0?auto=format&fit=crop&w=1600&q=80';
@endphp

<main class="flex flex-col md:flex-row w-full min-h-screen">

    {{-- Left: Hero --}}
    <div class="relative w-full md:w-3/5 min-h-[220px] md:min-h-screen order-1 md:order-1">
        <img src="{{ $heroUrl }}"
             alt="{{ $siteName }}"
             class="absolute inset-0 object-cover w-full h-full">

        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/25 to-transparent"></div>

        <div class="absolute inset-x-0 bottom-0 p-5 sm:p-8 md:p-16 lg:p-24 text-white">
            <span class="inline-flex items-center gap-2 px-3 py-1 text-[11px] font-bold tracking-widest text-white uppercase bg-black/40 rounded-full backdrop-blur-sm border border-white/10">
                <span class="w-1.5 h-1.5 bg-yellow-500 rounded-full"></span>
                {{ $siteName }}
            </span>

            <h1 class="mt-5 text-2xl sm:text-3xl md:text-5xl lg:text-[54px] font-extrabold tracking-tight leading-[1.1]">
                Register your business &<br />welcome the world
            </h1>

            <p class="max-w-2xl mt-4 text-sm font-medium leading-relaxed text-gray-200 md:text-base">
                Put your resort, inn, eco-park, or restaurant on the map.
                Reach more visitors and share the best of Victorias City.
            </p>
        </div>
    </div>

    {{-- Right: State-aware panel --}}
    <div class="flex items-center justify-center w-full px-4 sm:px-6 py-10 md:py-12 bg-white dark:bg-gray-900 md:w-2/5 lg:px-16 order-2 md:order-2 overflow-y-auto">
        <div class="w-full max-w-md">

            {{-- Back to Home --}}
            <a href="{{ route('home') }}" wire:navigate
               class="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors mb-6 rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Home
            </a>

            {{-- Brand --}}
            <div class="flex items-center gap-3 mb-6">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $siteName }} logo"
                         class="w-10 h-10 object-contain rounded-lg shrink-0">
                @else
                    <div class="w-10 h-10 rounded-xl bg-primary-600 flex items-center justify-center text-white shrink-0 font-semibold">
                        {{ strtoupper(substr($siteName, 0, 1)) }}
                    </div>
                @endif
                <span class="text-lg font-semibold text-gray-900 dark:text-white">{{ $siteName }}</span>
            </div>

            <h2 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white mb-2">
                Register Your Business
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                Complete our verification process to list your business on {{ $siteName }}.
            </p>

            {{-- Flash messages --}}
            @if (session()->has('message'))
                <div class="mb-5 flex items-center gap-2.5 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ session('message') }}</span>
                </div>
            @endif

            @if (session()->has('error'))
                <div class="mb-5 flex items-center gap-2.5 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
                    <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            {{-- ──────────────────────────────────────────────────────────
                 STATE-AWARE PANEL
                 ────────────────────────────────────────────────────────── --}}

            @if ($this->isApproved)
                {{-- ✅ Already approved --}}
                <div class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl p-6 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-emerald-50 dark:bg-emerald-500/10 rounded-xl text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/50 shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                Your business is approved
                            </h3>
                            <p class="mt-1 text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                                Your business account is live on the platform. Head to your dashboard to manage properties, services, bookings, and payments.
                            </p>
                        </div>
                    </div>

                    <a href="{{ route('tenant.dashboard') }}" wire:navigate
                       class="mt-5 inline-flex w-full items-center justify-center gap-2 px-4 py-3 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        Go to Business Dashboard
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>

            @elseif ($this->isSubmitted)
                {{-- ⏳ Under review --}}
                <div class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl p-6 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-amber-50 dark:bg-amber-500/10 rounded-xl text-amber-600 dark:text-amber-400 border border-amber-100 dark:border-amber-900/50 shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                Application under review
                            </h3>
                            <p class="mt-1 text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                                Our team is reviewing your submission. We'll notify you as soon as a decision is made.
                            </p>
                            @if ($this->application?->submitted_at)
                                <p class="mt-3 text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Submitted {{ $this->application->submitted_at->diffForHumans() }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>

            @elseif ($this->isRejected)
                {{-- ❌ Rejected --}}
                <div class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl p-6 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-rose-50 dark:bg-rose-500/10 rounded-xl text-rose-600 dark:text-rose-400 border border-rose-100 dark:border-rose-900/50 shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                Application not approved
                            </h3>
                            <p class="mt-1 text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                                Unfortunately, your previous application was rejected. You may start a new one with corrected information.
                            </p>

                            @if ($this->application?->rejection_reason)
                                <div class="mt-4 rounded-xl bg-rose-50/60 dark:bg-rose-500/5 border border-rose-100 dark:border-rose-500/20 p-3">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 mb-1">
                                        Reason from reviewer
                                    </p>
                                    <p class="text-xs text-rose-800 dark:text-rose-300 leading-relaxed">
                                        {{ $this->application->rejection_reason }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <form method="POST" action="{{ route('register_business.start') }}" class="mt-5">
                        @csrf
                        <button type="submit"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            Start New Application
                        </button>
                    </form>
                </div>

            @elseif ($this->isEditable)
                {{-- ✏️ Draft or needs-revision --}}
                <div class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl p-6 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-primary-50 dark:bg-primary-950/50 rounded-xl text-primary-600 dark:text-primary-400 border border-primary-100 dark:border-primary-900/50 shrink-0">
                            @if ($this->state === BusinessApplication::STATUS_NEEDS_REVISION)
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                                </svg>
                            @else
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                @if ($this->state === BusinessApplication::STATUS_NEEDS_REVISION)
                                    Revision requested
                                @else
                                    Application in progress
                                @endif
                            </h3>
                            <p class="mt-1 text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                                @if ($this->state === BusinessApplication::STATUS_NEEDS_REVISION)
                                    Our reviewer requested changes to your application. Review the notes, update your details, and resubmit.
                                @else
                                    You have an unfinished KYB application. Pick up right where you left off.
                                @endif
                            </p>

                            @if ($this->state === BusinessApplication::STATUS_NEEDS_REVISION && $this->application?->revision_notes)
                                <div class="mt-4 rounded-xl bg-amber-50/60 dark:bg-amber-500/5 border border-amber-100 dark:border-amber-500/20 p-3">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 mb-1">
                                        Reviewer notes
                                    </p>
                                    <p class="text-xs text-amber-900 dark:text-amber-300 leading-relaxed">
                                        {{ $this->application->revision_notes }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <form method="POST" action="{{ route('register_business.start') }}" class="mt-5">
                        @csrf
                        <button type="submit"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            @if ($this->state === BusinessApplication::STATUS_NEEDS_REVISION)
                                Review &amp; Update Application
                            @else
                                Continue Application
                            @endif
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </form>
                </div>

            @else
                {{-- 🆕 No application yet --}}
                <div class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            What to expect
                        </span>
                    </div>

                    <ol class="space-y-4">
                        <li class="flex items-start gap-3">
                            <span class="shrink-0 w-6 h-6 rounded-full bg-primary-50 dark:bg-primary-950/50 text-primary-600 dark:text-primary-400 text-[11px] font-bold flex items-center justify-center border border-primary-100 dark:border-primary-900/50">1</span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Business details</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Name, type, address, and ownership information.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="shrink-0 w-6 h-6 rounded-full bg-primary-50 dark:bg-primary-950/50 text-primary-600 dark:text-primary-400 text-[11px] font-bold flex items-center justify-center border border-primary-100 dark:border-primary-900/50">2</span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Upload documents</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">DTI/SEC/CDA, BIR 2303, Mayor's Permit, and valid owner ID.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="shrink-0 w-6 h-6 rounded-full bg-primary-50 dark:bg-primary-950/50 text-primary-600 dark:text-primary-400 text-[11px] font-bold flex items-center justify-center border border-primary-100 dark:border-primary-900/50">3</span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Get verified</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Our team reviews your submission and activates your business.</p>
                            </div>
                        </li>
                    </ol>

                    <form method="POST" action="{{ route('register_business.start') }}" class="mt-6">
                        @csrf
                        <button type="submit"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            Start Application
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </button>
                    </form>
                </div>
            @endif

            <p class="mt-6 text-center text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                By continuing, you agree to {{ $siteName }}'s terms and privacy policy.
            </p>
        </div>
    </div>
</main>