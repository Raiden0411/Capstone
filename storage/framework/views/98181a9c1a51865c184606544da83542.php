
<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Traits\HandlesImageUploads;
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
    use HandlesImageUploads;

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

    public function hydrate(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');
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

    #[Computed]
    public function currentAvatarUrl(): ?string
    {
        return $this->user->avatar
            ? asset('storage/' . $this->user->avatar)
            : null;
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
            'avatar'    => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_active' => ['boolean'],
        ];

        if ($this->user->hasRole('super-admin')) {
            $rules['role'] = ['required', 'string', Rule::in(['super-admin'])];
        } elseif ($this->isPlatformUser) {
            $rules['role'] = ['required', 'string', Rule::in(['tourist'])];
        } else {
            $rules['role'] = [
                'required', 'string',
                Rule::exists('roles', 'name')->where('guard_name', 'web'),
                Rule::notIn($this->internalRoles),
            ];
        }

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

        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');

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
                $newAvatarPath = $this->storeImage($this->avatar, 'user-avatars', 'public', 'avatars');

                if (!$newAvatarPath) {
                    throw new \RuntimeException('Failed to store the uploaded avatar.');
                }
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

                $willBeBusinessOwner = $tenantId && $this->role === 'admin';

                if (!$willBeBusinessOwner) {
                    $data['active_mode'] = User::MODE_TOURIST;
                }

                $this->user->update($data);

                $this->user->syncRoles([$this->role]);
            });
        } catch (\Throwable $e) {
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

        if ($newAvatarPath && $oldAvatarPath && Storage::disk('public')->exists($oldAvatarPath)) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        session()->flash('message', "User '{$this->user->name}' updated successfully.");

        return $this->redirectRoute('superadmin.users.index', navigate: true);
    }
};
?>

<div
    x-data="{ showPassword: false, showConfirmPassword: false }"
    class="p-4 sm:p-6 lg:p-8 max-w-3xl mx-auto space-y-6"
>

    
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Platform Users</span>
            </div>
            <h1 class="font-display text-3xl md:text-4xl font-semibold text-gray-900 dark:text-white">
                Edit <em class="italic text-primary-600 dark:text-primary-400"><?php echo e($user->name); ?></em>
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-2">
                Update account details, role, and access level.
            </p>
        </div>
        <a href="<?php echo e(route('superadmin.users.index')); ?>" wire:navigate
           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                  transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span>Back to Users</span>
        </a>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('message')): ?>
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 4000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><?php echo e(session('message')); ?></span>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-7 w-7 rounded-md text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/10
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('error')): ?>
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 5000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span><?php echo e(session('error')); ?></span>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-7 w-7 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <form wire:submit="update" class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-8">

        
        <div class="space-y-5">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Basic Information
                </h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="field-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Full Name <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="field-name" wire:model="name" autocomplete="name" class="input w-full">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div>
                    <label for="field-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Email Address <span class="text-rose-500">*</span>
                    </label>
                    <input type="email" id="field-email" wire:model="email" autocomplete="email" class="input w-full">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>

        <hr class="border-gray-100 dark:border-gray-700/60">

        
        <div class="space-y-5">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Contact &amp; Photo
                </h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="field-phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Phone <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="field-phone" wire:model="phone" autocomplete="tel" inputmode="tel" class="input w-full" placeholder="09123456789">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Profile Picture <span class="text-gray-400 font-normal">(optional)</span>
                    </label>

                    <div
                        x-data="avatarPreview()"
                        x-on:avatar-preview.window="setUrl($event.detail.url)"
                        x-on:avatar-cleared.window="clear()"
                    >
                        <div
                            x-data="{
                                ...imageCropper({
                                    wireProperty: 'avatar',
                                    aspect: 1,
                                    title: 'Crop profile picture',
                                    description: 'Square crop works best',
                                    previewEvent: 'avatar-preview',
                                }),
                                dragging: false,
                            }"
                            x-init="init()"
                            x-on:dragover.prevent="dragging = true"
                            x-on:dragleave.prevent="dragging = false"
                            x-on:drop.prevent="
                                dragging = false;
                                const dt = new DataTransfer();
                                for (const f of $event.dataTransfer.files) dt.items.add(f);
                                $refs.input.files = dt.files;
                                $refs.input.dispatchEvent(new Event('change'));
                            "
                            :class="dragging
                                ? 'border-primary-600 bg-primary-50 dark:bg-primary-500/10'
                                : 'border-gray-300 dark:border-gray-600'"
                            class="relative flex items-center gap-4 rounded-xl border-2 border-dashed p-4 transition-colors"
                        >
                            <div class="relative h-16 w-16 shrink-0">
                                <img
                                    :src="previewUrl || '<?php echo e($this->currentAvatarUrl ?? ''); ?>'"
                                    :class="(previewUrl || <?php echo e($this->currentAvatarUrl ? 'true' : 'false'); ?>) ? 'block' : 'hidden'"
                                    alt="<?php echo e($user->name); ?>"
                                    class="h-16 w-16 object-cover rounded-lg border border-gray-200 dark:border-gray-700"
                                    decoding="async"
                                >

                                <div
                                    :class="(previewUrl || <?php echo e($this->currentAvatarUrl ? 'true' : 'false'); ?>) ? 'hidden' : 'flex'"
                                    class="h-16 w-16 rounded-lg bg-gray-100 dark:bg-gray-700 items-center justify-center text-gray-400 dark:text-gray-500"
                                >
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 11c2.209 0 4-1.791 4-4s-1.791-4-4-4-4 1.791-4 4 1.791 4 4 4zm0 2c-2.67 0-8 1.34-8 4v3h16v-3c0-2.66-5.33-4-8-4z"/>
                                    </svg>
                                </div>

                                <div
                                    wire:loading.flex
                                    wire:target="avatar"
                                    class="absolute inset-0 rounded-lg bg-black/55 backdrop-blur-[2px] items-center justify-center pointer-events-none"
                                    aria-hidden="true"
                                >
                                    <svg class="w-5 h-5 text-white animate-spin motion-reduce:animate-none" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                </div>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm font-semibold text-primary-600 dark:text-primary-400">
                                        <?php echo e($avatar ? 'Change photo' : 'Upload a new photo'); ?>

                                    </span>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($avatar): ?>
                                        <button type="button"
                                                wire:click="$set('avatar', null)"
                                                @click="$dispatch('avatar-cleared')"
                                                class="relative z-10 text-xs font-semibold text-rose-500 hover:text-rose-700 dark:hover:text-rose-300 active:scale-95 transition rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 px-1">
                                            Remove
                                        </button>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    Drag &amp; drop, or click to browse. JPG, PNG, or WebP up to 5 MB.
                                </p>

                                <div wire:loading wire:target="avatar" class="text-xs text-primary-600 dark:text-primary-400 mt-1 flex items-center gap-1">
                                    <svg class="animate-spin h-3 w-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    Uploading…
                                </div>
                            </div>

                            <input
                                x-ref="input"
                                id="avatar-input"
                                type="file"
                                x-on:change="pick($event)"
                                accept="image/jpeg,image/png,image/webp"
                                class="absolute inset-0 opacity-0 cursor-pointer"
                            >
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['avatar'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <hr class="border-gray-100 dark:border-gray-700/60">

        
        <div class="space-y-5">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Credentials
                </h2>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400 -mt-3">
                Leave both password fields blank to keep the current password.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="field-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Password</label>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'"
                               id="field-password"
                               wire:model="password"
                               autocomplete="new-password"
                               class="input w-full pr-10"
                               placeholder="••••••••">
                        <button type="button"
                                @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition active:scale-95 rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                tabindex="-1"
                                aria-label="Toggle password visibility">
                            <svg :class="showPassword ? 'hidden' : 'block'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg :class="showPassword ? 'block' : 'hidden'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div>
                    <label for="field-confirm-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm New Password</label>
                    <div class="relative">
                        <input :type="showConfirmPassword ? 'text' : 'password'"
                               id="field-confirm-password"
                               wire:model="password_confirmation"
                               autocomplete="new-password"
                               class="input w-full pr-10"
                               placeholder="••••••••">
                        <button type="button"
                                @click="showConfirmPassword = !showConfirmPassword"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition active:scale-95 rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                tabindex="-1"
                                aria-label="Toggle confirm password visibility">
                            <svg :class="showConfirmPassword ? 'hidden' : 'block'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg :class="showConfirmPassword ? 'block' : 'hidden'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['password_confirmation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>

        <hr class="border-gray-100 dark:border-gray-700/60">

        
        <div class="space-y-5">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Access &amp; Permissions
                </h2>
            </div>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($user->id === Auth::id() && $user->hasRole('super-admin')): ?>
                <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-xs text-amber-800 dark:text-amber-200">
                    <div class="flex items-start gap-2">
                        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span>You are editing your own super-admin account. Your role and active status are locked.</span>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/40 p-4">
                <div class="flex items-start gap-3">
                    <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-0.5 <?php echo e($user->hasRole('super-admin') ? 'opacity-60' : ''); ?>">
                        <input type="checkbox" wire:model.live="isPlatformUser" class="sr-only peer"
                               <?php if($user->hasRole('super-admin')): ?> disabled <?php endif; ?>>
                        <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full peer peer-checked:bg-primary-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full peer-disabled:opacity-50"></div>
                    </label>
                    <div class="min-w-0">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Platform User
                        </span>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Platform users get the <strong class="text-gray-700 dark:text-gray-300">Tourist</strong> role and are not tied to any business.
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($user->hasRole('super-admin')): ?>
                                <span class="block mt-1 text-amber-700 dark:text-amber-300 font-medium">Locked — super-admin accounts are always platform users.</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$isPlatformUser && !$user->hasRole('super-admin')): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="field-tenant-search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Assign Business</label>
                        <input type="text"
                               id="field-tenant-search"
                               wire:model.live.debounce.300ms="tenantSearch"
                               placeholder="Search businesses…"
                               class="input w-full mb-2">
                        <select wire:model.live="tenant_id" class="input w-full">
                            <option value="">— Select a business —</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->tenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <option <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'tenant-'.e($tenant->id).''; ?>wire:key="tenant-<?php echo e($tenant->id); ?>" value="<?php echo e($tenant->id); ?>"><?php echo e($tenant->name); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['tenant_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div>
                        <label for="field-role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Assign Role</label>
                        <select id="field-role" wire:model="role" class="input w-full">
                            <option value="">— Select a role —</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->availableRoles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $roleData): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <option <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'role-'.e($roleData['name']).''; ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'role-'.e($roleData['name']).''; ?>wire:key="role-<?php echo e($roleData['name']); ?>" value="<?php echo e($roleData['name']); ?>"><?php echo e($roleData['label']); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['role'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div>
                    <label for="field-role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Assign Role
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($user->hasRole('super-admin')): ?>
                            <span class="text-gray-400 font-normal">(locked — super-admin role cannot be changed)</span>
                        <?php elseif($isPlatformUser): ?>
                            <span class="text-gray-400 font-normal">(locked to Tourist for platform users)</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </label>
                    <select id="field-role" wire:model="role" class="input w-full"
                            <?php if($user->hasRole('super-admin') || $isPlatformUser): ?> disabled <?php endif; ?>>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->availableRoles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $roleData): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <option <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'role-'.e($roleData['name']).''; ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'role-'.e($roleData['name']).''; ?>wire:key="role-<?php echo e($roleData['name']); ?>" value="<?php echo e($roleData['name']); ?>"><?php echo e($roleData['label']); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['role'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <div>
                <div class="flex items-center gap-3 pt-1">
                    <label class="relative inline-flex items-center cursor-pointer <?php echo e($user->id === Auth::id() ? 'opacity-60 cursor-not-allowed' : ''); ?>">
                        <input type="checkbox" wire:model="is_active" class="sr-only peer"
                               <?php if($user->id === Auth::id()): ?> disabled <?php endif; ?>>
                        <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full peer peer-checked:bg-primary-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full peer-disabled:opacity-50"></div>
                    </label>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Active Account</span>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($user->id === Auth::id()): ?>
                        <span class="text-xs text-gray-400 dark:text-gray-500">(You)</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['is_active'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        
        <div class="pt-5 border-t border-gray-100 dark:border-gray-700/60 flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3">
            <a href="<?php echo e(route('superadmin.users.index')); ?>" wire:navigate
               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                      transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                Cancel
            </a>

            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="update"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                           disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="update" class="inline-flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Update User
                </span>
                <span wire:loading wire:target="update" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Saving…
                </span>
            </button>
        </div>
    </form>

    
    <?php if (isset($component)) { $__componentOriginalb992f09e6b42df8ffd0c7230daf18927 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb992f09e6b42df8ffd0c7230daf18927 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.image-crop-modal','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('image-crop-modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb992f09e6b42df8ffd0c7230daf18927)): ?>
<?php $attributes = $__attributesOriginalb992f09e6b42df8ffd0c7230daf18927; ?>
<?php unset($__attributesOriginalb992f09e6b42df8ffd0c7230daf18927); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb992f09e6b42df8ffd0c7230daf18927)): ?>
<?php $component = $__componentOriginalb992f09e6b42df8ffd0c7230daf18927; ?>
<?php unset($__componentOriginalb992f09e6b42df8ffd0c7230daf18927); ?>
<?php endif; ?>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views\superadmin\pages\user\⚡edit-user.blade.php ENDPATH**/ ?>