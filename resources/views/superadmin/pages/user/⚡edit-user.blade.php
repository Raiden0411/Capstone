<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\Tenant;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Str;

new
#[Layout('superadmin.layouts.app')]
#[Title('Edit User')]
class extends Component {

    use WithFileUploads;

    public User $user;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $phone = '';
    public $avatar;
    public string $tenant_id = '';
    public string $role = '';
    public string $tenantSearch = '';
    public bool $isPlatformUser = false;
    public bool $is_active = true;

    /**
     * Roles auto-assigned by the system — never manually assignable.
     *
     * @var array<int, string>
     */
    protected array $internalRoles = ['super-admin', 'tourist'];

    public function mount(User $user): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');

        $this->user             = $user;
        $this->name             = $user->name;
        $this->email            = $user->email;
        $this->phone            = $user->phone ?? '';
        $this->tenant_id        = (string) ($user->tenant_id ?? '');
        $this->isPlatformUser   = is_null($user->tenant_id);
        $this->is_active        = (bool) $user->is_active;

        if ($this->tenant_id) {
            $tenant = Tenant::query()->select('id', 'name')->find($this->tenant_id);
            $this->tenantSearch = $tenant?->name ?? '';
        }

        $this->role = $user->roles->first()?->name ?? '';
    }

    // ─────────────────────────────────────────────────────────
    //  Lifecycle: lightweight per-property hooks
    // ─────────────────────────────────────────────────────────

    public function updatedName(string $value): void
    {
        $this->name = trim($value);
    }

    public function updatedEmail(string $value): void
    {
        $this->email = trim($value);
    }

    public function updatedPhone(string $value): void
    {
        $this->phone = trim($value);
    }

    public function updatedTenantId(): void
    {
        // Switching tenant invalidates the previously selected role.
        $this->role = '';
    }

    public function updatedIsPlatformUser(bool $value): void
    {
        if ($value) {
            $this->tenant_id = '';
            $this->tenantSearch = '';

            if (!$this->user->hasRole('super-admin')) {
                $this->role = 'tourist';
            }

            return;
        }

        if ($this->role === 'tourist') {
            $this->role = '';
        }
    }

    public function clearTenant(): void
    {
        $this->tenant_id = '';
        $this->tenantSearch = '';
    }

    // ─────────────────────────────────────────────────────────
    //  Computed
    // ─────────────────────────────────────────────────────────

    #[Computed]
    public function tenants()
    {
        return Tenant::query()
            ->select('id', 'name')
            ->where('is_active', true)
            ->orderBy('name')
            ->when(
                $this->tenantSearch !== '',
                fn ($q) => $q->where('name', 'like', '%' . $this->tenantSearch . '%'),
            )
            ->limit(50)
            ->get();
    }

    /**
     * Assignable roles for the current edit target:
     *   - The target is a super-admin → only "super-admin" (locked).
     *   - Platform user (no tenant)    → only "tourist".
     *   - Tenant user                  → all non-internal roles.
     */
    #[Computed]
    public function availableRoles()
    {
        if ($this->user->hasRole('super-admin')) {
            return collect([
                ['name' => 'super-admin', 'label' => 'Super Admin'],
            ]);
        }

        if ($this->isPlatformUser) {
            return collect([
                ['name' => 'tourist', 'label' => 'Tourist'],
            ]);
        }

        return Role::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', $this->internalRoles)
            ->orderBy('name')
            ->pluck('name')
            ->map(fn (string $name) => [
                'name'  => $name,
                'label' => Str::headline($name),
            ])
            ->values();
    }

    // ─────────────────────────────────────────────────────────
    //  Validation
    // ─────────────────────────────────────────────────────────

    protected function rules(): array
    {
        $rules = [
            'name'      => ['required', 'string', 'min:3', 'max:255'],
            'email'     => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user->id)],
            'phone'     => ['required', 'string', 'max:20', 'regex:/^(09|\+639)\d{9}$/'],
            'password'  => ['nullable', 'string', 'min:8', 'confirmed'],
            'avatar'    => ['nullable', 'image', 'max:2048'],
            'role'      => [
                'required', 'string',
                Rule::exists('roles', 'name')->where('guard_name', 'web'),
                Rule::notIn($this->internalRoles),
            ],
            'is_active' => ['boolean'],
        ];

        if ($this->isPlatformUser) {
            $rules['tenant_id'] = ['nullable'];
        } else {
            $rules['tenant_id'] = [
                'required', 'integer',
                Rule::exists('tenants', 'id')->where('is_active', true),
            ];
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'phone.regex'        => 'Invalid Philippine phone number. Use 09xxxxxxxxx or +639xxxxxxxxx.',
            'tenant_id.required' => 'Please select a business for this user.',
            'role.not_in'        => 'This role cannot be assigned manually.',
        ];
    }

    // ─────────────────────────────────────────────────────────
    //  Update
    // ─────────────────────────────────────────────────────────

    public function update()
    {
        $this->name = trim($this->name);
        $this->validate();

        // Defensive re-check.
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');

        // Ensure the selected role is assignable for this user type.
        if (!$this->availableRoles->contains('name', $this->role)) {
            $this->addError('role', 'Invalid role selected.');
            return null;
        }

        // Self-protection: cannot remove own super-admin role.
        if ($this->user->id === Auth::id()
            && $this->user->hasRole('super-admin')
            && $this->role !== 'super-admin') {
            $this->addError('role', 'You cannot remove your own Super Admin role.');
            return null;
        }

        // Self-protection: cannot deactivate own account.
        if ($this->user->id === Auth::id() && !$this->is_active) {
            $this->addError('is_active', 'You cannot deactivate your own account.');
            return null;
        }

        $tenantId      = $this->isPlatformUser ? null : ($this->tenant_id ?: null);
        $oldAvatarPath = $this->user->avatar;
        $newAvatarPath = null;

        try {
            if ($this->avatar) {
                $newAvatarPath = $this->avatar->store('user-avatars', 'public');
            }

            DB::transaction(function () use ($tenantId, $newAvatarPath): void {
                $data = [
                    'name'      => $this->name,
                    'email'     => $this->email,
                    'phone'     => $this->phone,
                    'avatar'    => $newAvatarPath ?? $this->user->avatar,
                    'tenant_id' => $tenantId,
                    'is_active' => $this->is_active,
                ];

                if (!empty($this->password)) {
                    $data['password'] = Hash::make($this->password);
                }

                // Reconcile active_mode with the resulting role set.
                // A user with no tenant or no admin role cannot be in
                // "business" mode; keep their prior mode otherwise so a
                // business owner editing a field doesn't lose their state.
                $willBeBusinessOwner = $tenantId
                    && $this->role === 'admin';

                if (!$willBeBusinessOwner) {
                    $data['active_mode'] = User::MODE_TOURIST;
                }

                $this->user->update($data);

                // syncRoles replaces the role set entirely — this is the
                // intended behaviour for the superadmin panel where the
                // operator explicitly chooses the user's single role.
                $this->user->syncRoles([$this->role]);
            });
        } catch (\Throwable $e) {
            // Clean up the newly stored avatar if the transaction failed.
            if ($newAvatarPath && Storage::disk('public')->exists($newAvatarPath)) {
                Storage::disk('public')->delete($newAvatarPath);
            }

            Log::error('Superadmin user update failed: ' . $e->getMessage(), [
                'actor_id' => Auth::id(),
                'user_id'  => $this->user->id,
                'email'    => $this->email,
            ]);

            session()->flash('error', 'Failed to update user. Please try again.');
            return null;
        }

        // Delete old avatar AFTER successful commit (only if replaced).
        if ($newAvatarPath && $oldAvatarPath && Storage::disk('public')->exists($oldAvatarPath)) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        // Clear Spatie's permission cache after commit so any subsequent
        // authorization checks pick up the new role assignment.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        session()->flash('message', "User '{$this->user->name}' updated successfully.");

        return $this->redirectRoute('superadmin.users.index', navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-3xl mx-auto space-y-6"
     x-data="{ showPassword: false, showConfirmPassword: false, avatarPreview: null }"
     x-on:user-form-reset.window="avatarPreview = null; showPassword = false; showConfirmPassword = false">

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-transition.opacity
             class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('message') }}</span>
            </div>
            <button type="button" @click="show = false" class="text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif
    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-transition.opacity
             class="flex items-center justify-between bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" @click="show = false" class="text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Platform Users</span>
            </div>
            <h1 class="font-display text-3xl md:text-4xl font-semibold text-gray-900 dark:text-white">
                Edit <em class="italic text-primary-600 dark:text-primary-400">{{ $user->name }}</em>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Update account details, role, and access level.</p>
        </div>
        <a href="{{ route('superadmin.users.index') }}" wire:navigate
           class="btn-secondary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Users
        </a>
    </div>

    <form wire:submit="update" class="card p-5 sm:p-6 space-y-6">

        {{-- Basic Information --}}
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">Basic Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name *</label>
                    <input type="text" wire:model="name" class="input">
                    @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address *</label>
                    <input type="email" wire:model="email" class="input">
                    @error('email') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- Contact & Avatar --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone *</label>
                <input type="text" wire:model="phone" class="input" placeholder="09123456789">
                @error('phone') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Profile Picture (Optional)</label>
                <div
                    x-data="{ dragging: false }"
                    x-on:dragover.prevent="dragging = true"
                    x-on:dragleave.prevent="dragging = false"
                    x-on:drop.prevent="dragging = false; $refs.avatarInput.files = $event.dataTransfer.files; $refs.avatarInput.dispatchEvent(new Event('change'))"
                    :class="dragging ? 'border-primary-600 bg-blue-50 dark:bg-blue-500/10' : 'border-gray-300 dark:border-gray-600'"
                    class="relative flex items-center gap-4 rounded-xl border-2 border-dashed p-4 transition-colors"
                >
                    <template x-if="avatarPreview">
                        <img :src="avatarPreview" class="h-16 w-16 object-cover rounded-lg border border-gray-200 dark:border-gray-700 shrink-0">
                    </template>

                    <template x-if="!avatarPreview">
                        <div class="shrink-0">
                            @if($user->avatar && !$avatar)
                                <img src="{{ asset('storage/' . $user->avatar) }}" class="h-16 w-16 object-cover rounded-lg border border-gray-200 dark:border-gray-700" alt="{{ $user->name }}">
                            @else
                                <div class="h-16 w-16 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 11c2.209 0 4-1.791 4-4s-1.791-4-4-4-4 1.791-4 4 1.791 4 4 4zm0 2c-2.67 0-8 1.34-8 4v3h16v-3c0-2.66-5.33-4-8-4z"/></svg>
                                </div>
                            @endif
                        </div>
                    </template>

                    <div class="flex-1 min-w-0">
                        <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 dark:text-primary-400">
                            {{ $avatar ? 'Change photo' : 'Upload a photo' }}
                        </span>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Drag & drop, or click to browse. PNG/JPG up to 2MB.</p>
                        <div wire:loading wire:target="avatar" class="text-xs text-primary-600 dark:text-primary-400 mt-1 flex items-center gap-1">
                            <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            Uploading…
                        </div>
                    </div>

                    @if ($avatar)
                        <button type="button" wire:click="$set('avatar', null)" @click="avatarPreview = null"
                                class="relative z-10 shrink-0 text-xs font-semibold text-rose-500 hover:text-rose-700 active:scale-95 transition-transform">
                            Remove
                        </button>
                    @endif

                    <input x-ref="avatarInput" type="file" wire:model="avatar" accept="image/*"
                           @change="avatarPreview = $refs.avatarInput.files[0] ? URL.createObjectURL($refs.avatarInput.files[0]) : null"
                           class="absolute inset-0 opacity-0 cursor-pointer">
                </div>
                @error('avatar') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Passwords --}}
        <div>
            <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">Credentials</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        New Password <span class="text-gray-400 dark:text-gray-500 font-normal">(leave blank to keep current)</span>
                    </label>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'" wire:model="password" class="input pr-10" placeholder="••••••••">
                        <button type="button" @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded"
                                tabindex="-1" aria-label="Toggle password visibility">
                            <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showPassword" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                    @error('password') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm New Password</label>
                    <div class="relative">
                        <input :type="showConfirmPassword ? 'text' : 'password'" wire:model="password_confirmation" class="input pr-10" placeholder="••••••••">
                        <button type="button" @click="showConfirmPassword = !showConfirmPassword"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded"
                                tabindex="-1" aria-label="Toggle confirm password visibility">
                            <svg x-show="!showConfirmPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showConfirmPassword" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                    @error('password_confirmation') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- Access & Permissions --}}
        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">Access & Permissions</h3>

            {{-- Self-editing notice --}}
            @if($user->id === Auth::id() && $user->hasRole('super-admin'))
                <div class="mb-4 p-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-xs text-amber-800 dark:text-amber-200">
                    <div class="flex items-start gap-2">
                        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span>You are editing your own super-admin account. Your role and active status are locked.</span>
                    </div>
                </div>
            @endif

            {{-- Platform User Toggle --}}
            <div class="mb-4">
                <div class="flex items-center gap-3">
                    <label class="relative inline-flex items-center cursor-pointer {{ $user->hasRole('super-admin') ? 'opacity-60' : '' }}">
                        <input type="checkbox" wire:model.live="isPlatformUser" class="sr-only peer"
                               @if($user->hasRole('super-admin')) disabled @endif>
                        <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full peer peer-checked:bg-primary-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full peer-disabled:opacity-50"></div>
                    </label>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Platform User (No Business Affiliation)</span>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 ml-14">
                    Platform users get the <strong>Tourist</strong> role and are not tied to any business.
                </p>
            </div>

            {{-- Tenant Selection --}}
            @if(!$isPlatformUser && !$user->hasRole('super-admin'))
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Assign Business (Tenant) *</label>
                    <input type="text" wire:model.live.debounce.300ms="tenantSearch"
                           placeholder="Search businesses…"
                           class="input mb-2">
                    <select wire:model.live="tenant_id" class="select">
                        <option value="">-- Select a business --</option>
                        @foreach($this->tenants as $tenant)
                            <option wire:key="tenant-{{ $tenant->id }}" value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                    @error('tenant_id') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            @endif

            {{-- Role Select --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Assign Role *
                    @if($user->hasRole('super-admin'))
                        <span class="text-xs text-gray-400 font-normal">(locked — super-admin role cannot be changed)</span>
                    @elseif($isPlatformUser)
                        <span class="text-xs text-gray-400 font-normal">(locked to Tourist for platform users)</span>
                    @endif
                </label>
                <select wire:model="role" class="select"
                        @if($user->hasRole('super-admin') || $isPlatformUser) disabled @endif>
                    <option value="">-- Select a role --</option>
                    @foreach($this->availableRoles as $roleData)
                        <option wire:key="role-{{ $roleData['name'] }}" value="{{ $roleData['name'] }}">{{ $roleData['label'] }}</option>
                    @endforeach
                </select>
                @error('role') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Active Toggle --}}
            <div class="mt-4 flex items-center gap-3">
                <label class="relative inline-flex items-center cursor-pointer {{ $user->id === Auth::id() ? 'opacity-60 cursor-not-allowed' : '' }}">
                    <input type="checkbox" wire:model="is_active" class="sr-only peer"
                           @if($user->id === Auth::id()) disabled @endif>
                    <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full peer peer-checked:bg-primary-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full peer-disabled:opacity-50"></div>
                </label>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Active Account</span>
                @if($user->id === Auth::id())
                    <span class="text-xs text-gray-400 dark:text-gray-500">(You)</span>
                @endif
            </div>
            @error('is_active') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        {{-- Form Actions --}}
        <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="submit" wire:loading.attr="disabled" wire:target="update"
                    class="btn-primary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="update">Update User</span>
                <span wire:loading wire:target="update" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Saving…
                </span>
            </button>
            <a href="{{ route('superadmin.users.index') }}" wire:navigate
               class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                Cancel
            </a>
        </div>
    </form>
</div>