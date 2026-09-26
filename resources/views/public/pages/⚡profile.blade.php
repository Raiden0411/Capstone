{{-- resources/views/public/pages/⚡profile.blade.php --}}
<?php

use App\Models\User;
use App\Traits\HandlesImageUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Layout('layouts.app')]
#[Title('My Profile')]
class extends Component
{
    use WithFileUploads;
    use HandlesImageUploads;

    public string $name  = '';
    public string $email = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $avatar = null;

    public ?string $currentAvatar = null;

    public string $current_password = '';
    public string $new_password = '';
    public string $new_password_confirmation = '';

    public function mount(): void
    {
        abort_unless(Auth::check(), 403);

        $user = Auth::user();
        $this->name          = (string) $user->name;
        $this->email         = (string) $user->email;
        $this->currentAvatar = $user->avatar;
    }

    public function hydrate(): void
    {
        abort_unless(Auth::check(), 403, 'Your session has expired. Please sign in again.');
    }

    protected function requireAuth(): void
    {
        abort_unless(Auth::check(), 403, 'Your session has expired. Please sign in again.');
    }

    #[Computed]
    public function viewer(): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user;
    }

    #[Computed]
    public function currentAvatarUrl(): ?string
    {
        return $this->avatarUrl($this->currentAvatar);
    }

    #[Computed]
    public function canRequestDeletion(): bool
    {
        $viewer = $this->viewer;

        return $viewer !== null && ! $viewer->hasRole('super-admin');
    }

    #[Computed]
    public function isBusinessOwner(): bool
    {
        return $this->viewer?->isBusinessOwner() ?? false;
    }

    #[Computed]
    public function userInitial(): string
    {
        $name = (string) ($this->viewer?->name ?? 'U');

        return strtoupper(substr($name, 0, 1));
    }

    protected function rules(): array
    {
        return [
            'name'  => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore(Auth::id())],
            'avatar'           => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'current_password' => ['nullable', 'required_with:new_password', 'current_password'],
            'new_password'     => ['nullable|min:8|confirmed'],
        ];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['name', 'email'], true)) {
            $this->$property = trim((string) $this->$property);
        }
    }

    public function removeAvatar(): void
    {
        $this->requireAuth();

        $user = $this->viewer;
        if (! $user) {
            return;
        }

        $oldPath = $user->avatar;

        $user->update(['avatar' => null]);

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $this->currentAvatar = null;
        $this->avatar        = null;

        unset($this->currentAvatarUrl, $this->userInitial);

        $this->dispatch('avatar-cleared');

        session()->flash('message', 'Profile photo removed.');
        $this->dispatch('scroll-to-top');
    }

    public function updateProfile(): void
    {
        $this->requireAuth();

        $this->validate();

        $user = $this->viewer;
        if (! $user) {
            return;
        }

        $data = [
            'name'  => $this->name,
            'email' => $this->email,
        ];

        $newAvatarPath = null;
        $oldAvatarPath = $user->avatar;

        if ($this->avatar) {
            try {
                $newAvatarPath = $this->storeImage($this->avatar, 'avatars', 'public', 'avatars');

                if (! $newAvatarPath) {
                    throw new \RuntimeException('Avatar store returned no path.');
                }

                $data['avatar'] = $newAvatarPath;
            } catch (\Throwable $e) {
                Log::error('Profile avatar store failed', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);

                session()->flash('error', 'Failed to upload the new photo. Please try again.');
                $this->dispatch('scroll-to-top');
                return;
            }
        }

        try {
            if ($this->new_password) {
                $data['password'] = Hash::make($this->new_password);
            }

            $user->update($data);
        } catch (\Throwable $e) {
            if ($newAvatarPath && Storage::disk('public')->exists($newAvatarPath)) {
                Storage::disk('public')->delete($newAvatarPath);
            }

            Log::error('Profile update failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            session()->flash('error', 'Failed to update your profile. Please try again.');
            $this->dispatch('scroll-to-top');
            return;
        }

        if ($newAvatarPath && $oldAvatarPath && Storage::disk('public')->exists($oldAvatarPath)) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        $this->currentAvatar = $newAvatarPath ?? $oldAvatarPath;
        $this->avatar        = null;
        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);

        unset($this->currentAvatarUrl, $this->userInitial, $this->viewer);

        $this->dispatch('avatar-cleared');

        session()->flash('message', 'Profile updated successfully.');
        $this->dispatch('scroll-to-top');
    }

    public function avatarUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return asset('storage/' . $path);
    }
};
?>

<div x-data="revealOnScroll"
     class="relative z-10 min-h-screen py-10 md:py-12 px-4 sm:px-6 lg:px-8 bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">

    <div
        x-data="{ toasts: [] }"
        x-on:toast.window="
            const id = Date.now() + Math.random();
            toasts.push({ id, message: $event.detail.message, type: $event.detail.type || 'info' });
            setTimeout(() => { toasts = toasts.filter(t => t.id !== id) }, 3500);
        "
        x-on:scroll-to-top.window="window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' })"
        class="max-w-2xl mx-auto space-y-6"
    >

        {{-- ═══════════════ FLASH ═══════════════ --}}
        @if(session()->has('message'))
            <div role="status" aria-live="polite"
                 class="flex items-start gap-3 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 border-l-4 border-l-emerald-500 p-4 rounded-xl">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <p class="text-sm text-emerald-700 dark:text-emerald-300 font-medium">{{ session('message') }}</p>
            </div>
        @endif
        @if(session()->has('error'))
            <div role="alert" aria-live="polite"
                 class="flex items-start gap-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 border-l-4 border-l-rose-500 p-4 rounded-xl">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-rose-700 dark:text-rose-300 font-medium">{{ session('error') }}</p>
            </div>
        @endif

        {{-- ═══════════════ HEADER ═══════════════ --}}
        <div data-reveal class="flex items-center justify-between gap-4">
            <div>
                <p class="mb-2 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-amber-600 dark:text-amber-400">
                    <span class="h-px w-4 bg-amber-500" aria-hidden="true"></span>
                    Account
                </p>
                <h1 class="font-display text-3xl font-bold tracking-tight text-gray-900 dark:text-white">My Profile</h1>
            </div>
            <a href="{{ route('home') }}" wire:navigate
               class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors flex items-center gap-1 active:scale-95
                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Home
            </a>
        </div>

        <form wire:submit="updateProfile" class="space-y-6">

            {{-- ═══════════════ PROFILE CARD ═══════════════ --}}
            <section data-reveal style="--reveal-delay: 80ms"
                     class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">

                {{-- ── Photo ── --}}
                <div class="p-6 border-b border-gray-100 dark:border-gray-700/60">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-5">
                        Photo
                    </p>

                    <div
                        x-data="avatarPreview()"
                        x-on:avatar-preview.window="setUrl($event.detail.url)"
                        x-on:avatar-cleared.window="clear()"
                        class="flex flex-col sm:flex-row items-center gap-6"
                    >
                        <div class="relative shrink-0 w-24 h-24">
                            <img
                                :src="previewUrl || '{{ $this->currentAvatarUrl ?? '' }}'"
                                :class="(previewUrl || {{ $this->currentAvatarUrl ? 'true' : 'false' }}) ? 'block' : 'hidden'"
                                class="w-24 h-24 rounded-full object-cover border-2 border-primary-500 shadow-md"
                                alt="Profile photo"
                                loading="eager"
                                decoding="async"
                            >

                            <div
                                :class="(previewUrl || {{ $this->currentAvatarUrl ? 'true' : 'false' }}) ? 'hidden' : 'flex'"
                                class="w-24 h-24 rounded-full bg-primary-50 dark:bg-primary-900/30 items-center justify-center text-primary-700 dark:text-primary-400 text-3xl font-bold border-2 border-primary-200 dark:border-primary-500/30"
                            >
                                {{ $this->userInitial }}
                            </div>

                            <div
                                wire:loading.flex
                                wire:target="avatar"
                                class="absolute inset-0 rounded-full bg-black/55 backdrop-blur-[2px] items-center justify-center z-10 pointer-events-none"
                                aria-hidden="true"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-white animate-spin motion-reduce:animate-none" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>

                            <div
                                x-data="imageCropper({
                                    wireProperty: 'avatar',
                                    aspect: 1,
                                    title: 'Crop profile photo',
                                    description: 'Square crop works best',
                                    previewEvent: 'avatar-preview',
                                })"
                                x-init="init()"
                                class="absolute bottom-0 right-0 z-20"
                            >
                                <label class="block bg-primary-600 hover:bg-primary-500 text-white rounded-full p-2.5 cursor-pointer shadow-lg transition-colors
                                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                              focus-within:ring-2 focus-within:ring-primary-600/50 active:scale-95"
                                       aria-label="Upload profile photo">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <input type="file"
                                           x-ref="input"
                                           x-on:change="pick($event)"
                                           accept="image/jpeg,image/png,image/webp"
                                           class="hidden">
                                </label>
                            </div>
                        </div>

                        <div class="flex-1 text-center sm:text-left min-w-0">
                            <h2 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-white truncate">{{ $this->viewer?->name }}</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $this->viewer?->email }}</p>

                            @if($avatar)
                                <p class="mt-2 text-[11px] font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                                    New photo selected — click Save Changes to apply.
                                </p>
                            @endif

                            @if($this->currentAvatarUrl)
                                <button type="button"
                                        wire:click="removeAvatar"
                                        class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:text-rose-700 dark:hover:text-rose-300 transition-colors active:scale-95
                                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 rounded px-1 py-0.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    Remove photo
                                </button>
                            @endif

                            @error('avatar')
                                <span class="text-rose-600 dark:text-rose-400 text-xs mt-2 block">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- ── Personal information ── --}}
                <div class="p-6">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-5">
                        Personal Information
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="field-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Full Name</label>
                            <input type="text" id="field-name" wire:model="name" autocomplete="name"
                                   class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 px-4 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                            @error('name') <span class="text-rose-600 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="field-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Email Address</label>
                            <input type="email" id="field-email" wire:model="email" autocomplete="email" inputmode="email"
                                   class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 px-4 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                            @error('email') <span class="text-rose-600 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </section>

            {{-- ═══════════════ PASSWORD CARD ═══════════════ --}}
            <section data-reveal style="--reveal-delay: 140ms"
                     x-data="{
                         pw: '',
                         showCurrent: false,
                         showNew: false,
                         showConfirm: false,
                         capsCurrent: false,
                         capsNew: false,
                         capsConfirm: false,

                         get strengthScore() {
                             const p = this.pw;
                             if (!p) return 0;
                             let s = 0;
                             if (p.length >= 8) s++;
                             if (p.length >= 12) s++;
                             if (/[a-z]/.test(p)) s++;
                             if (/[A-Z]/.test(p)) s++;
                             if (/[0-9]/.test(p)) s++;
                             if (/[^a-zA-Z0-9]/.test(p)) s++;
                             if (s <= 2) return 1;
                             if (s <= 4) return 2;
                             if (s <= 5) return 3;
                             return 4;
                         },
                         get strengthLabel() {
                             return ['', 'Weak', 'Fair', 'Good', 'Strong'][this.strengthScore] || '';
                         },
                         get strengthTextClass() {
                             return [
                                 '',
                                 'text-rose-600 dark:text-rose-400',
                                 'text-amber-600 dark:text-amber-400',
                                 'text-blue-600 dark:text-blue-400',
                                 'text-emerald-600 dark:text-emerald-400',
                             ][this.strengthScore] || '';
                         },
                         get passwordsMatch() {
                             return this.pw.length > 0 && this.pw === this.$wire.new_password_confirmation;
                         }
                     }"
                     class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm p-6">

                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">
                    Change Password
                </p>

                <p class="text-xs text-gray-500 dark:text-gray-400 mb-5">
                    Leave blank to keep your current password.
                </p>

                <div class="space-y-4">

                    {{-- Current password --}}
                    <div>
                        <label for="field-current-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Current Password</label>
                        <div class="relative">
                            <input :type="showCurrent ? 'text' : 'password'"
                                   id="field-current-password"
                                   wire:model="current_password"
                                   autocomplete="current-password"
                                   @keyup="capsCurrent = $event.getModifierState && $event.getModifierState('CapsLock')"
                                   @keydown="capsCurrent = $event.getModifierState && $event.getModifierState('CapsLock')"
                                   @blur="capsCurrent = false"
                                   class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 pl-4 pr-11 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                            <button type="button"
                                    @click="showCurrent = !showCurrent"
                                    :aria-label="showCurrent ? 'Hide password' : 'Show password'"
                                    :aria-pressed="showCurrent ? 'true' : 'false'"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800
                                           transition-colors
                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                <svg x-show="!showCurrent" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg x-show="showCurrent" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                        <p x-cloak x-show="capsCurrent" role="status" aria-live="polite"
                           class="mt-1.5 inline-flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                            Caps Lock is on
                        </p>
                        @error('current_password') <span class="text-rose-600 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                        {{-- New password --}}
                        <div>
                            <label for="field-new-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">New Password</label>
                            <div class="relative">
                                <input :type="showNew ? 'text' : 'password'"
                                       id="field-new-password"
                                       wire:model="new_password"
                                       x-on:input="pw = $event.target.value"
                                       autocomplete="new-password"
                                       minlength="8"
                                       @keyup="capsNew = $event.getModifierState && $event.getModifierState('CapsLock')"
                                       @keydown="capsNew = $event.getModifierState && $event.getModifierState('CapsLock')"
                                       @blur="capsNew = false"
                                       class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 pl-4 pr-11 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                                <button type="button"
                                        @click="showNew = !showNew"
                                        :aria-label="showNew ? 'Hide password' : 'Show password'"
                                        :aria-pressed="showNew ? 'true' : 'false'"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800
                                               transition-colors
                                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                    <svg x-show="!showNew" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <svg x-show="showNew" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                    </svg>
                                </button>
                            </div>

                            {{-- Strength meter --}}
                            <div x-show="pw.length > 0" x-cloak class="mt-2">
                                <div class="flex gap-1" aria-hidden="true">
                                    <div class="h-1 flex-1 rounded-full transition-colors duration-200"
                                         :class="strengthScore >= 1 ? 'bg-rose-500' : 'bg-gray-200 dark:bg-gray-700'"></div>
                                    <div class="h-1 flex-1 rounded-full transition-colors duration-200"
                                         :class="strengthScore >= 2 ? 'bg-amber-500' : 'bg-gray-200 dark:bg-gray-700'"></div>
                                    <div class="h-1 flex-1 rounded-full transition-colors duration-200"
                                         :class="strengthScore >= 3 ? 'bg-blue-500' : 'bg-gray-200 dark:bg-gray-700'"></div>
                                    <div class="h-1 flex-1 rounded-full transition-colors duration-200"
                                         :class="strengthScore >= 4 ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-gray-700'"></div>
                                </div>
                                <p role="status" aria-live="polite"
                                   class="mt-1.5 text-[11px] font-semibold"
                                   :class="strengthTextClass">
                                    <span x-text="strengthLabel"></span>
                                    <span class="text-gray-400 dark:text-gray-500 font-normal">· minimum 8 characters</span>
                                </p>
                            </div>

                            <p x-cloak x-show="capsNew" role="status" aria-live="polite"
                               class="mt-1.5 inline-flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                </svg>
                                Caps Lock is on
                            </p>
                            @error('new_password') <span class="text-rose-600 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        {{-- Confirm password --}}
                        <div>
                            <label for="field-new-password-confirm" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Confirm New Password</label>
                            <div class="relative">
                                <input :type="showConfirm ? 'text' : 'password'"
                                       id="field-new-password-confirm"
                                       wire:model.live.debounce.300ms="new_password_confirmation"
                                       autocomplete="new-password"
                                       minlength="8"
                                       @keyup="capsConfirm = $event.getModifierState && $event.getModifierState('CapsLock')"
                                       @keydown="capsConfirm = $event.getModifierState && $event.getModifierState('CapsLock')"
                                       @blur="capsConfirm = false"
                                       class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 pl-4 pr-11 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                                <button type="button"
                                        @click="showConfirm = !showConfirm"
                                        :aria-label="showConfirm ? 'Hide password' : 'Show password'"
                                        :aria-pressed="showConfirm ? 'true' : 'false'"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800
                                               transition-colors
                                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                    <svg x-show="!showConfirm" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <svg x-show="showConfirm" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                    </svg>
                                </button>
                            </div>

                            {{-- Match indicator — only shown when confirm has content --}}
                            <p x-cloak x-show="$wire.new_password_confirmation && $wire.new_password_confirmation.length > 0"
                               role="status" aria-live="polite"
                               class="mt-1.5 inline-flex items-center gap-1.5 text-[11px] font-semibold">
                                <template x-if="passwordsMatch">
                                    <span class="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Passwords match
                                    </span>
                                </template>
                                <template x-if="!passwordsMatch">
                                    <span class="inline-flex items-center gap-1.5 text-rose-600 dark:text-rose-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Passwords don't match
                                    </span>
                                </template>
                            </p>

                            <p x-cloak x-show="capsConfirm" role="status" aria-live="polite"
                               class="mt-1.5 inline-flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                </svg>
                                Caps Lock is on
                            </p>
                            @error('new_password_confirmation') <span class="text-rose-600 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </section>

            {{-- ═══════════════ ACTIONS ═══════════════ --}}
            <div data-reveal style="--reveal-delay: 200ms"
                 class="pt-4 border-t border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row items-stretch sm:items-center sm:justify-end gap-3">
                <a href="{{ route('home') }}" wire:navigate
                   class="inline-flex items-center justify-center rounded-xl px-5 h-11 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-200 active:scale-95
                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    Cancel
                </a>
                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="updateProfile"
                        class="inline-flex items-center justify-center gap-2 rounded-xl px-5 h-11 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm transition-all duration-200 active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                               disabled:opacity-60 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="updateProfile">Save Changes</span>
                    <span wire:loading wire:target="updateProfile" class="inline-flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Saving…
                    </span>
                </button>
            </div>
        </form>

        {{-- ═══════════════ DELETE ACCOUNT ═══════════════ --}}
        @if($this->canRequestDeletion)
            <section data-reveal style="--reveal-delay: 260ms"
                     class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm p-6">
                <h2 class="text-base font-semibold tracking-tight text-gray-900 dark:text-white">
                    Delete account
                </h2>

                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                    @if($this->isBusinessOwner)
                        You can request the deletion of your business listing or your entire account. As a business owner, requests are reviewed by a superadmin before processing.
                    @else
                        Permanently remove your account and all associated data. This action cannot be undone.
                    @endif
                </p>

                <div class="mt-5">
                    <a href="{{ route('account.delete') }}" wire:navigate
                       class="inline-flex items-center gap-2 rounded-xl border border-rose-300 dark:border-rose-500/40 bg-white dark:bg-transparent px-5 h-11 text-sm font-semibold text-rose-700 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-all duration-200 active:scale-95
                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        {{ $this->isBusinessOwner ? 'Request account deletion' : 'Delete my account' }}
                    </a>
                </div>
            </section>
        @endif

        {{-- Image crop modal — singleton --}}
        <x-image-crop-modal />
    </div>
</div>