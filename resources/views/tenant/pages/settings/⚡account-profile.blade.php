{{-- resources/views/tenant/pages/settings/⚡account-profile.blade.php --}}
<?php

use App\Models\BusinessApplication;
use App\Models\BusinessDocument;
use App\Models\Employee;
use App\Models\User;
use App\Traits\HandlesImageUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Layout('tenant.layouts.app')]
#[Title('My Account')]
class extends Component
{
    use WithFileUploads;
    use HandlesImageUploads;

    public ?User $user = null;

    /** KYB record ID — resolved in mount, never trusted from client. */
    #[Locked]
    public ?int $businessApplicationId = null;

    // ═══ Profile ═══
    public string $name  = '';
    public string $email = '';
    public string $phone = '';

    // ═══ Avatar (stored on upload) ═══
    public $admin_avatar;
    public ?string $admin_avatar_path = null;

    // ═══ Password change ═══
    public string $current_password      = '';
    public string $password              = '';
    public string $password_confirmation = '';

    // ─────────────────────────────────────────────────────
    //  Lifecycle
    // ─────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->authorizeSelfService();

        $this->user = Auth::user();

        $this->name  = (string) $this->user->name;
        $this->email = (string) $this->user->email;
        $this->phone = (string) ($this->user->phone ?? '');

        $this->admin_avatar_path = $this->user->avatar;

        // Fallback — the KYB record may hold an owner photo even when the
        // User.avatar was never set (e.g. the tenant admin approved through
        // the superadmin wizard but never visited this page).
        $app = BusinessApplication::where('approved_tenant_id', $this->user->tenant_id)
            ->latest('id')
            ->first();

        if ($app) {
            $this->businessApplicationId = $app->id;

            if (! $this->admin_avatar_path && $app->owner_avatar_path) {
                $this->admin_avatar_path = $app->owner_avatar_path;
            }
        }
    }

    public function hydrate(): void
    {
        $this->authorizeSelfService();
    }

    protected function authorizeSelfService(): void
    {
        $user = Auth::user();

        abort_unless($user, 403);
        abort_unless($user->tenant_id, 403, 'Your account is not linked to a business.');

        if ($this->user) {
            abort_unless($this->user->id === $user->id, 403);
        }
    }

    // ─────────────────────────────────────────────────────
    //  Computed — profile + application
    // ─────────────────────────────────────────────────────

    #[Computed]
    public function avatarPreviewUrl(): ?string
    {
        return $this->admin_avatar_path ? asset('storage/' . $this->admin_avatar_path) : null;
    }

    #[Computed]
    public function isAdmin(): bool
    {
        return (bool) $this->user?->hasRole('admin');
    }

    /**
     * The approved KYB application for this user's tenant. Only the tenant
     * admin (business owner) has a KYB record — employees do not.
     */
    #[Computed]
    public function businessApplication(): ?BusinessApplication
    {
        if (! $this->user?->tenant_id || ! $this->isAdmin) {
            return null;
        }

        return BusinessApplication::query()
            ->with(['documents', 'typeOfTenant'])
            ->where('approved_tenant_id', $this->user->tenant_id)
            ->latest('id')
            ->first();
    }

    /**
     * @return \Illuminate\Support\Collection<string, BusinessDocument>
     */
    #[Computed]
    public function documentsByType(): \Illuminate\Support\Collection
    {
        $app = $this->businessApplication;

        return $app ? $app->documents->keyBy('document_type') : collect();
    }

    /**
     * Combined list of required + optional documents with metadata for the
     * read-only summary. Each entry is keyed by the document type string.
     *
     * @return array<int, array{type: string, label: string, required: bool, doc: ?BusinessDocument}>
     */
    #[Computed]
    public function documentChecklist(): array
    {
        $docs = $this->documentsByType;
        $rows = [];

        foreach (BusinessApplication::REQUIRED_DOCUMENTS as $type) {
            $rows[] = [
                'type'     => $type,
                'label'    => BusinessApplication::DOCUMENT_LABELS[$type] ?? ucfirst(str_replace('_', ' ', $type)),
                'required' => true,
                'doc'      => $docs->get($type),
            ];
        }

        foreach (BusinessApplication::OPTIONAL_DOCUMENTS as $type) {
            $rows[] = [
                'type'     => $type,
                'label'    => BusinessApplication::DOCUMENT_LABELS[$type] ?? ucfirst(str_replace('_', ' ', $type)),
                'required' => false,
                'doc'      => $docs->get($type),
            ];
        }

        return $rows;
    }

    /**
     * Human-readable label for a business type (dti / sec / cda).
     */
    public function businessTypeLabel(?string $type): string
    {
        if (! $type) {
            return '—';
        }

        return BusinessApplication::BUSINESS_TYPE_LABELS[$type] ?? ucfirst($type);
    }

    /**
     * Human-readable label for a document's verification status.
     *
     * @return array{label: string, classes: string}
     */
    public function documentStatusConfig(?BusinessDocument $doc): array
    {
        if (! $doc) {
            return ['label' => 'Not uploaded', 'classes' => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-600'];
        }

        if ($doc->expires_at && $doc->expires_at->isPast()) {
            return ['label' => 'Expired', 'classes' => 'bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-500/30'];
        }

        return match ($doc->verification_status) {
            BusinessDocument::STATUS_VERIFIED => ['label' => 'Verified', 'classes' => 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30'],
            BusinessDocument::STATUS_REJECTED => ['label' => 'Rejected', 'classes' => 'bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-500/30'],
            default                          => ['label' => 'Pending', 'classes' => 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/30'],
        };
    }

    /**
     * Overall status pill config for the application header.
     *
     * @return array{label: string, classes: string}
     */
    public function applicationStatusConfig(string $status): array
    {
        return match ($status) {
            BusinessApplication::STATUS_APPROVED       => ['label' => 'Approved', 'classes' => 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30'],
            BusinessApplication::STATUS_REJECTED       => ['label' => 'Rejected', 'classes' => 'bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-500/30'],
            BusinessApplication::STATUS_NEEDS_REVISION => ['label' => 'Needs revision', 'classes' => 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/30'],
            BusinessApplication::STATUS_UNDER_REVIEW   => ['label' => 'Under review', 'classes' => 'bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-500/30'],
            BusinessApplication::STATUS_PENDING        => ['label' => 'Pending', 'classes' => 'bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-500/30'],
            default                                    => ['label' => ucfirst($status), 'classes' => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-600'],
        };
    }

    // ─────────────────────────────────────────────────────
    //  Avatar upload / removal
    // ─────────────────────────────────────────────────────

    public function updatedAdminAvatar(): void
    {
        if (! $this->admin_avatar) {
            return;
        }

        try {
            $this->validate([
                'admin_avatar' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            ], [
                'admin_avatar.max'   => 'Photo must not exceed 5 MB.',
                'admin_avatar.mimes' => 'Photo must be JPEG, PNG, or WebP.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->admin_avatar = null;
            $first = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', message: $first ?: 'Invalid photo.', type: 'error');
            return;
        }

        try {
            /*
             * storeImage() routes the file through ImageCompressionService
             * (context: avatars — 512 KB / 800×800). It returns null on
             * storage failure (never throws); that null is promoted to a
             * RuntimeException so the catch block fires and the user sees
             * a toast — never a DB row pointing at a missing file.
             *
             * IMPORTANT: This method does NOT delete the previous avatar.
             * Deletion is deferred to save() after the DB commit succeeds.
             */
            $newPath = $this->storeImage($this->admin_avatar, 'tenant-avatars', 'public', 'avatars');

            if (! $newPath) {
                throw new \RuntimeException('Failed to store the uploaded photo.');
            }

            $this->admin_avatar_path = $newPath;

            $this->dispatch('toast', message: 'Photo uploaded.', type: 'success');
        } catch (\Throwable $e) {
            Log::error('Account avatar upload failed', [
                'user_id' => $this->user?->id,
                'error'   => $e->getMessage(),
            ]);
            $this->dispatch('toast', message: 'Upload failed. Please try again.', type: 'error');
        } finally {
            $this->admin_avatar = null;
        }
    }

    public function removeAdminAvatar(): void
    {
        // Don't touch storage here — the user may navigate away without
        // clicking Save. Clearing the session-state path is enough; save()
        // removes the file after the DB commit succeeds.
        $this->admin_avatar_path = null;
        $this->dispatch('toast', message: 'Photo removed.', type: 'info');
    }

    // ─────────────────────────────────────────────────────
    //  Field hooks
    // ─────────────────────────────────────────────────────

    public function updated(string $property): void
    {
        $trimmable = ['name', 'email', 'phone'];

        if (in_array($property, $trimmable, true)) {
            $this->$property = trim((string) $this->$property);
        }

        if ($property === 'phone') {
            $this->phone = substr(
                (string) preg_replace('/[^0-9]/', '', $this->phone),
                0,
                11
            );
        }

        if (in_array($property, ['email', 'phone'], true) && $this->$property !== '') {
            $this->validateOnly($property);
        }
    }

    // ─────────────────────────────────────────────────────
    //  Save
    // ─────────────────────────────────────────────────────

    public function save(): void
    {
        $this->authorizeSelfService();

        $userId   = $this->user->id;
        $tenantId = $this->user->tenant_id;

        $this->validate([
            'name'             => ['required', 'string', 'min:3', 'max:255'],
            'email'            => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone'            => ['nullable', 'string', 'regex:/^[0-9]{10,11}$/'],
            'admin_avatar'     => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'current_password' => ['nullable', 'required_with:password', 'string'],
            'password'         => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'phone.regex'                    => 'Phone number must be 10–11 digits, numbers only.',
            'current_password.required_with' => 'Enter your current password to set a new one.',
            'password.min'                   => 'New password must be at least 8 characters.',
            'password.confirmed'             => 'New password confirmation does not match.',
        ]);

        $changingPassword = $this->password !== '';

        if ($changingPassword) {
            if (! Hash::check($this->current_password, $this->user->password)) {
                $this->addError('current_password', 'Your current password is incorrect.');
                return;
            }
        }

        $oldAvatar = $this->user->avatar;

        try {
            DB::transaction(function () use ($changingPassword): void {
                $locked = User::whereKey($this->user->id)->lockForUpdate()->firstOrFail();

                $payload = [
                    'name'   => $this->name,
                    'email'  => $this->email,
                    'phone'  => $this->phone ?: null,
                    'avatar' => $this->admin_avatar_path,
                ];

                if ($changingPassword) {
                    $payload['password'] = Hash::make($this->password);
                }

                $locked->update($payload);

                // Sync the KYB record's owner_avatar_path when this user is
                // the tenant's admin. Employees don't own a KYB record.
                if ($this->businessApplicationId && $locked->hasRole('admin')) {
                    BusinessApplication::whereKey($this->businessApplicationId)
                        ->update(['owner_avatar_path' => $this->admin_avatar_path]);
                }

                // Sync the Employee row avatar, if one exists for this user.
                $employeeRow = Employee::where('user_id', $locked->id)->first();
                if ($employeeRow) {
                    $employeeRow->update([
                        'name'   => $this->name,
                        'phone'  => $this->phone ?: null,
                        'avatar' => $this->admin_avatar_path,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            if ($this->admin_avatar_path && $this->admin_avatar_path !== $oldAvatar
                && Storage::disk('public')->exists($this->admin_avatar_path)) {
                Storage::disk('public')->delete($this->admin_avatar_path);
            }

            Log::error('Account profile save failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);

            $this->dispatch('toast', message: 'Something went wrong while saving. Please try again.', type: 'error');
            return;
        }

        // Delete the previous avatar AFTER a successful commit.
        if ($this->admin_avatar_path !== $oldAvatar && $oldAvatar
            && Storage::disk('public')->exists($oldAvatar)) {
            Storage::disk('public')->delete($oldAvatar);
        }

        // Reset password fields — they should never linger in the DOM.
        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->user->refresh();

        // Invalidate the cached computed properties that read from $user.
        unset($this->avatarPreviewUrl, $this->isAdmin);

        session()->flash('account_message', $changingPassword
            ? 'Account updated and password changed. You may need to sign in again on other devices.'
            : 'Account updated successfully.');

        $this->dispatch('profile-saved');
        $this->dispatch('toast', message: 'Account saved.', type: 'success');
    }
};
?>

<div
    x-data="{ toasts: [] }"
    x-on:toast.window="
        const id = Date.now() + Math.random();
        toasts.push({ id, message: $event.detail.message, type: $event.detail.type || 'info' });
        setTimeout(() => { toasts = toasts.filter(t => t.id !== id) }, 4000);
    "
    x-on:profile-saved.window="window.scrollTo({ top: 0, behavior: 'smooth' });"
    class="p-4 sm:p-6 lg:p-8 max-w-5xl mx-auto space-y-6"
>
    {{-- ═══ Toasts ═══ --}}
    <div class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 w-full max-w-sm pointer-events-none no-print">
        <template x-for="toast in toasts" :key="toast.id">
            <div
                class="pointer-events-auto rounded-xl px-4 py-3 shadow-lg text-sm font-medium flex items-center gap-2 border"
                :class="{
                    'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-300': toast.type === 'success',
                    'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-500/10 dark:border-rose-500/30 dark:text-rose-300': toast.type === 'error',
                    'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-500/10 dark:border-blue-500/30 dark:text-blue-300': toast.type === 'info',
                }"
            >
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>

    {{-- ═══ Save overlay ═══ --}}
    <div wire:loading.delay.longer wire:target="save"
         class="fixed inset-0 z-40 bg-white/60 dark:bg-gray-900/60 backdrop-blur-sm flex items-center justify-center pointer-events-none">
        <div class="flex items-center gap-3 bg-white dark:bg-gray-800 rounded-2xl shadow-xl px-6 py-4 pointer-events-auto">
            <svg class="animate-spin h-5 w-5 text-primary-600 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Saving your account…</span>
        </div>
    </div>

    {{-- ═══ Page header ═══ --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-800">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Settings · Account</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                My Account
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                Manage your personal information, photo, and password.
            </p>
        </div>

        @if($this->isAdmin)
            <a href="{{ route('tenant.settings.index') }}" wire:navigate
               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                      transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <span>Business Settings</span>
            </a>
        @endif
    </div>

    {{-- ═══ Flash + error bag ═══ --}}
    @if(session()->has('account_message'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 4000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>{{ session('account_message') }}</span>
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
    @endif

    @if($errors->any())
        <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-500/10 p-4 text-sm text-rose-700 dark:text-rose-300">
            <p class="font-semibold mb-1">Please fix {{ $errors->count() }} field{{ $errors->count() === 1 ? '' : 's' }} before saving:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li wire:key="err-{{ $loop->index }}">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">

        {{-- ═══════════════ PROFILE PHOTO ═══════════════ --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Profile Photo
                </h2>
            </div>

            <div
                x-data="{
                    ...imageCropper({
                        wireProperty: 'admin_avatar',
                        aspect: 1,
                        title: 'Crop profile photo',
                        description: 'Square crop works best',
                        previewEvent: 'avatar-preview',
                    }),
                    ...avatarPreview(),
                    dragging: false,
                    serverUrl: '{{ $this->avatarPreviewUrl ?? '' }}',
                    get showPreview() { return !!this.previewUrl || !!this.serverUrl; },
                }"
                x-init="init()"
                x-on:avatar-preview.window="setUrl($event.detail.url)"
                x-on:avatar-cleared.window="clear()"
                class="flex flex-col sm:flex-row sm:items-start gap-5"
            >
                {{-- Avatar box (round) --}}
                <div
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
                        : 'border-gray-300 dark:border-gray-600 hover:border-primary-500/50'"
                    class="relative w-28 h-28 sm:w-32 sm:h-32 shrink-0 mx-auto sm:mx-0 rounded-full border-2 border-dashed overflow-hidden transition-colors"
                >
                    <input
                        x-ref="input"
                        id="avatar-input"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                        x-on:change="pick($event)"
                    >

                    <img
                        :src="previewUrl || serverUrl"
                        :class="showPreview ? 'block' : 'hidden'"
                        alt="Profile photo"
                        class="absolute inset-0 w-full h-full object-cover"
                        loading="lazy"
                        decoding="async"
                    >

                    <label
                        for="avatar-input"
                        :class="showPreview ? 'hidden' : 'flex'"
                        class="absolute inset-0 flex-col items-center justify-center p-3 text-center cursor-pointer"
                    >
                        <svg class="h-8 w-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <p class="mt-1 text-[10px] font-medium text-gray-700 dark:text-gray-300 leading-tight">
                            Click or drop
                        </p>
                    </label>

                    <div
                        wire:loading.flex
                        wire:target="admin_avatar"
                        class="absolute inset-0 bg-black/45 backdrop-blur-[2px] items-center justify-center pointer-events-none"
                        aria-hidden="true"
                    >
                        <svg class="animate-spin h-5 w-5 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </div>
                </div>

                {{-- Helper + actions column --}}
                <div class="flex-1 min-w-0 space-y-3 text-center sm:text-left">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        PNG, JPG, or WebP · max 5 MB · auto-cropped to a square and compressed to ≤512 KB.
                    </p>

                    <div :class="showPreview ? 'flex' : 'hidden'" class="flex-wrap items-center justify-center sm:justify-start gap-2">
                        <label for="avatar-input"
                               class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg
                                      border border-gray-300 dark:border-gray-600
                                      bg-white dark:bg-gray-800
                                      text-gray-700 dark:text-gray-200
                                      text-xs font-semibold cursor-pointer
                                      transition-all duration-200 active:scale-95
                                      hover:bg-gray-50 dark:hover:bg-gray-700
                                      focus-within:outline-none focus-within:ring-2 focus-within:ring-primary-500/50">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            <span>Replace</span>
                        </label>

                        <button type="button"
                                x-on:click="clear(); $wire.removeAdminAvatar()"
                                class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg
                                       border border-rose-300 dark:border-rose-500/40
                                       bg-white dark:bg-gray-800
                                       text-rose-700 dark:text-rose-300
                                       text-xs font-semibold
                                       transition-all duration-200 active:scale-95
                                       hover:bg-rose-50 dark:hover:bg-rose-500/10
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            <span>Remove</span>
                        </button>
                    </div>
                </div>
            </div>

            @error('admin_avatar') <span class="text-rose-500 dark:text-rose-400 text-xs block" role="alert">{{ $message }}</span> @enderror
        </div>

        {{-- ═══════════════ PERSONAL INFORMATION ═══════════════ --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Personal Information
                </h2>
            </div>

            <div>
                <label for="field-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Full Name <span class="text-rose-500">*</span>
                </label>
                <input type="text" id="field-name" wire:model.live.debounce.300ms="name" class="input" maxlength="255">
                @error('name') <p class="mt-1 text-xs text-rose-500" role="alert">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="field-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Email Address <span class="text-rose-500">*</span>
                    </label>
                    <input type="email" id="field-email" wire:model.live.debounce.400ms="email" class="input" maxlength="255">
                    @error('email') <p class="mt-1 text-xs text-rose-500" role="alert">{{ $message }}</p> @enderror
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Used for signing in and receiving notifications.</p>
                </div>
                <div>
                    <label for="field-phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Phone Number <span class="text-gray-400 dark:text-gray-500 font-normal">(optional)</span>
                    </label>
                    <input type="tel" id="field-phone"
                           inputmode="numeric" pattern="[0-9]*" maxlength="11"
                           wire:model="phone"
                           x-on:input="event.target.value = event.target.value.replace(/[^0-9]/g, '').slice(0, 11)"
                           class="input" placeholder="09xxxxxxxxx">
                    @error('phone') <p class="mt-1 text-xs text-rose-500" role="alert">{{ $message }}</p> @enderror
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">10–11 digits, numbers only.</p>
                </div>
            </div>
        </div>

        {{-- ═══════════════ CHANGE PASSWORD ═══════════════ --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Change Password
                </h2>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400 -mt-2">
                Leave all three fields blank to keep your current password. If you change it, you may need to sign in again on other devices.
            </p>

            {{--
                Rule 89: The eye-icon toggle and the input MUST share one
                Alpine scope. Two independent x-data blocks = the button
                toggles a variable the input cannot read.
            --}}
            <div x-data="{ show_current: false, show_new: false, show_confirm: false }" class="space-y-4">
                <div>
                    <label for="field-current-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Current Password
                    </label>
                    <div class="relative">
                        <input
                            :type="show_current ? 'text' : 'password'"
                            id="field-current-password"
                            wire:model="current_password"
                            autocomplete="current-password"
                            class="input pr-10"
                            placeholder="Enter your current password">
                        <button type="button"
                                @click="show_current = !show_current"
                                aria-label="Toggle password visibility"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300
                                       active:scale-95 transition-transform rounded
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    @error('current_password') <p class="mt-1 text-xs text-rose-500" role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="field-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            New Password
                        </label>
                        <div class="relative">
                            <input
                                :type="show_new ? 'text' : 'password'"
                                id="field-password"
                                wire:model="password"
                                autocomplete="new-password"
                                class="input pr-10"
                                placeholder="Min. 8 characters">
                            <button type="button"
                                    @click="show_new = !show_new"
                                    aria-label="Toggle new password visibility"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300
                                           active:scale-95 transition-transform rounded
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                        @error('password') <p class="mt-1 text-xs text-rose-500" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="field-password-confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Confirm New Password
                        </label>
                        <div class="relative">
                            <input
                                :type="show_confirm ? 'text' : 'password'"
                                id="field-password-confirmation"
                                wire:model="password_confirmation"
                                autocomplete="new-password"
                                class="input pr-10"
                                placeholder="Re-enter new password">
                            <button type="button"
                                    @click="show_confirm = !show_confirm"
                                    aria-label="Toggle confirm password visibility"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300
                                           active:scale-95 transition-transform rounded
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                        @error('password_confirmation') <p class="mt-1 text-xs text-rose-500" role="alert">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════ SAVE ═══════════════ --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3 pt-2">
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                           disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="save">Save Changes</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Saving…
                </span>
            </button>
        </div>
    </form>

    {{-- ═══════════════ YOUR BUSINESS APPLICATION (read-only) ═══════════════ --}}
    @if($this->businessApplication)
        @php
            $app  = $this->businessApplication;
            $pill = $this->applicationStatusConfig($app->status);
        @endphp

        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm overflow-hidden">

            {{-- ─── Header ─── --}}
            <div class="px-5 sm:px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <div>
                            <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Your Business Application
                            </h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Everything you submitted during business registration — read-only.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border {{ $pill['classes'] }}">
                            {{ $pill['label'] }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="p-5 sm:p-6 space-y-6">

                {{-- ─── Business details ─── --}}
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-3 h-px bg-primary-600"></span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Business</span>
                    </div>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5 text-sm">
                        <div>
                            <dt class="text-[11px] text-gray-500 dark:text-gray-400">Business name</dt>
                            <dd class="font-medium text-gray-900 dark:text-white mt-0.5">{{ $app->business_name ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-gray-500 dark:text-gray-400">Business type</dt>
                            <dd class="font-medium text-gray-900 dark:text-white mt-0.5">{{ $this->businessTypeLabel($app->business_type) }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-gray-500 dark:text-gray-400">Registration number</dt>
                            <dd class="font-mono text-gray-900 dark:text-white mt-0.5">{{ $app->business_registration_number ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-gray-500 dark:text-gray-400">TIN</dt>
                            <dd class="font-mono text-gray-900 dark:text-white mt-0.5">{{ $app->tin_number ?: '—' }}</dd>
                        </div>
                        @if($app->typeOfTenant)
                            <div class="sm:col-span-2">
                                <dt class="text-[11px] text-gray-500 dark:text-gray-400">Category</dt>
                                <dd class="font-medium text-gray-900 dark:text-white mt-0.5">{{ $app->typeOfTenant->type }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- ─── Owner details ─── --}}
                <div class="pt-5 border-t border-gray-100 dark:border-gray-700/60">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-3 h-px bg-primary-600"></span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Owner</span>
                    </div>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5 text-sm">
                        <div>
                            <dt class="text-[11px] text-gray-500 dark:text-gray-400">Full name</dt>
                            <dd class="font-medium text-gray-900 dark:text-white mt-0.5">{{ $app->owner_full_name ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-gray-500 dark:text-gray-400">ID type</dt>
                            <dd class="font-medium text-gray-900 dark:text-white mt-0.5">
                                {{ $app->owner_id_type ? (BusinessApplication::OWNER_ID_TYPES[$app->owner_id_type] ?? ucfirst($app->owner_id_type)) : '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-gray-500 dark:text-gray-400">ID number</dt>
                            <dd class="font-mono text-gray-900 dark:text-white mt-0.5">{{ $app->owner_id_number ?: '—' }}</dd>
                        </div>
                        @if($app->owner_birthdate)
                            <div>
                                <dt class="text-[11px] text-gray-500 dark:text-gray-400">Birthdate</dt>
                                <dd class="font-medium text-gray-900 dark:text-white mt-0.5 tabular-nums">{{ $app->owner_birthdate->format('M j, Y') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- ─── Contact + address ─── --}}
                <div class="pt-5 border-t border-gray-100 dark:border-gray-700/60">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-3 h-px bg-primary-600"></span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Contact &amp; Address</span>
                    </div>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5 text-sm">
                        <div>
                            <dt class="text-[11px] text-gray-500 dark:text-gray-400">Contact email</dt>
                            <dd class="font-medium text-gray-900 dark:text-white mt-0.5 truncate">{{ $app->contact_email ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-gray-500 dark:text-gray-400">Contact phone</dt>
                            <dd class="font-mono text-gray-900 dark:text-white mt-0.5">{{ $app->contact_phone ?: '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-[11px] text-gray-500 dark:text-gray-400">Street address</dt>
                            <dd class="text-gray-900 dark:text-white mt-0.5">{{ $app->address ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-gray-500 dark:text-gray-400">Barangay</dt>
                            <dd class="text-gray-900 dark:text-white mt-0.5">{{ $app->barangay ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-gray-500 dark:text-gray-400">City / Municipality</dt>
                            <dd class="text-gray-900 dark:text-white mt-0.5">{{ $app->city ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-gray-500 dark:text-gray-400">Province</dt>
                            <dd class="text-gray-900 dark:text-white mt-0.5">{{ $app->province ?: '—' }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- ─── Documents ─── --}}
                <div class="pt-5 border-t border-gray-100 dark:border-gray-700/60">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-3 h-px bg-primary-600"></span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Documents</span>
                        <span class="text-[10px] text-gray-400 dark:text-gray-500 font-normal normal-case tracking-normal">
                            — {{ $app->documents->count() }} on file
                        </span>
                    </div>

                    <div class="space-y-2">
                        @foreach($this->documentChecklist as $row)
                            @php
                                $doc        = $row['doc'];
                                $docStatus  = $this->documentStatusConfig($doc);
                                $viewPath   = $doc?->stored_path;
                                $viewUrl    = $viewPath ? asset('storage/' . $viewPath) : null;
                            @endphp

                            <div wire:key="doc-{{ $row['type'] }}"
                                 class="flex flex-wrap items-center gap-3 p-3 rounded-xl border transition
                                        {{ $doc
                                           ? 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40'
                                           : 'border-dashed border-gray-300 dark:border-gray-600 bg-transparent' }}">

                                {{-- Icon --}}
                                <div class="w-9 h-9 rounded-lg shrink-0 flex items-center justify-center
                                            {{ $doc ? 'bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400' : 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500' }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    </svg>
                                </div>

                                {{-- Info --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                            {{ $row['label'] }}
                                        </p>
                                        @if($row['required'])
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-rose-50 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30">
                                                Required
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-600">
                                                Optional
                                            </span>
                                        @endif
                                    </div>

                                    @if($doc)
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate mt-0.5" title="{{ $doc->original_filename }}">
                                            {{ $doc->original_filename }}
                                        </p>
                                        @if($doc->issued_at || $doc->expires_at)
                                            <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5 tabular-nums">
                                                @if($doc->issued_at) Issued {{ $doc->issued_at->format('M j, Y') }} @endif
                                                @if($doc->issued_at && $doc->expires_at) · @endif
                                                @if($doc->expires_at) Expires {{ $doc->expires_at->format('M j, Y') }} @endif
                                            </p>
                                        @endif
                                    @else
                                        <p class="text-[11px] text-gray-400 dark:text-gray-500 italic mt-0.5">
                                            Not uploaded
                                        </p>
                                    @endif
                                </div>

                                {{-- Status pill --}}
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border shrink-0 {{ $docStatus['classes'] }}">
                                    {{ $docStatus['label'] }}
                                </span>

                                {{-- View link --}}
                                @if($viewUrl)
                                    <a href="{{ $viewUrl }}" target="_blank" rel="noopener noreferrer"
                                       class="inline-flex items-center justify-center h-8 px-3 rounded-lg shrink-0
                                              border border-gray-300 dark:border-gray-600
                                              bg-white dark:bg-gray-800
                                              text-gray-700 dark:text-gray-200
                                              text-[11px] font-semibold
                                              transition-all duration-200 active:scale-95
                                              hover:bg-gray-50 dark:hover:bg-gray-700
                                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        View
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- ─── Review trail ─── --}}
                <div class="pt-5 border-t border-gray-100 dark:border-gray-700/60">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-3 h-px bg-primary-600"></span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Review trail</span>
                    </div>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5 text-sm">
                        @if($app->submitted_at)
                            <div>
                                <dt class="text-[11px] text-gray-500 dark:text-gray-400">Submitted</dt>
                                <dd class="font-medium text-gray-900 dark:text-white mt-0.5 tabular-nums">{{ $app->submitted_at->format('M j, Y \a\t g:i A') }}</dd>
                            </div>
                        @endif
                        @if($app->reviewed_at)
                            <div>
                                <dt class="text-[11px] text-gray-500 dark:text-gray-400">Reviewed</dt>
                                <dd class="font-medium text-gray-900 dark:text-white mt-0.5 tabular-nums">{{ $app->reviewed_at->format('M j, Y \a\t g:i A') }}</dd>
                            </div>
                        @endif
                        @if($app->approvedTenant)
                            <div>
                                <dt class="text-[11px] text-gray-500 dark:text-gray-400">Approved business</dt>
                                <dd class="font-medium text-gray-900 dark:text-white mt-0.5">{{ $app->approvedTenant->name }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-[11px] text-gray-500 dark:text-gray-400">Application source</dt>
                            <dd class="font-medium text-gray-900 dark:text-white mt-0.5">{{ $app->sourceLabel() }}</dd>
                        </div>
                    </dl>

                    @if($app->rejection_reason)
                        <div class="mt-4 flex items-start gap-2.5 rounded-xl border border-rose-200/70 dark:border-rose-500/30 bg-rose-50/60 dark:bg-rose-500/[0.06] px-3.5 py-3 text-xs text-rose-800 dark:text-rose-300">
                            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <div>
                                <p class="font-semibold">Rejection reason</p>
                                <p class="mt-0.5 whitespace-pre-line leading-relaxed">{{ $app->rejection_reason }}</p>
                            </div>
                        </div>
                    @endif

                    @if($app->revision_notes)
                        <div class="mt-4 flex items-start gap-2.5 rounded-xl border border-amber-200/70 dark:border-amber-500/30 bg-amber-50/60 dark:bg-amber-500/[0.06] px-3.5 py-3 text-xs text-amber-800 dark:text-amber-300">
                            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <div>
                                <p class="font-semibold">Revision notes</p>
                                <p class="mt-0.5 whitespace-pre-line leading-relaxed">{{ $app->revision_notes }}</p>
                            </div>
                        </div>
                    @endif
                </div>

            </div>
        </div>
    @endif

    {{-- ═══════════════ DANGER ZONE ═══════════════ --}}
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-rose-200/80 dark:border-rose-500/30 shadow-sm p-5 sm:p-6 space-y-4">
        <div class="flex items-center gap-3">
            <span class="w-5 h-px bg-rose-500"></span>
            <h2 class="text-[10px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">
                Danger Zone
            </h2>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                    {{ $this->isAdmin ? 'Delete my account' : 'Delete account' }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 leading-relaxed">
                    @if($this->isAdmin)
                        Permanently remove your personal account. As a business owner, you can also choose to remove the business. A superadmin reviews the request first.
                    @else
                        Permanently remove your personal account and all associated data. This cannot be undone.
                    @endif
                </p>
            </div>

            <a href="{{ route('account.delete') }}" wire:navigate
               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl shrink-0
                      border border-rose-300 dark:border-rose-500/40
                      bg-white dark:bg-gray-800
                      text-rose-700 dark:text-rose-300
                      text-sm font-semibold
                      transition-all duration-200 active:scale-95
                      hover:bg-rose-50 dark:hover:bg-rose-500/10
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                <span>Delete account</span>
            </a>
        </div>
    </div>

    {{-- Image crop modal — singleton for this page (Rule 87) --}}
    <x-image-crop-modal />
</div>