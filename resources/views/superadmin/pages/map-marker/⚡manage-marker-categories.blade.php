{{-- resources/views/superadmin/pages/marker-category/⚡manage-marker-categories.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\SiteSetting;
use App\Services\SvgSanitizerService;

new
#[Layout('superadmin.layouts.app')]
#[Title('Manage Marker Categories')]
class extends Component
{
    use WithFileUploads;

    public array $categories = [];

    // New Category Properties
    public string $newKey = '';
    public string $newLabel = '';
    public string $newColor = '#3b82f6';
    public $newIcon;

    public function mount(): void
    {
        // Defensive: the route already uses IsSuperAdmin middleware.
        if (!Auth::user()?->hasRole('super-admin')) {
            abort(403, 'Super-admin access only.');
        }

        $loaded = SiteSetting::getValue('marker_categories', []);

        // Normalize each category so the view can rely on the shape
        $this->categories = collect($loaded)->map(function (array $cat) {
            return [
                'key'       => $cat['key']       ?? '',
                'label'     => $cat['label']     ?? '',
                'color'     => $cat['color']     ?? '#3b82f6',
                'icon_path' => $cat['icon_path'] ?? null,
                'icon_svg'  => $cat['icon_svg']  ?? null,
                'is_active' => $cat['is_active'] ?? true,
            ];
        })->values()->toArray();
    }

    public function updated(string $property): void
    {
        // Auto-trim the text-based new-category fields
        if (in_array($property, ['newKey', 'newLabel', 'newColor'], true)) {
            $this->$property = trim((string) $this->$property);
        }

        // Auto-trim labels when editing inline
        if (preg_match('/^categories\.\d+\.label$/', $property)) {
            $index = (int) explode('.', $property)[1];
            if (isset($this->categories[$index])) {
                $this->categories[$index]['label'] = trim((string) $this->categories[$index]['label']);
            }
        }

        if (preg_match('/^categories\.\d+\.color$/', $property)) {
            $index = (int) explode('.', $property)[1];
            if (isset($this->categories[$index])) {
                $this->categories[$index]['color'] = trim((string) $this->categories[$index]['color']);
            }
        }
    }

    public function addCategory(): void
    {
        if (!Auth::user()?->hasRole('super-admin')) {
            abort(403);
        }

        $this->newKey   = trim($this->newKey);
        $this->newLabel = trim($this->newLabel);
        $this->newColor = trim($this->newColor);

        $this->validate([
            'newKey'   => 'required|alpha_dash|max:50',
            'newLabel' => 'required|string|max:100',
            'newColor' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            'newIcon'  => 'nullable|file|mimes:svg|max:1024',
        ]);

        // Case-insensitive duplicate check on the key
        $keyLower = strtolower($this->newKey);
        if (collect($this->categories)->contains(fn ($c) => strtolower($c['key'] ?? '') === $keyLower)) {
            $this->addError('newKey', 'This key already exists.');
            return;
        }

        $iconPath = null;
        $iconSvg  = null;

        try {
            if ($this->newIcon) {
                $iconPath = $this->newIcon->store('marker-icons', 'public');

                // Read raw bytes, then sanitize before persisting. The
                // sanitizer strips <script>, on* handlers, foreignObject,
                // and javascript: URLs — so a malicious upload can never
                // execute when the stored SVG is rendered.
                $rawSvg  = @file_get_contents($this->newIcon->getRealPath()) ?: '';
                $clean   = app(SvgSanitizerService::class)->sanitize($rawSvg);
                $iconSvg = $clean !== '' ? $clean : null;
            }

            $this->categories[] = [
                'key'       => $this->newKey,
                'label'     => $this->newLabel,
                'color'     => $this->newColor,
                'icon_path' => $iconPath,
                'icon_svg'  => $iconSvg,
                'is_active' => true,
            ];

            $this->saveCategories();
        } catch (\Exception $e) {
            // Clean up the newly stored icon if the save failed
            if ($iconPath && Storage::disk('public')->exists($iconPath)) {
                Storage::disk('public')->delete($iconPath);
            }

            Log::error('Marker category creation failed: ' . $e->getMessage(), [
                'key' => $this->newKey,
            ]);
            $this->addError('newKey', 'Failed to save the category. Please try again.');
            return;
        }

        $this->reset(['newKey', 'newLabel', 'newColor', 'newIcon']);
        $this->newColor = '#3b82f6';
        $this->dispatch('toast', message: 'Category added successfully.', type: 'success');
    }

    public function updateCategory(int $index): void
    {
        if (!Auth::user()?->hasRole('super-admin')) {
            abort(403);
        }

        if (!isset($this->categories[$index])) {
            $this->dispatch('toast', message: 'Category no longer exists.', type: 'error');
            return;
        }

        $this->validate([
            "categories.$index.label"     => 'required|string|max:100',
            "categories.$index.color"     => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            "categories.$index.icon_file" => 'nullable|file|mimes:svg|max:1024',
        ]);

        $newIconPath = null;
        $oldIconPath = $this->categories[$index]['icon_path'] ?? null;

        try {
            if (isset($this->categories[$index]['icon_file']) && $this->categories[$index]['icon_file']) {
                $file        = $this->categories[$index]['icon_file'];
                $newIconPath = $file->store('marker-icons', 'public');

                // Sanitize before persisting — same contract as addCategory().
                $rawSvg  = @file_get_contents($file->getRealPath()) ?: '';
                $clean   = app(SvgSanitizerService::class)->sanitize($rawSvg);
                $iconSvg = $clean !== '' ? $clean : null;

                $this->categories[$index]['icon_path'] = $newIconPath;
                $this->categories[$index]['icon_svg']  = $iconSvg;
                unset($this->categories[$index]['icon_file']);
            }

            $this->saveCategories();
        } catch (\Exception $e) {
            if ($newIconPath && Storage::disk('public')->exists($newIconPath)) {
                Storage::disk('public')->delete($newIconPath);
            }

            Log::error('Marker category update failed: ' . $e->getMessage(), [
                'index' => $index,
                'key'   => $this->categories[$index]['key'] ?? null,
            ]);
            $this->addError("categories.$index.label", 'Failed to save. Please try again.');
            return;
        }

        // Delete old icon only after successful save (if it was replaced)
        if ($newIconPath && $oldIconPath && Storage::disk('public')->exists($oldIconPath)) {
            Storage::disk('public')->delete($oldIconPath);
        }

        $this->dispatch('toast', message: 'Category updated successfully.', type: 'success');
    }

    public function removeCategory(int $index): void
    {
        if (!Auth::user()?->hasRole('super-admin')) {
            abort(403);
        }

        if (!isset($this->categories[$index])) {
            $this->dispatch('toast', message: 'Category no longer exists.', type: 'error');
            return;
        }

        $iconPath = $this->categories[$index]['icon_path'] ?? null;
        $key      = $this->categories[$index]['key'] ?? null;

        try {
            unset($this->categories[$index]);
            $this->categories = array_values($this->categories);
            $this->saveCategories();
        } catch (\Exception $e) {
            Log::error('Marker category removal failed: ' . $e->getMessage(), [
                'key' => $key,
            ]);
            $this->dispatch('toast', message: 'Failed to remove category. Please try again.', type: 'error');
            return;
        }

        // Delete the icon file only after the save succeeded
        if ($iconPath && Storage::disk('public')->exists($iconPath)) {
            Storage::disk('public')->delete($iconPath);
        }

        $this->dispatch('toast', message: 'Category removed.', type: 'success');
    }

    public function toggleActive(int $index): void
    {
        if (!Auth::user()?->hasRole('super-admin')) {
            abort(403);
        }

        if (!isset($this->categories[$index])) {
            $this->dispatch('toast', message: 'Category no longer exists.', type: 'error');
            return;
        }

        $this->categories[$index]['is_active'] = !($this->categories[$index]['is_active'] ?? true);

        try {
            $this->saveCategories();
        } catch (\Exception $e) {
            // Revert on failure
            $this->categories[$index]['is_active'] = !$this->categories[$index]['is_active'];

            Log::error('Marker category toggle failed: ' . $e->getMessage(), [
                'index' => $index,
            ]);
            $this->dispatch('toast', message: 'Failed to update category. Please try again.', type: 'error');
            return;
        }

        $status = $this->categories[$index]['is_active'] ? 'enabled' : 'disabled';
        $this->dispatch('toast', message: "Category {$status}.", type: 'success');
    }

    protected function saveCategories(): void
    {
        DB::transaction(function () {
            // Strip non-persistent fields (icon_file is a temporary Livewire upload)
            $persistable = array_map(function (array $cat) {
                return [
                    'key'       => $cat['key']       ?? '',
                    'label'     => $cat['label']     ?? '',
                    'color'     => $cat['color']     ?? '#3b82f6',
                    'icon_path' => $cat['icon_path'] ?? null,
                    'icon_svg'  => $cat['icon_svg']  ?? null,
                    'is_active' => (bool) ($cat['is_active'] ?? true),
                ];
            }, $this->categories);

            SiteSetting::setValue('marker_categories', $persistable);
        });
    }

    /**
     * Marker-category counters.
     *
     * @return array{total: int, active: int, inactive: int}
     */
    #[Computed]
    public function stats(): array
    {
        $total    = count($this->categories);
        $inactive = collect($this->categories)->filter(fn ($c) => !($c['is_active'] ?? true))->count();

        return [
            'total'    => $total,
            'active'   => $total - $inactive,
            'inactive' => $inactive,
        ];
    }
};
?>

@php $stats = $this->stats; @endphp

<div class="p-4 sm:p-6 lg:p-8 max-w-5xl mx-auto space-y-6">

    {{-- ═══ Page header ═══ --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Map Management</span>
            </div>
            <h1 class="font-display text-3xl md:text-4xl font-semibold text-gray-900 dark:text-white">
                Marker <em class="italic text-primary-600 dark:text-primary-400">Categories</em>
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-2">
                Manage the categories used for sub-locations across the map.
            </p>
        </div>
    </div>

    {{-- ═══ Add New Category ═══ --}}
    <form wire:submit="addCategory"
          class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">

        <div class="flex items-center gap-3">
            <span class="w-5 h-px bg-primary-600"></span>
            <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Add New Category
            </h2>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-start">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Key (slug) <span class="text-rose-500">*</span>
                </label>
                <input type="text"
                       wire:model="newKey"
                       maxlength="50"
                       autocomplete="off"
                       class="input w-full font-mono text-sm"
                       placeholder="e.g. restaurant">
                @error('newKey') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Label <span class="text-rose-500">*</span>
                </label>
                <input type="text"
                       wire:model="newLabel"
                       maxlength="100"
                       autocomplete="off"
                       class="input w-full"
                       placeholder="e.g. Restaurant">
                @error('newLabel') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Color <span class="text-rose-500">*</span>
                </label>
                <div class="flex items-center gap-2">
                    <input type="color"
                           wire:model="newColor"
                           aria-label="Pick a color"
                           class="h-11 w-14 rounded-lg border border-gray-300 dark:border-gray-600 cursor-pointer p-1 shrink-0 bg-white dark:bg-gray-800">
                    <input type="text"
                           wire:model="newColor"
                           maxlength="7"
                           autocomplete="off"
                           class="input w-full uppercase font-mono text-sm"
                           placeholder="#000000">
                </div>
                @error('newColor') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Icon (SVG) <span class="text-gray-400 dark:text-gray-500 font-normal">(optional)</span>
                </label>
                <input type="file"
                       wire:model="newIcon"
                       accept=".svg,image/svg+xml"
                       class="block w-full text-sm text-gray-500 dark:text-gray-400
                              file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0
                              file:text-xs file:font-semibold
                              file:bg-primary-50 dark:file:bg-primary-500/15
                              file:text-primary-700 dark:file:text-primary-300
                              hover:file:bg-primary-100 dark:hover:file:bg-primary-500/25
                              transition cursor-pointer
                              focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                @error('newIcon') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror

                <div wire:loading wire:target="newIcon" class="mt-1.5 text-xs text-primary-600 dark:text-primary-400 inline-flex items-center gap-1.5">
                    <svg class="animate-spin h-3 w-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Uploading…
                </div>
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="addCategory"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                           disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="addCategory" class="inline-flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Category
                </span>
                <span wire:loading wire:target="addCategory" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Adding…
                </span>
            </button>
        </div>
    </form>

    {{-- ═══ Existing Categories ═══ --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between px-1">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Manage Categories
                </h2>
            </div>
            @if($stats['total'] > 0)
                <span class="text-xs text-gray-500 dark:text-gray-400 tabular-nums">
                    <strong class="text-gray-900 dark:text-white font-semibold">{{ $stats['total'] }}</strong> total
                    @if($stats['inactive'] > 0)
                        · <span class="text-amber-600 dark:text-amber-400 font-semibold">{{ $stats['inactive'] }} hidden</span>
                    @endif
                </span>
            @endif
        </div>

        @forelse($categories as $index => $cat)
            @php $isActive = $cat['is_active'] ?? true; @endphp
            <div wire:key="category-{{ $cat['key'] ?: 'idx-' . $loop->index }}"
                 class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 flex flex-col lg:flex-row lg:items-center gap-5 transition-all duration-200 hover:shadow-md {{ !$isActive ? 'opacity-70 bg-gray-50 dark:bg-gray-800/40' : '' }}">

                {{-- Info & Icon --}}
                <div class="flex items-center gap-4 lg:w-1/4 shrink-0">
                    <div class="h-12 w-12 rounded-xl flex items-center justify-center shrink-0 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2 shadow-sm">
                        @if(!empty($cat['icon_path']) && Storage::disk('public')->exists($cat['icon_path']))
                            {{-- Rule J: relative /storage path, never Storage::url() --}}
                            <img src="/storage/{{ ltrim($cat['icon_path'], '/') }}"
                                 class="w-full h-full object-contain"
                                 alt="{{ $cat['label'] }}"
                                 loading="lazy"
                                 decoding="async">
                        @elseif(!empty($cat['icon_svg']))
                            <div class="w-full h-full text-gray-700 dark:text-gray-300">
                                <x-safe-svg :svg="$cat['icon_svg']" class="w-full h-full stroke-current fill-none" />
                            </div>
                        @else
                            <div class="w-4 h-4 rounded-full shadow-sm" style="background-color: {{ $cat['color'] }};"></div>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 dark:text-white truncate">{{ $cat['label'] }}</p>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 font-mono bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded mt-0.5 inline-block">
                            {{ $cat['key'] }}
                        </p>
                    </div>
                </div>

                {{-- Edit Fields --}}
                <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-3 items-start">
                    <div>
                        <input type="text"
                               wire:model="categories.{{ $index }}.label"
                               maxlength="100"
                               class="input w-full text-sm"
                               placeholder="Label">
                        @error("categories.$index.label") <span class="text-rose-500 dark:text-rose-400 text-[10px] block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <div class="flex items-center gap-2">
                            <input type="color"
                                   wire:model="categories.{{ $index }}.color"
                                   aria-label="Color for {{ $cat['label'] }}"
                                   class="h-11 w-12 rounded-lg border border-gray-300 dark:border-gray-600 cursor-pointer p-0.5 shrink-0 bg-white dark:bg-gray-800">
                            <input type="text"
                                   wire:model="categories.{{ $index }}.color"
                                   maxlength="7"
                                   class="input w-full text-sm uppercase font-mono px-2"
                                   placeholder="#000000">
                        </div>
                        @error("categories.$index.color") <span class="text-rose-500 dark:text-rose-400 text-[10px] block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <input type="file"
                               wire:model="categories.{{ $index }}.icon_file"
                               accept=".svg,image/svg+xml"
                               class="block w-full text-xs text-gray-500 dark:text-gray-400
                                      file:mr-2 file:py-1.5 file:px-2.5 file:rounded-lg file:border-0
                                      file:text-xs file:font-medium
                                      file:bg-gray-100 dark:file:bg-gray-700
                                      file:text-gray-700 dark:file:text-gray-300
                                      hover:file:bg-gray-200 dark:hover:file:bg-gray-600
                                      cursor-pointer
                                      focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        @error("categories.$index.icon_file") <span class="text-rose-500 dark:text-rose-400 text-[10px] block mt-1">{{ $message }}</span> @enderror

                        <div wire:loading wire:target="categories.{{ $index }}.icon_file" class="mt-1 text-[10px] text-primary-600 dark:text-primary-400">
                            Uploading…
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center justify-between lg:justify-end gap-3 shrink-0 pt-4 lg:pt-0 border-t lg:border-t-0 border-gray-100 dark:border-gray-700/60">

                    {{-- Toggle Active --}}
                    <button type="button"
                            wire:click="toggleActive({{ $index }})"
                            wire:loading.attr="disabled"
                            wire:target="toggleActive"
                            aria-label="{{ $isActive ? 'Deactivate' : 'Activate' }} {{ $cat['label'] }}"
                            class="inline-flex items-center gap-2 px-2 py-1 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white
                                   transition-colors
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <div class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors {{ $isActive ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-600' }}">
                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm transition-transform {{ $isActive ? 'translate-x-4' : 'translate-x-1' }}"></span>
                        </div>
                        <span class="text-xs font-medium">{{ $isActive ? 'Active' : 'Hidden' }}</span>
                    </button>

                    <div class="flex items-center gap-1.5">
                        {{-- Save --}}
                        <button type="button"
                                wire:click="updateCategory({{ $index }})"
                                wire:loading.attr="disabled"
                                wire:target="updateCategory"
                                class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold shadow-sm
                                       transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                       disabled:opacity-60 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="updateCategory">Save</span>
                            <span wire:loading wire:target="updateCategory" class="inline-flex items-center gap-1.5">
                                <svg class="animate-spin h-3 w-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Saving…
                            </span>
                        </button>

                        {{-- Delete — deferred: still uses native confirm() pending two-click-arm conversion --}}
                        <button type="button"
                                x-on:click="if (confirm('Delete \'{{ addslashes($cat['label']) }}\'? Markers using this key will fall back to Uncategorized.')) $wire.removeCategory({{ $index }})"
                                wire:loading.attr="disabled"
                                wire:target="removeCategory"
                                aria-label="Delete {{ $cat['label'] }}"
                                class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg
                                       border border-rose-300 dark:border-rose-500/40
                                       bg-white dark:bg-gray-800 text-rose-700 dark:text-rose-300
                                       text-xs font-semibold
                                       transition-all duration-200 active:scale-95
                                       hover:bg-rose-50 dark:hover:bg-rose-500/10
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                                       disabled:opacity-60 disabled:cursor-not-allowed">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-10 flex flex-col items-center justify-center text-center">
                <div class="p-3 bg-gray-100 dark:bg-gray-800 rounded-2xl mb-3 text-gray-400 dark:text-gray-500">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">No marker categories yet</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Get started by adding your first category above.</p>
            </div>
        @endforelse
    </div>

    {{-- Toast Notifications --}}
    <div x-data="{ toasts: [] }"
         x-on:toast.window="
             const id = Date.now() + Math.random();
             toasts.push({ id, message: $event.detail.message, type: $event.detail.type || 'info' });
             setTimeout(() => { toasts = toasts.filter(t => t.id !== id) }, 4000);
         "
         class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 w-full max-w-sm pointer-events-none"
         aria-live="polite"
         role="status">
        <template x-for="toast in toasts" :key="toast.id">
            <div class="pointer-events-auto rounded-xl px-4 py-3 shadow-lg text-sm font-medium flex items-center gap-2 border"
                 :class="{
                     'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-300': toast.type === 'success',
                     'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-500/10 dark:border-rose-500/30 dark:text-rose-300': toast.type === 'error',
                     'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-500/10 dark:border-blue-500/30 dark:text-blue-300': toast.type === 'info',
                 }">
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>
</div>