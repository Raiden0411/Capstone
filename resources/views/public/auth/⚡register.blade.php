{{-- resources/views/public/auth/⚡register.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\User;

new 
#[Title('Create Tourist Account')]
class extends Component {

    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $phone = '';

    public ?string $redirectTo = null;

    public function mount()
    {
        $this->redirectTo = request()->query('redirect');
    }

    protected function rules()
    {
        return [
            'name'     => ['required', 'string', 'min:3', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone'    => ['nullable', 'string', 'max:20', 'regex:/^(09|\+639)\d{9}$/'],
        ];
    }

    public function messages()
    {
        return [
            'phone.regex' => 'Use a valid PH number: 09xxxxxxxxx or +639xxxxxxxxx.',
        ];
    }

    public function register()
    {
        $this->validate();

        $user = User::create([
            'name'      => $this->name,
            'email'     => $this->email,
            'password'  => Hash::make($this->password),
            'phone'     => $this->phone,
            'tenant_id' => null,
            'is_active' => true,
        ]);

        $user->assignRole('tourist');

        auth()->login($user);

        $message = 'Account created successfully! Welcome to Victorias Tourism.';

        if ($this->redirectTo) {
            return redirect()->to($this->redirectTo)->with('message', $message);
        }

        return redirect()->route('home')->with('message', $message);
    }
};
?>

@php
    $siteName = \App\Models\SiteSetting::getValue('site_name', config('app.name'));
    $logoPath = \App\Models\SiteSetting::getValue('site_logo');
    $logoUrl = $logoPath ? asset('storage/' . $logoPath) : null;

    $heroPath = \App\Models\SiteSetting::getValue('hero_background_image');
    $heroUrl = $heroPath
        ? asset('storage/' . $heroPath)
        : 'https://images.unsplash.com/photo-1506748686214-e9df14d4d9d0?auto=format&fit=crop&w=1600&q=80';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Register - {{ $siteName }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    {{-- Dark mode flash prevention --}}
    <script>
        !function() {
            var t = localStorage.getItem('hs_theme');
            var dark = t === 'dark' || (t !== 'light' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        }();
    </script>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600&display=swap" rel="stylesheet">
</head>
<body class="font-sans antialiased text-gray-900 bg-[#F8F7F3] dark:bg-gray-900 dark:text-white min-h-screen">

    <main class="flex flex-col md:flex-row w-full min-h-screen">

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

        {{-- Right Side: Registration Form --}}
        <div class="flex items-center justify-center w-full px-4 sm:px-6 py-10 md:py-12 bg-white dark:bg-gray-900 md:w-2/5 lg:px-16 order-2 md:order-2">
            <div class="w-full max-w-md">

                {{-- Back to Home --}}
                <a href="{{ route('home') }}"
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
                    Create your account
                </h2>

                @if ($errors->any())
                    <div class="mb-5 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 rounded-xl p-3 text-sm text-red-600 dark:text-red-400">
                        Please fix the errors below.
                    </div>
                @endif

                <form wire:submit="register" class="space-y-5"
                      x-data="{ showPassword: false, showConfirmPassword: false }">

                    {{-- Full Name --}}
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Full Name *
                        </label>
                        <div class="mt-1.5">
                            <input id="name"
                                   type="text"
                                   wire:model="name"
                                   required
                                   autofocus
                                   autocomplete="name"
                                   placeholder="Juan Dela Cruz"
                                   class="block w-full px-4 py-3 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500">
                        </div>
                        @error('name')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Email Address *
                        </label>
                        <div class="mt-1.5">
                            <input id="email"
                                   type="email"
                                   wire:model="email"
                                   required
                                   autocomplete="email"
                                   placeholder="example@email.com"
                                   class="block w-full px-4 py-3 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500">
                        </div>
                        @error('email')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Phone --}}
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Phone (optional)
                        </label>
                        <div class="mt-1.5">
                            <input id="phone"
                                   type="text"
                                   wire:model="phone"
                                   autocomplete="tel"
                                   placeholder="09123456789"
                                   class="block w-full px-4 py-3 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500">
                        </div>
                        @error('phone')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Password --}}
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Password *
                        </label>
                        <div class="relative mt-1.5">
                            <input :type="showPassword ? 'text' : 'password'"
                                   id="password"
                                   wire:model="password"
                                   required
                                   autocomplete="new-password"
                                   minlength="8"
                                   placeholder="••••••••"
                                   class="block w-full px-4 py-3 pr-11 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500">

                            <button type="button" @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 flex items-center right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
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

                    {{-- Confirm Password --}}
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Confirm Password *
                        </label>
                        <div class="relative mt-1.5">
                            <input :type="showConfirmPassword ? 'text' : 'password'"
                                   id="password_confirmation"
                                   wire:model="password_confirmation"
                                   required
                                   autocomplete="new-password"
                                   minlength="8"
                                   placeholder="••••••••"
                                   class="block w-full px-4 py-3 pr-11 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500">

                            <button type="button" @click="showConfirmPassword = !showConfirmPassword"
                                    class="absolute inset-y-0 flex items-center right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                    tabindex="-1" aria-label="Toggle confirm password visibility">
                                <svg x-show="!showConfirmPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg x-show="showConfirmPassword" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                        @error('password_confirmation')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Submit Button --}}
                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="w-full py-3.5 mt-2 text-[15px] font-semibold text-white transition-colors bg-primary-600 hover:bg-primary-700 rounded-xl shadow-sm disabled:opacity-50 disabled:cursor-not-allowed focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <span wire:loading.remove>Create Account</span>
                        <span wire:loading class="inline-flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Creating...
                        </span>
                    </button>
                </form>

                {{-- Sign In Link (preserve redirect) --}}
                <p class="mt-8 text-sm font-medium text-center text-gray-500 dark:text-gray-400">
                    Already Have An Account?
                    <a href="{{ route('login', $redirectTo ? ['redirect' => $redirectTo] : []) }}"
                       class="text-primary-600 hover:underline ml-1 focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                        Sign In
                    </a>
                </p>

                {{-- Business Registration (preserve redirect) --}}
                <p class="mt-2 text-sm font-medium text-center text-gray-500 dark:text-gray-400">
                    Own a tourist spot?
                    <a href="{{ route('register_business', $redirectTo ? ['redirect' => $redirectTo] : []) }}"
                       class="text-primary-600 hover:underline ml-1 focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                        Register your business
                    </a>
                </p>

            </div>
        </div>
    </main>

    @livewireScripts
</body>
</html>