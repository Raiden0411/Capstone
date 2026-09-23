
<?php

use App\Models\SiteSetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
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

        if (Auth::check()) {
            $this->redirect(
                $this->redirectTo ?? $this->roleBasedFallback(),
                navigate: true,
            );
            return;
        }
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
            RateLimiter::hit($throttleKey, 60);

            $this->addError('email', 'The provided credentials do not match our records.');
            return null;
        }

        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();

            session()->invalidate();
            session()->regenerateToken();

            RateLimiter::hit($throttleKey, 60);

            $this->addError('email', 'Your account is not yet active. Please wait for approval.');
            return null;
        }

        RateLimiter::clear($throttleKey);

        session()->regenerate();

        if ($this->redirectTo) {
            return redirect()->to($this->redirectTo);
        }

        return redirect()->to($this->roleBasedFallback());
    }

    protected function roleBasedFallback(): string
    {
        $user = Auth::user();

        if (!$user) {
            return route('home');
        }

        if ($user->hasRole('super-admin')) {
            return route('superadmin.dashboard');
        }

        if ($user->hasRole('admin')) {
            return route('tenant.dashboard');
        }

        if ($user->tenant_id && $user->getAllPermissions()->count() > 0) {
            return route('tenant.employee.dashboard');
        }

        return route('home');
    }

    protected function sanitizeRedirect(?string $url): ?string
    {
        if (!$url || !str_starts_with($url, '/')) {
            return null;
        }

        if (str_starts_with($url, '//') || str_starts_with($url, '\\')) {
            return null;
        }

        return $url;
    }

    // ─────────────────────────────────────────────────────
    //  Branding & links — resolved once per render, cached
    //  by Livewire's #[Computed] memoization.
    // ─────────────────────────────────────────────────────

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

    /**
     * A real, active destination from the platform — shown as a
     * floating card in the hero on md+ screens.
     *
     * QUERY: One indexed query. Livewire's #[Computed] memoizes it
     * for the request. Tenant doesn't use BelongsToTenant (it's the
     * root of the tenant system), so no scope bypass needed.
     *
     * ORDER: Recommended first → verified second → alphabetical tie.
     *
     * Returns null if no tenant qualifies. The card only renders
     * when this is non-null.
     *
     * @return array{name: string, slug: string, type: string, logo: string, url: string}|null
     */
    #[Computed]
    public function featuredTenant(): ?array
    {
        $tenant = Tenant::query()
            ->where('is_active', true)
            ->whereNotNull('logo')
            ->whereNotNull('slug')
            ->with('typeOfTenant:id,type')
            ->orderByDesc('is_recommended')
            ->orderByDesc('verified_at')
            ->orderBy('name')
            ->first(['id', 'name', 'slug', 'logo', 'type_of_tenant_id']);

        if (!$tenant || !$tenant->logo) {
            return null;
        }

        return [
            'name' => (string) $tenant->name,
            'slug' => (string) $tenant->slug,
            'type' => (string) ($tenant->typeOfTenant?->type ?? 'Destination'),
            'logo' => asset('storage/' . $tenant->logo),
            'url'  => route('business.offerings', $tenant->slug),
        ];
    }

    #[Computed]
    public function businessSignupUrl(): string
    {
        return route('register', [
            'redirect' => $this->redirectTo ?: route('register_business'),
        ]);
    }
};
?>

<?php $__env->startPush('styles'); ?>
    <?php if (! $__env->hasRenderedOnce('77b65b47-886c-478e-a938-addb4835a776')): $__env->markAsRenderedOnce('77b65b47-886c-478e-a938-addb4835a776'); ?>
        <style>
            /* Rule 69 replacements — CSS keyframes for the business modal. */
            .login-modal-backdrop {
                animation: loginModalBackdropIn .2s ease-out;
            }
            @keyframes loginModalBackdropIn {
                from { opacity: 0 }
                to   { opacity: 1 }
            }
            .login-modal-panel {
                animation: loginModalPanelIn .25s cubic-bezier(.16,1,.3,1);
            }
            @keyframes loginModalPanelIn {
                from { opacity: 0; transform: translateY(16px) scale(.98); }
                to   { opacity: 1; transform: translateY(0) scale(1); }
            }
            @media (prefers-reduced-motion: reduce) {
                .login-modal-backdrop, .login-modal-panel { animation: none; }
            }

            /* Ambient glow on the form panel. */
            .login-form-panel {
                background-image:
                    radial-gradient(ellipse 70% 50% at 50% 0%, rgba(245,158,11,.05) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 60% at 100% 100%, rgba(59,130,246,.04) 0%, transparent 55%);
            }
            .dark .login-form-panel {
                background-image:
                    radial-gradient(ellipse 70% 50% at 50% 0%, rgba(245,158,11,.09) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 60% at 100% 100%, rgba(59,130,246,.07) 0%, transparent 55%);
            }

            /* Caps Lock warning slide-in. */
            .caps-warning {
                animation: capsWarningIn .18s ease-out;
            }
            @keyframes capsWarningIn {
                from { opacity: 0; transform: translateY(-4px); }
                to   { opacity: 1; transform: translateY(0); }
            }
            @media (prefers-reduced-motion: reduce) {
                .caps-warning { animation: none; }
            }

            /* Featured-spot card entrance. */
            .login-featured-card {
                animation: loginFeaturedIn .5s cubic-bezier(.16,1,.3,1) .25s both;
            }
            @keyframes loginFeaturedIn {
                from { opacity: 0; transform: translateY(-6px); }
                to   { opacity: 1; transform: translateY(0); }
            }
            @media (prefers-reduced-motion: reduce) {
                .login-featured-card { animation: none; }
            }
        </style>
    <?php endif; ?>
<?php $__env->stopPush(); ?>

<div class="min-h-screen w-full overflow-x-hidden flex flex-col md:flex-row bg-white dark:bg-gray-900"
     x-data="{ showBusinessNotice: false }"
     @keydown.escape.window="showBusinessNotice = false">

    
    
    <div class="relative w-full md:w-3/5 min-h-[240px] sm:min-h-[280px] md:min-h-screen order-1 md:order-1 overflow-hidden">
        <img src="<?php echo e($this->heroUrl); ?>"
             alt=""
             aria-hidden="true"
             loading="eager"
             fetchpriority="high"
             decoding="async"
             width="1600"
             height="900"
             class="absolute inset-0 object-cover w-full h-full">

        <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/45 to-black/15"></div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->featuredTenant): ?>
            <?php $ft = $this->featuredTenant; ?>
            <a href="<?php echo e($ft['url']); ?>"
               wire:navigate
               class="login-featured-card hidden md:flex absolute top-6 right-6 lg:top-10 lg:right-10
                      items-center gap-3 w-[280px]
                      rounded-2xl p-3 pr-3.5
                      bg-white/[0.08] hover:bg-white/[0.14] backdrop-blur-md
                      border border-white/15 hover:border-white/30
                      transition-all duration-300 active:scale-[0.97]
                      group
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/50">
                <img src="<?php echo e($ft['logo']); ?>"
                     alt=""
                     width="44" height="44"
                     loading="lazy" decoding="async"
                     class="w-11 h-11 rounded-xl object-cover bg-white/10 shrink-0">
                <div class="min-w-0 flex-1">
                    <p class="text-[9px] font-bold uppercase tracking-[0.18em] text-amber-300/90 mb-0.5">
                        Featured Spot
                    </p>
                    <p class="text-sm font-semibold text-white leading-tight truncate">
                        <?php echo e($ft['name']); ?>

                    </p>
                    <p class="text-[11px] text-white/55 truncate mt-0.5">
                        <?php echo e($ft['type']); ?>

                    </p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg"
                     class="w-4 h-4 text-white/50 group-hover:text-white group-hover:translate-x-0.5 transition-all shrink-0"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <div class="absolute inset-x-0 bottom-0 p-5 sm:p-8 md:p-16 lg:p-20 text-white">
            
            <span class="inline-flex max-w-full items-center gap-2 px-3 py-1 text-[11px] font-bold tracking-widest text-white uppercase bg-black/40 rounded-full backdrop-blur-sm border border-white/15">
                <span class="w-1.5 h-1.5 bg-amber-400 rounded-full shrink-0" aria-hidden="true"></span>
                <span class="truncate"><?php echo e($this->siteName); ?></span>
            </span>

            
            <p class="mt-6 text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.08] break-words text-white max-w-3xl">
                Your journey to the heart of the wilderness starts here
            </p>

            <p class="max-w-2xl mt-4 sm:mt-5 text-sm sm:text-base font-normal leading-relaxed text-gray-200/85">
                Discover the unmapped ecotrails, pristine waterfalls, and rich history of Victorias City.
                Let us show you a side of the world you've never seen.
            </p>
        </div>
    </div>

    
    <div class="login-form-panel flex items-center justify-center w-full min-w-0 px-4 sm:px-6 py-10 md:py-12 bg-white dark:bg-gray-900 md:w-2/5 lg:px-16 order-2 md:order-2">
        <div class="w-full max-w-md min-w-0">

            
            <a href="<?php echo e(route('home')); ?>" wire:navigate
               class="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-all duration-200 active:scale-95 mb-8 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Home
            </a>

            
            <div class="flex items-center gap-3 mb-8 min-w-0">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->logoUrl): ?>
                    <img src="<?php echo e($this->logoUrl); ?>" alt="<?php echo e($this->siteName); ?> logo"
                         width="40" height="40" decoding="async"
                         class="w-10 h-10 object-contain rounded-lg shrink-0">
                <?php else: ?>
                    <div class="w-10 h-10 rounded-xl bg-primary-600 flex items-center justify-center text-white shrink-0 font-bold text-lg"
                         aria-hidden="true">
                        <?php echo e(strtoupper(substr($this->siteName, 0, 1))); ?>

                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <span class="text-base font-semibold text-gray-900 dark:text-white truncate"><?php echo e($this->siteName); ?></span>
            </div>

            
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-2 break-words leading-tight tracking-tight">
                Login to your account
            </h1>

            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">
                Enter your email and password to continue.
            </p>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
                <div role="alert" aria-live="polite"
                     class="mb-5 flex items-start gap-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 border-l-4 border-l-rose-500 rounded-xl p-3.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <ul class="space-y-0.5 text-sm text-rose-700 dark:text-rose-300 min-w-0 break-words">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <li><?php echo e($error); ?></li>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <form wire:submit="login" class="space-y-5"
                  x-data="{ showPassword: false, capsLock: false }">

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Email Address
                    </label>
                    <input id="email"
                           type="email"
                           wire:model="email"
                           autofocus
                           autocomplete="username"
                           placeholder="example@email.com"
                           class="block w-full px-4 py-3 text-base sm:text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500 <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-400/60 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="mt-1.5 text-xs text-rose-500 break-words"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div>
                    <div class="flex items-center justify-between gap-3 mb-1.5">
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Password
                        </label>
                        <a href="<?php echo e(route('password.request')); ?>" wire:navigate
                           class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 hover:underline transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded shrink-0">
                            Forgot?
                        </a>
                    </div>

                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'"
                               id="password"
                               wire:model="password"
                               autocomplete="current-password"
                               placeholder="Enter your password"
                               @keyup="capsLock = $event.getModifierState && $event.getModifierState('CapsLock')"
                               @keydown="capsLock = $event.getModifierState && $event.getModifierState('CapsLock')"
                               @blur="capsLock = false"
                               class="block w-full px-4 py-3 pr-11 text-base sm:text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500 <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-400/60 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">

                        <button type="button" @click="showPassword = !showPassword"
                                class="absolute inset-y-0 flex items-center right-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded-full"
                                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                :aria-pressed="showPassword ? 'true' : 'false'">
                            <svg xmlns="http://www.w3.org/2000/svg" x-cloak :class="!showPassword ? '' : 'hidden'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg xmlns="http://www.w3.org/2000/svg" x-cloak :class="showPassword ? '' : 'hidden'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>

                    <p x-cloak
                       x-show="capsLock"
                       class="caps-warning mt-1.5 inline-flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400"
                       role="status"
                       aria-live="polite">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Caps Lock is on
                    </p>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="mt-1.5 text-xs text-rose-500 break-words"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="flex items-center pt-1">
                    <label class="flex items-center text-sm text-gray-600 dark:text-gray-300 cursor-pointer select-none">
                        <input type="checkbox"
                               id="remember"
                               wire:model="remember"
                               class="shrink-0 rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 bg-white dark:bg-gray-700">
                        <span class="ml-2">Remember me</span>
                    </label>
                </div>

                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="login"
                        class="w-full inline-flex items-center justify-center gap-2 h-11 mt-2 text-sm font-semibold text-white transition-all duration-200 bg-primary-600 hover:bg-primary-700 rounded-xl shadow-sm
                               disabled:opacity-50 disabled:cursor-not-allowed active:scale-95
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <span wire:loading.remove wire:target="login">Login now</span>
                    <span wire:loading wire:target="login" class="inline-flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin w-4 h-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Signing in…
                    </span>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-gray-100 dark:border-gray-800 space-y-2">
                <p class="text-sm text-center text-gray-600 dark:text-gray-400">
                    Don't have an account?
                    <a href="<?php echo e(route('register', $this->redirectTo ? ['redirect' => $this->redirectTo] : [])); ?>" wire:navigate
                       class="text-primary-600 dark:text-primary-400 font-medium hover:underline ml-1 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                        Sign up
                    </a>
                </p>

                <p class="text-sm text-center text-gray-600 dark:text-gray-400">
                    Own a tourist spot?
                    <button type="button"
                            @click="showBusinessNotice = true"
                            class="text-primary-600 dark:text-primary-400 font-medium hover:underline ml-1 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                        Register your business
                    </button>
                </p>
            </div>

            
            <div class="mt-10 pt-5 border-t border-gray-100 dark:border-gray-800
                        flex items-center justify-between gap-3
                        text-[11px] text-gray-400 dark:text-gray-600">
                <span class="truncate">© <?php echo e(date('Y')); ?> <?php echo e($this->siteName); ?></span>
                <a href="<?php echo e(route('home')); ?>" wire:navigate
                   class="inline-flex items-center gap-1 shrink-0
                          hover:text-primary-600 dark:hover:text-primary-400
                          transition-all duration-200 active:scale-95
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                    Continue browsing
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </a>
            </div>

        </div>
    </div>

    
    
    <div x-cloak
         :class="showBusinessNotice ? 'login-modal-backdrop flex' : 'hidden'"
         class="fixed inset-0 z-[1000] items-center justify-center p-4 sm:p-6
                bg-gray-900/60 backdrop-blur-sm"
         role="dialog"
         aria-modal="true"
         aria-labelledby="business-notice-title"
         @click.self="showBusinessNotice = false">

        <div x-cloak
             :class="showBusinessNotice ? 'login-modal-panel' : 'hidden'"
             class="relative w-full max-w-sm bg-white dark:bg-gray-800 rounded-3xl
                    shadow-2xl border border-gray-200/80 dark:border-gray-700/80
                    overflow-hidden"
             @click.stop>

            <button type="button"
                    @click="showBusinessNotice = false"
                    aria-label="Close"
                    class="absolute top-3.5 right-3.5 z-10 w-8 h-8 rounded-full
                           flex items-center justify-center
                           text-gray-400 hover:text-gray-700 dark:hover:text-gray-200
                           hover:bg-gray-100 dark:hover:bg-gray-700
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                     stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"
                     aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            <div class="p-6 sm:p-7">

                <div class="flex items-center justify-center w-12 h-12 rounded-2xl
                            bg-gradient-to-b from-blue-50 to-blue-100/60
                            dark:from-blue-500/15 dark:to-blue-500/[0.08]
                            border border-blue-200/70 dark:border-blue-500/25
                            text-blue-600 dark:text-blue-400
                            shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                         stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                         aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>

                <h3 id="business-notice-title"
                    class="mt-4 text-lg font-semibold text-gray-900 dark:text-white leading-snug break-words">
                    Create a tourist account first
                </h3>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                    You'll need one to register your business. It takes less than a minute.
                </p>

                <div class="mt-6 flex flex-col sm:flex-row sm:items-center gap-2.5">
                    <a href="<?php echo e($this->businessSignupUrl); ?>"
                       wire:navigate
                       class="flex-1 min-w-0 inline-flex items-center justify-center gap-1.5 h-11 rounded-xl
                              bg-primary-600 hover:bg-primary-700
                              px-4 text-sm font-semibold text-white whitespace-nowrap
                              shadow-sm shadow-primary-600/20
                              transition-all duration-200 active:scale-95
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2
                              dark:focus-visible:ring-offset-gray-800">
                        Create a Tourist Account
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none"
                             stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"
                             aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </a>
                    <button type="button"
                            @click="showBusinessNotice = false"
                            class="sm:w-auto inline-flex items-center justify-center h-11 rounded-xl
                                   px-4 text-sm font-semibold whitespace-nowrap
                                   text-gray-600 dark:text-gray-300
                                   border border-gray-200 dark:border-gray-700
                                   hover:bg-gray-50 dark:hover:bg-gray-700/50
                                   transition-all duration-200 active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        Maybe later
                    </button>
                </div>

            </div>
        </div>
    </div>

</div><?php /**PATH C:\laragon\www\Capstone\resources\views\public\auth\⚡login-form.blade.php ENDPATH**/ ?>