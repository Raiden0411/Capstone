<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.auth')]
#[Title('Login')]
class extends Component
{
    public string $email    = '';
    public string $password = '';
    public bool   $remember = false;

    public ?string $redirectTo = null;

    public function mount(): void
    {
        $this->redirectTo = $this->sanitizeRedirect(request()->query('redirect'));
    }

    protected function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function login()
    {
        $this->validate();

        $email       = strtolower(trim($this->email));
        $throttleKey = $email . '|' . request()->ip();

        // ── Rate limit check ──────────────────────────────────────
        // Runs BEFORE the bcrypt comparison, so a brute-forcer never
        // burns a CPU cycle on Auth::attempt() once they're throttled.
        //
        // Key: email + IP. Two users behind the same NAT don't share
        // a bucket because their emails differ. An attacker rotating
        // emails from one IP still hits the route-level IP throttle.
        // An attacker rotating IPs against one account: each IP has
        // its own bucket, but they'd need 5 clean IPs per minute to
        // sustain an attack — well outside the range of a casual
        // brute force.
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            $this->addError('email',
                "Too many login attempts. Please try again in {$seconds} seconds."
            );
            return null;
        }

        $credentials = [
            'email'    => $email,
            'password' => $this->password,
        ];

        if (!Auth::attempt($credentials, $this->remember)) {
            // Record the failure. The 60-second decay window means
            // the counter expires on its own; no cleanup needed.
            RateLimiter::hit($throttleKey, 60);

            $this->addError('email', 'The provided credentials do not match our records.');
            return null;
        }

        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();

            // Inactive accounts still count as a failed attempt —
            // otherwise an attacker could probe for valid-but-inactive
            // accounts without triggering the limiter.
            RateLimiter::hit($throttleKey, 60);

            $this->addError('email', 'Your account is not yet active. Please wait for approval.');
            return null;
        }

        // ── Success: clear the limiter ────────────────────────────
        // A legitimate user who finally typed the right password
        // shouldn't carry failed attempts into their next session.
        RateLimiter::clear($throttleKey);

        // Rotate the session ID after authentication — prevents session
        // fixation. Auth::attempt() has already written the user id into
        // the current session, so without this an attacker who planted a
        // known session cookie could ride the new authenticated session.
        session()->regenerate();

        // Honour the sanitized ?redirect= param first.
        if ($this->redirectTo) {
            return redirect()->to($this->redirectTo);
        }

        // Role-based fallback.
        if ($user->hasRole('super-admin')) {
            return redirect()->route('superadmin.dashboard');
        }

        if ($user->hasRole('admin')) {
            return redirect()->route('tenant.dashboard');
        }

        if ($user->tenant_id && $user->getAllPermissions()->count() > 0) {
            return redirect()->route('tenant.employee.dashboard');
        }

        return redirect()->route('home');
    }

    /**
     * Only allow same-origin absolute paths. Rejects:
     *   - External URLs        https://evil.com
     *   - Protocol-relative    //evil.com
     *   - Backslash trickery   /\evil.com  (older browsers treat \ as /)
     *   - Anything not starting with a single forward slash
     */
    protected function sanitizeRedirect(?string $url): ?string
    {
        if (!$url || !str_starts_with($url, '/')) {
            return null;
        }

        if (str_starts_with($url, '//') || str_starts_with($url, '/\\')) {
            return null;
        }

        return $url;
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

<div class="min-h-screen flex flex-col md:flex-row bg-white dark:bg-gray-900">

    {{-- Left Side: Hero Image & Text --}}
    <div class="relative w-full md:w-3/5 min-h-[220px] md:min-h-screen order-1 md:order-1">
        <img src="{{ $heroUrl }}"
             alt="{{ $siteName }}"
             class="absolute inset-0 object-cover w-full h-full">

        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>

        <div class="absolute inset-x-0 bottom-0 p-5 sm:p-8 md:p-16 lg:p-24 text-white">
            <span class="inline-flex items-center gap-2 px-3 py-1 text-[11px] font-bold tracking-widest text-white uppercase bg-black/40 rounded-full backdrop-blur-sm border border-white/10">
                <span class="w-1.5 h-1.5 bg-yellow-500 rounded-full"></span>
                {{ $siteName }}
            </span>

            <h1 class="mt-5 text-2xl sm:text-3xl md:text-5xl lg:text-[54px] font-extrabold tracking-tight leading-[1.1]">
                Your journey to the heart of the<br />wilderness starts here
            </h1>

            <p class="max-w-2xl mt-4 text-sm font-medium leading-relaxed text-gray-200 md:text-base">
                Discover the unmapped ecotrails, pristine waterfalls, and rich history of Victorias City.
                Let us show you a side of the world you've never seen.
            </p>
        </div>
    </div>

    {{-- Right Side: Login Form --}}
    <div class="flex items-center justify-center w-full px-4 sm:px-6 py-10 md:py-12 bg-white dark:bg-gray-900 md:w-2/5 lg:px-16 order-2 md:order-2">
        <div class="w-full max-w-md">

            {{-- Back to Home --}}
            <a href="{{ route('home') }}" wire:navigate
               class="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors mb-6 focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Back to Home
            </a>

            {{-- Brand / Logo --}}
            <div class="flex items-center gap-3 mb-6">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $siteName }} logo"
                         class="w-10 h-10 object-contain rounded-lg shrink-0">
                @else
                    <div class="w-10 h-10 rounded-xl bg-primary-600 flex items-center justify-center text-white shrink-0">
                        {{ strtoupper(substr($siteName, 0, 1)) }}
                    </div>
                @endif
                <span class="text-lg font-semibold text-gray-900 dark:text-white">{{ $siteName }}</span>
            </div>

            <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">
                Login to your account
            </h2>

            @if ($errors->any())
                <div class="mb-5 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 rounded-xl p-3 text-sm text-red-600 dark:text-red-400">
                    <ul class="space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form wire:submit="login" class="space-y-5"
                  x-data="{ showPassword: false }">

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Email Address
                    </label>
                    <div class="mt-1.5">
                        <input id="email"
                               type="email"
                               wire:model="email"
                               required
                               autofocus
                               autocomplete="username"
                               placeholder="example@email.com"
                               class="block w-full px-4 py-3 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500">
                    </div>
                    @error('email')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password --}}
                <div>
                    <div class="flex items-center justify-between">
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Password
                        </label>
                        <a href="#" wire:click.prevent
                           class="text-sm font-medium text-primary-600 hover:underline focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded"
                           title="Password reset is not yet available.">
                            Forgot?
                        </a>
                    </div>

                    <div class="relative mt-1.5">
                        <input :type="showPassword ? 'text' : 'password'"
                               id="password"
                               wire:model="password"
                               required
                               autocomplete="current-password"
                               placeholder="Enter your password"
                               class="block w-full px-4 py-3 pr-11 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500">

                        <button type="button" @click="showPassword = !showPassword"
                                class="absolute inset-y-0 flex items-center right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded-full"
                                tabindex="-1" aria-label="Toggle password visibility">
                            <svg x-show="!showPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg x-show="showPassword" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Remember me --}}
                <div class="flex items-center">
                    <label class="flex items-center text-sm text-gray-600 dark:text-gray-300 cursor-pointer">
                        <input type="checkbox"
                               id="remember"
                               wire:model="remember"
                               class="shrink-0 rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 bg-white dark:bg-gray-700">
                        <span class="ml-2">Remember me</span>
                    </label>
                </div>

                {{-- Submit --}}
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="w-full py-3.5 mt-2 text-[15px] font-semibold text-white transition-all duration-200 bg-primary-600 hover:bg-primary-700 rounded-xl shadow-sm disabled:opacity-50 disabled:cursor-not-allowed active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:outline-none">
                    <span wire:loading.remove>Login now</span>
                    <span wire:loading class="inline-flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Signing in…
                    </span>
                </button>
            </form>

            {{-- Sign Up Link (preserve redirect) --}}
            <p class="mt-8 text-sm font-medium text-center text-gray-500 dark:text-gray-400">
                Don't Have An Account?
                <a href="{{ route('register', $redirectTo ? ['redirect' => $redirectTo] : []) }}" wire:navigate
                   class="text-primary-600 hover:underline ml-1 focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                    Sign Up
                </a>
            </p>

            {{-- Business Registration (preserve redirect) --}}
            <p class="mt-2 text-sm font-medium text-center text-gray-500 dark:text-gray-400">
                Own a tourist spot?
                <a href="{{ route('register_business', $redirectTo ? ['redirect' => $redirectTo] : []) }}" wire:navigate
                   class="text-primary-600 hover:underline ml-1 focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                    Register your business
                </a>
            </p>

        </div>
    </div>

</div>