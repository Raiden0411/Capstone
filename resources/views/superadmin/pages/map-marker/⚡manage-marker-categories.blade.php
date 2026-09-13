{{-- resources/views/superadmin/pages/map-marker/⚡manage-marker-categories.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\SiteSetting;

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
                // Read the raw SVG contents for inline rendering later
                $iconSvg = file_get_contents($this->newIcon->getRealPath());
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
                $iconSvg     = file_get_contents($file->getRealPath());

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
     * @return array{total: int, active: int, inactive: int}
     */
    public function getStatsProperty(): array
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

<div class="p-4 sm:p-6 lg:p-8 max-w-5xl mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-800">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Marker Categories</h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">Manage the categories used for sub-locations across the map.</p>
        </div>
    </div>

    {{-- Add New Category Form --}}
    <form wire:submit="addCategory" class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6 space-y-6">
        <div class="flex items-center gap-2">
            <span class="w-5 h-px bg-primary-600"></span>
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Add New Category</h2>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 items-start">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Key (slug) <span class="text-red-500">*</span></label>
                <input type="text" wire:model="newKey" class="input" placeholder="e.g. restaurant">
                @error('newKey') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Label <span class="text-red-500">*</span></label>
                <input type="text" wire:model="newLabel" class="input" placeholder="e.g. Restaurant">
                @error('newLabel') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Color <span class="text-red-500">*</span></label>
                <div class="flex items-center gap-2">
                    <input type="color" wire:model="newColor" class="h-10 w-14 rounded-lg border border-gray-300 dark:border-gray-600 cursor-pointer p-1 shrink-0">
                    <input type="text" wire:model="newColor" class="input uppercase font-mono text-sm" placeholder="#000000">
                </div>
                @error('newColor') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Icon (SVG)</label>
                <input type="file" wire:model="newIcon" accept=".svg"
                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-primary-50 dark:file:bg-primary-500/20 file:text-primary-700 dark:file:text-primary-300 hover:file:bg-primary-100 dark:hover:file:bg-primary-500/30 transition cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                @error('newIcon') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror

                <div wire:loading wire:target="newIcon" class="mt-1 text-xs text-primary-600 dark:text-primary-400 flex items-center gap-1">
                    <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Uploading…
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled" wire:target="addCategory"
                    class="btn-primary active:scale-95 transition-transform inline-flex items-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="addCategory">Add Category</span>
                <span wire:loading wire:target="addCategory" class="inline-flex items-center gap-1.5">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Adding…
                </span>
            </button>
        </div>
    </form>

    {{-- Existing Categories List --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between px-1">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Manage Categories</h2>
            @php $stats = $this->stats; @endphp
            @if($stats['total'] > 0)
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    <strong class="text-gray-900 dark:text-white">{{ $stats['total'] }}</strong> total
                    @if($stats['inactive'] > 0)
                        · <span class="text-amber-600 dark:text-amber-400">{{ $stats['inactive'] }} hidden</span>
                    @endif
                </span>
            @endif
        </div>

        @forelse($categories as $index => $cat)
            @php
                $isActive = $cat['is_active'] ?? true;
            @endphp
            <div wire:key="category-{{ $cat['key'] ?? $index }}"
                 class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 flex flex-col lg:flex-row lg:items-center gap-5 transition-all duration-200 hover:shadow-md {{ !$isActive ? 'opacity-60 bg-gray-50 dark:bg-gray-800/50' : '' }}">

                {{-- Info & Icon --}}
                <div class="flex items-center gap-4 lg:w-1/4 shrink-0">
                    <div class="h-12 w-12 rounded-xl flex items-center justify-center shrink-0 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2 shadow-sm">
                        @if(!empty($cat['icon_path']) && Storage::disk('public')->exists($cat['icon_path']))
                            <img src="{{ Storage::url($cat['icon_path']) }}" class="w-full h-full object-contain" alt="{{ $cat['label'] }}">
                        @elseif(!empty($cat['icon_svg']))
                            <div class="w-full h-full text-gray-700 dark:text-gray-300">
                                {!! str_replace('<svg ', '<svg class="w-full h-full stroke-current fill-none" ', $cat['icon_svg']) !!}
                            </div>
                        @else
                            <div class="w-4 h-4 rounded-full" style="background-color: {{ $cat['color'] }};"></div>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="font-bold text-gray-900 dark:text-white truncate">{{ $cat['label'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-mono bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded mt-0.5 inline-block">{{ $cat['key'] }}</p>
                    </div>
                </div>

                {{-- Edit Fields --}}
                <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-4 items-start">
                    <div>
                        <input type="text" wire:model="categories.{{ $index }}.label" class="input text-sm" placeholder="Label">
                        @error("categories.$index.label") <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <div class="flex items-center gap-2">
                            <input type="color" wire:model="categories.{{ $index }}.color" class="h-9 w-12 rounded border border-gray-300 dark:border-gray-600 cursor-pointer p-0.5 shrink-0">
                            <input type="text" wire:model="categories.{{ $index }}.color" class="input text-sm uppercase font-mono px-2" placeholder="#000000">
                        </div>
                        @error("categories.$index.color") <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <input type="file" wire:model="categories.{{ $index }}.icon_file" accept=".svg"
                               class="block w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-gray-100 dark:file:bg-gray-700 file:text-gray-700 dark:file:text-gray-300 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        @error("categories.$index.icon_file") <span class="text-red-500 text-[10px] block mt-1">{{ $message }}</span> @enderror

                        <div wire:loading wire:target="categories.{{ $index }}.icon_file" class="mt-1 text-[10px] text-primary-600 dark:text-primary-400">
                            Uploading…
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center justify-between lg:justify-end gap-4 shrink-0 pt-4 lg:pt-0 border-t lg:border-t-0 border-gray-100 dark:border-gray-700">

                    {{-- Toggle Active --}}
                    <button type="button" wire:click="toggleActive({{ $index }})"
                            wire:loading.attr="disabled"
                            wire:target="toggleActive({{ $index }})"
                            class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded-lg px-2 py-1 disabled:opacity-60">
                        <div class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors {{ $isActive ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600' }}">
                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform {{ $isActive ? 'translate-x-4' : 'translate-x-1' }}"></span>
                        </div>
                        <span class="text-xs font-medium">{{ $isActive ? 'Active' : 'Hidden' }}</span>
                    </button>

                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="updateCategory({{ $index }})" wire:loading.attr="disabled" wire:target="updateCategory({{ $index }})"
                                class="px-3 py-1.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold shadow-sm transition active:scale-95 focus:outline-none focus:ring-2 focus:ring-primary-500/50 disabled:opacity-60 inline-flex items-center gap-1.5">
                            <span wire:loading.remove wire:target="updateCategory({{ $index }})">Save</span>
                            <span wire:loading wire:target="updateCategory({{ $index }})" class="inline-flex items-center gap-1.5">
                                <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                Saving…
                            </span>
                        </button>
                        <button type="button" wire:click="removeCategory({{ $index }})"
                                wire:confirm="Delete '{{ $cat['label'] }}'? Markers using this key will fall back to Uncategorized."
                                wire:loading.attr="disabled"
                                wire:target="removeCategory({{ $index }})"
                                class="px-3 py-1.5 rounded-xl bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 text-xs font-semibold border border-red-200 dark:border-red-500/30 hover:bg-red-100 dark:hover:bg-red-500/20 transition active:scale-95 focus:outline-none focus:ring-2 focus:ring-red-500/50 disabled:opacity-60">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-10 flex flex-col items-center justify-center text-center">
                <div class="p-3 bg-gray-100 dark:bg-gray-800 rounded-2xl mb-3 text-gray-400 dark:text-gray-500">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
         class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 w-full max-w-sm pointer-events-none">
        <template x-for="toast in toasts" :key="toast.id">
            <div
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="pointer-events-auto rounded-xl px-4 py-3 shadow-lg text-sm font-medium flex items-center gap-2 border"
                :class="{
                    'bg-green-50 border-green-200 text-green-800 dark:bg-green-500/10 dark:border-green-500/30 dark:text-green-300': toast.type === 'success',
                    'bg-red-50 border-red-200 text-red-800 dark:bg-red-500/10 dark:border-red-500/30 dark:text-red-300': toast.type === 'error',
                    'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-500/10 dark:border-blue-500/30 dark:text-blue-300': toast.type === 'info',
                }">
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>
</div>