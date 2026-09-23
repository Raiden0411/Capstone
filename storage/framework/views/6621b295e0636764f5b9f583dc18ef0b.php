
<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Locked;
use App\Models\Event;
use App\Models\Tenant;
use App\Traits\ChecksTenantPermissions;
use App\Traits\HandlesImageUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

new
#[Layout('tenant.layouts.app')]
#[Title('Add Event')]
class extends Component
{
    use WithFileUploads;
    use ChecksTenantPermissions;
    use HandlesImageUploads;

    // ── Event fields ──
    public string $name        = '';
    public string $description = '';
    public string $type        = 'fiesta';
    public string $start_date  = '';
    public string $end_date    = '';
    public bool   $is_active   = true;
    public $image;

    /** Inherited from the tenant — never editable here. */
    #[Locked] public string $barangay = '';

    /** Derived from the tenant's business profile — never editable here. */
    #[Locked] public ?float $latitude  = null;
    #[Locked] public ?float $longitude = null;

    public bool $satellite = false;

    #[Locked] public int $mapVersion = 0;
    #[Locked] public array $mapView = [
        'lat'  => 10.900977766937142,
        'lng'  => 123.07055771888716,
        'zoom' => 13,
    ];

    // ─────────────────────────────────────────────────────────
    //  Lifecycle
    // ─────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->authorizeCreateEvent();

        $tenant = Auth::user()->tenant;

        abort_unless($tenant, 403, 'No business is linked to your account.');

        $this->barangay = $tenant->barangay ?? '';

        $primary = $this->extractPrimaryCoords($tenant);

        if ($primary) {
            $this->latitude  = $primary['lat'];
            $this->longitude = $primary['lng'];
            $this->mapView   = [
                'lat'  => $this->latitude,
                'lng'  => $this->longitude,
                'zoom' => 15,
            ];
        }
    }

    public function hydrate(): void
    {
        $this->authorizeCreateEvent();
    }

    protected function authorizeCreateEvent(): void
    {
        abort_unless(
            Auth::user()?->tenant_id,
            403,
            'No business is linked to your account.'
        );

        $this->requirePermission('manage events');
    }

    // ─────────────────────────────────────────────────────────
    //  Mapcn bridge methods (Rule 36 / bug 6.144)
    // ─────────────────────────────────────────────────────────

    public function getZoom(mixed $value = null): int
    {
        return (int) ($this->mapView['zoom'] ?? 13);
    }

    /** @return array{0: float, 1: float} */
    public function getCenter(mixed $value = null): array
    {
        return [
            (float) ($this->mapView['lng'] ?? 0.0),
            (float) ($this->mapView['lat'] ?? 0.0),
        ];
    }

    public function getBearing(mixed $value = null): float
    {
        return 0.0;
    }

    public function getPitch(mixed $value = null): float
    {
        return 0.0;
    }

    // ─────────────────────────────────────────────────────────
    //  Validation
    // ─────────────────────────────────────────────────────────

    protected function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type'        => ['required', 'string', 'max:50'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active'   => ['boolean'],
            'image'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    protected function messages(): array
    {
        return [
            'image.max'   => 'The event photo must not exceed 5 MB.',
            'image.mimes' => 'The event photo must be a JPEG, PNG, or WebP image.',
        ];
    }

    public function updated(string $field): void
    {
        if (in_array($field, ['name', 'description', 'type'], true)) {
            $this->$field = trim((string) $this->$field);
        }
    }

    // ─────────────────────────────────────────────────────────
    //  Coordinate helper
    // ─────────────────────────────────────────────────────────

    /**
     * @return array{lat: float, lng: float}|null
     */
    protected function extractPrimaryCoords(?Tenant $tenant): ?array
    {
        if (!$tenant) {
            return null;
        }

        $coords = $tenant->coordinates ?? null;

        if (!is_array($coords) || empty($coords[0])) {
            return null;
        }

        $primary = $coords[0];

        if (!is_array($primary) || !isset($primary['lat'], $primary['lng'])) {
            return null;
        }

        return [
            'lat' => (float) $primary['lat'],
            'lng' => (float) $primary['lng'],
        ];
    }

    // ─────────────────────────────────────────────────────────
    //  Map
    // ─────────────────────────────────────────────────────────

    public function toggleSatellite(): void
    {
        $this->satellite = !$this->satellite;
        $this->mapVersion++;
    }

    // ─────────────────────────────────────────────────────────
    //  Save
    // ─────────────────────────────────────────────────────────

    public function save()
    {
        $this->authorizeCreateEvent();

        $this->validate();

        // Re-derive location from the tenant's profile at save time.
        $tenant = Tenant::query()
            ->select('id', 'barangay', 'coordinates')
            ->find(Auth::user()->tenant_id);

        if (!$tenant) {
            session()->flash('error', 'Your business profile could not be found. Please contact support.');
            return null;
        }

        $this->barangay = $tenant->barangay ?? $this->barangay;

        $primary     = $this->extractPrimaryCoords($tenant);
        $coordinates = $primary
            ? ['lat' => $primary['lat'], 'lng' => $primary['lng']]
            : null;

        $imagePath = null;

        try {
            if ($this->image) {
                $imagePath = $this->storeImage($this->image, 'event-images', 'public', 'event');

                if (! $imagePath) {
                    throw new \RuntimeException('Failed to store the event photo.');
                }
            }

            DB::transaction(function () use ($tenant, $coordinates, $imagePath): void {
                Event::create([
                    'tenant_id'   => $tenant->id,
                    'name'        => $this->name,
                    'barangay'    => $this->barangay,
                    'description' => $this->description ?: null,
                    'type'        => $this->type,
                    'start_date'  => $this->start_date,
                    'end_date'    => $this->end_date ?: null,
                    'is_active'   => $this->is_active,
                    'image_path'  => $imagePath,
                    'coordinates' => $coordinates,
                ]);
            });
        } catch (\Throwable $e) {
            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

            Log::error('Tenant event creation failed', [
                'tenant_id' => Auth::user()->tenant_id,
                'actor_id'  => Auth::id(),
                'error'     => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);

            session()->flash('error', 'Failed to create the event. Please try again.');
            return null;
        }

        session()->flash('message', 'Event created successfully.');
        return $this->redirectRoute('tenant.events.index', navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-6xl mx-auto space-y-6">

    
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

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
        <div class="bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl">
            <div class="flex items-start gap-2.5">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div class="text-xs sm:text-sm text-rose-800 dark:text-rose-300">
                    <p class="font-semibold mb-1">Please fix the following:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $err): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <li <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'err-'.e($loop->index).''; ?>wire:key="err-<?php echo e($loop->index); ?>"><?php echo e($err); ?></li>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-800">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Events</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                Add Event
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                Create a new event for your business.
            </p>
        </div>
        <a href="<?php echo e(route('tenant.events.index')); ?>" wire:navigate
           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                  transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span>Back to Events</span>
        </a>
    </div>

    
    <form wire:submit="save">
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

            
            <div class="lg:col-span-3 space-y-6">

                
                <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Event Details
                        </h2>
                    </div>

                    
                    <div>
                        <div class="flex justify-between items-baseline mb-1">
                            <label for="field-event-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Event Name <span class="text-rose-500">*</span>
                            </label>
                            <span class="text-[11px] text-gray-400 dark:text-gray-500 tabular-nums"><?php echo e(Str::length($name)); ?>/255</span>
                        </div>
                        <input type="text" id="field-event-name" wire:model.live.debounce.300ms="name"
                               maxlength="255" class="input w-full" placeholder="e.g. Sinulog Festival">
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
                        <label for="field-event-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Type <span class="text-rose-500">*</span>
                        </label>
                        <select id="field-event-type" wire:model="type" class="input w-full">
                            <option value="fiesta">Fiesta</option>
                            <option value="sports">Sports</option>
                            <option value="environment">Environment</option>
                            <option value="entertainment">Entertainment</option>
                            <option value="adventure">Adventure</option>
                            <option value="other">Other</option>
                        </select>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    
                    <div>
                        <div class="flex justify-between items-baseline mb-1">
                            <label for="field-event-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Description
                            </label>
                            <span class="text-[11px] text-gray-400 dark:text-gray-500 tabular-nums"><?php echo e(Str::length($description)); ?>/1000</span>
                        </div>
                        <textarea id="field-event-description" wire:model.live.debounce.300ms="description"
                                  rows="5" class="input w-full" maxlength="1000"
                                  placeholder="Describe the event…"></textarea>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="field-event-start" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Start Date <span class="text-rose-500">*</span>
                            </label>
                            <input type="datetime-local" id="field-event-start" wire:model="start_date" class="input w-full">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['start_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div>
                            <label for="field-event-end" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                End Date <span class="text-gray-400 dark:text-gray-500 font-normal">(optional)</span>
                            </label>
                            <input type="datetime-local" id="field-event-end" wire:model="end_date" class="input w-full">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['end_date'];
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

                
                <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Publishing
                        </h2>
                    </div>

                    <label class="flex items-center gap-3 cursor-pointer select-none">
                        <span class="relative inline-flex items-center shrink-0">
                            <input type="checkbox" wire:model="is_active" class="sr-only peer">
                            <span class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full
                                         peer peer-checked:bg-primary-600
                                         after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                         after:bg-white after:rounded-full after:h-5 after:w-5
                                         after:transition-all peer-checked:after:translate-x-full"></span>
                        </span>
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            Active
                            <span class="text-gray-400 dark:text-gray-500">— visible to customers</span>
                        </span>
                    </label>
                </div>

            </div>

            
            <div class="lg:col-span-2 space-y-6">

                
                <div
                    x-data="avatarPreview()"
                    x-on:event-image-preview.window="setUrl($event.detail.url)"
                    x-on:event-image-cleared.window="clear()"
                    class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-4"
                >
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Event Photo
                        </h2>
                    </div>

                    
                    <div
                        x-data="{
                            ...imageCropper({
                                wireProperty: 'image',
                                aspect: 16 / 9,
                                title: 'Crop event photo',
                                description: 'Wide 16:9 crop works best',
                                previewEvent: 'event-image-preview',
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
                            : 'border-gray-300 dark:border-gray-600 hover:border-primary-500/50'"
                        class="relative w-full aspect-video border-2 border-dashed rounded-xl overflow-hidden transition-colors"
                    >
                        <input
                            x-ref="input"
                            id="event-image-input"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            x-on:change="pick($event)"
                        >

                        <img
                            :src="previewUrl || ''"
                            :class="previewUrl ? 'block' : 'hidden'"
                            alt="Event photo preview"
                            class="absolute inset-0 w-full h-full object-cover"
                            loading="lazy"
                            decoding="async"
                        >

                        <label
                            for="event-image-input"
                            :class="previewUrl ? 'hidden' : 'flex'"
                            class="absolute inset-0 flex-col items-center justify-center p-4 text-center cursor-pointer"
                        >
                            <svg class="h-8 w-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="mt-2 text-xs font-medium text-gray-700 dark:text-gray-300 leading-tight">
                                Click or drop a photo
                            </p>
                            <p class="mt-0.5 text-[10px] text-gray-400 dark:text-gray-500">
                                16:9 · auto-compressed
                            </p>
                        </label>

                        <div
                            wire:loading.flex
                            wire:target="image"
                            class="absolute inset-0 bg-black/45 backdrop-blur-[2px] items-center justify-center pointer-events-none"
                            aria-hidden="true"
                        >
                            <svg class="animate-spin h-5 w-5 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </div>
                    </div>

                    <p class="text-[11px] text-gray-500 dark:text-gray-400">
                        PNG, JPG, or WebP · max 5 MB · compressed to ≤2 MB.
                    </p>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['image'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <div :class="previewUrl ? 'flex' : 'hidden'" class="items-center gap-2">
                        <label for="event-image-input"
                               class="flex-1 inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg
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
                                wire:click="$set('image', null)"
                                x-on:click="$dispatch('event-image-cleared')"
                                class="flex-1 inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg
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

                
                <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-4">
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Location
                        </h2>
                    </div>

                    <?php $tenant = Auth::user()->tenant; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant): ?>
                        
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/60 overflow-hidden">

                            
                            <div class="flex items-start gap-3 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                                <div class="p-1.5 rounded-md bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Your Business</p>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white mt-0.5 truncate"><?php echo e($tenant->name); ?></p>
                                </div>
                            </div>

                            <dl class="divide-y divide-gray-200 dark:divide-gray-700">
                                
                                <div class="flex items-start gap-2.5 px-4 py-2.5">
                                    <div class="p-1 rounded-md bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v11a2 2 0 002 2h14a2 2 0 002-2V7M3 7l9-4 9 4M3 7h18"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Barangay</dt>
                                        <dd class="text-xs font-semibold text-gray-900 dark:text-white mt-0.5"><?php echo e($barangay ?: 'Not set'); ?></dd>
                                    </div>
                                </div>

                                
                                <div class="flex items-start gap-2.5 px-4 py-2.5">
                                    <div class="p-1 rounded-md bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Address</dt>
                                        <dd class="text-xs text-gray-900 dark:text-white mt-0.5 leading-snug"><?php echo e($tenant->address ?: 'No address on file'); ?></dd>
                                    </div>
                                </div>

                                
                                <div class="flex items-start gap-2.5 px-4 py-2.5">
                                    <div class="p-1 rounded-md bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Coordinates</dt>
                                        <dd class="text-[11px] font-mono text-gray-900 dark:text-white mt-0.5 tabular-nums">
                                            <?php echo e($latitude !== null && $longitude !== null
                                                ? number_format($latitude, 5) . ', ' . number_format($longitude, 5)
                                                : 'Not set'); ?>

                                        </dd>
                                    </div>
                                </div>
                            </dl>
                        </div>

                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($latitude !== null && $longitude !== null): ?>
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                    Fixed to your business location.
                                </p>
                                <button type="button"
                                        wire:click="toggleSatellite"
                                        class="inline-flex items-center justify-center gap-1.5 h-8 px-3 rounded-lg
                                               border border-gray-300 dark:border-gray-600
                                               bg-white dark:bg-gray-800
                                               text-gray-700 dark:text-gray-200
                                               text-[11px] font-semibold
                                               transition-all duration-200 active:scale-95
                                               hover:bg-gray-50 dark:hover:bg-gray-700
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                                    </svg>
                                    <span><?php echo e($satellite ? 'Street' : 'Satellite'); ?></span>
                                </button>
                            </div>

                            <div class="rounded-xl overflow-hidden border border-gray-200/80 dark:border-gray-700/80 relative"
                                 style="height: 240px;">
                                <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'event-location-preview-'.e($satellite ? 'sat' : 'std').'-'.e($mapVersion).''; ?>wire:key="event-location-preview-<?php echo e($satellite ? 'sat' : 'std'); ?>-<?php echo e($mapVersion); ?>">
                                    <?php if (isset($component)) { $__componentOriginal200d48706721e15bf0ceea6c3e5dfc4d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal200d48706721e15bf0ceea6c3e5dfc4d = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\Map::resolve(['center' => [(float) $mapView['lng'], (float) $mapView['lat']],'zoom' => $mapView['zoom'],'height' => '240px','provider' => $satellite ? 'custom' : 'carto-voyager','style' => $satellite ? route('map.satellite.style') : null,'lightStyle' => $satellite ? route('map.satellite.style') : null,'darkStyle' => $satellite ? route('map.satellite.style') : null,'theme' => 'auto','class' => 'h-full w-full','interactive' => false] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('map'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\Map::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'event-location-preview']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                                        <?php if (isset($component)) { $__componentOriginal30d4ce5150bc700b8142cf87b21ef225 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal30d4ce5150bc700b8142cf87b21ef225 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MapControls::resolve(['zoom' => false,'compass' => false,'locate' => false,'fullscreen' => true,'scale' => false,'position' => 'top-right'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('map-controls'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MapControls::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal30d4ce5150bc700b8142cf87b21ef225)): ?>
<?php $attributes = $__attributesOriginal30d4ce5150bc700b8142cf87b21ef225; ?>
<?php unset($__attributesOriginal30d4ce5150bc700b8142cf87b21ef225); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal30d4ce5150bc700b8142cf87b21ef225)): ?>
<?php $component = $__componentOriginal30d4ce5150bc700b8142cf87b21ef225; ?>
<?php unset($__componentOriginal30d4ce5150bc700b8142cf87b21ef225); ?>
<?php endif; ?>

                                        <?php if (isset($component)) { $__componentOriginalfdc07447b73c389f668e824ec2f32988 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfdc07447b73c389f668e824ec2f32988 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MapMarker::resolve(['lat' => $latitude,'lng' => $longitude,'color' => '#ef4444','id' => 'event-preview-marker','draggable' => false] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('map-marker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MapMarker::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:key' => 'event-preview-marker']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                                            <?php if (isset($component)) { $__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MarkerContent::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('marker-content'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MarkerContent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                                                
                                                <div class="relative flex items-center justify-center transform-gpu will-change-transform">
                                                    <svg class="h-9 w-9 drop-shadow-lg" viewBox="0 0 24 24" fill="#ef4444" stroke="white" stroke-width="1.5" aria-hidden="true">
                                                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                                                        <circle cx="12" cy="9" r="2.5" fill="white"/>
                                                    </svg>
                                                </div>
                                             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5)): ?>
<?php $attributes = $__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5; ?>
<?php unset($__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5)): ?>
<?php $component = $__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5; ?>
<?php unset($__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5); ?>
<?php endif; ?>
                                            <?php if (isset($component)) { $__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MarkerPopup::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('marker-popup'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MarkerPopup::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                                                <div class="p-3 min-w-[200px]">
                                                    <strong class="text-gray-900 dark:text-white text-sm block"><?php echo e($tenant->name); ?></strong>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($barangay): ?>
                                                        <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                                            <span class="font-semibold"><?php echo e($barangay); ?></span>
                                                        </p>
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->address): ?>
                                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 leading-snug"><?php echo e($tenant->address); ?></p>
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                    <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-2 font-mono tabular-nums">
                                                        <?php echo e(number_format($latitude, 5)); ?>, <?php echo e(number_format($longitude, 5)); ?>

                                                    </p>
                                                </div>
                                             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce)): ?>
<?php $attributes = $__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce; ?>
<?php unset($__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce)): ?>
<?php $component = $__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce; ?>
<?php unset($__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce); ?>
<?php endif; ?>
                                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalfdc07447b73c389f668e824ec2f32988)): ?>
<?php $attributes = $__attributesOriginalfdc07447b73c389f668e824ec2f32988; ?>
<?php unset($__attributesOriginalfdc07447b73c389f668e824ec2f32988); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalfdc07447b73c389f668e824ec2f32988)): ?>
<?php $component = $__componentOriginalfdc07447b73c389f668e824ec2f32988; ?>
<?php unset($__componentOriginalfdc07447b73c389f668e824ec2f32988); ?>
<?php endif; ?>
                                     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal200d48706721e15bf0ceea6c3e5dfc4d)): ?>
<?php $attributes = $__attributesOriginal200d48706721e15bf0ceea6c3e5dfc4d; ?>
<?php unset($__attributesOriginal200d48706721e15bf0ceea6c3e5dfc4d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal200d48706721e15bf0ceea6c3e5dfc4d)): ?>
<?php $component = $__componentOriginal200d48706721e15bf0ceea6c3e5dfc4d; ?>
<?php unset($__componentOriginal200d48706721e15bf0ceea6c3e5dfc4d); ?>
<?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="rounded-xl border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/5 p-3 flex items-start gap-2.5">
                                <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <div>
                                    <p class="text-xs font-semibold text-amber-800 dark:text-amber-200">No location set</p>
                                    <p class="text-[11px] text-amber-700 dark:text-amber-300 mt-0.5 leading-relaxed">
                                        The event will be created without a map position. Set your location in
                                        <a href="<?php echo e(route('tenant.settings.index')); ?>" wire:navigate class="font-semibold underline hover:no-underline">Business Profile</a>.
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php else: ?>
                        <div class="rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 p-6 text-center">
                            <div class="mx-auto p-2 rounded-xl bg-gray-100 dark:bg-gray-800 w-fit text-gray-400 dark:text-gray-500 mb-2">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>
                            <p class="text-xs font-semibold text-gray-900 dark:text-white">No business profile</p>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                                Contact the platform administrator.
                            </p>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

            </div>
        </div>

        
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3 pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
            <a href="<?php echo e(route('tenant.events.index')); ?>" wire:navigate
               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                      transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                <span>Cancel</span>
            </a>

            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                           disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="save">Save Event</span>
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
</div><?php /**PATH C:\laragon\www\Capstone\resources\views\tenant\pages\event\⚡create-event.blade.php ENDPATH**/ ?>