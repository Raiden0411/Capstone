{{-- resources/views/tenant/pages/settings/⚡gallery-manager.blade.php --}}
<?php

use App\Models\TenantSetting;
use App\Services\ImageCompressionService;
use App\Traits\ChecksTenantPermissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Layout('tenant.layouts.app')]
#[Title('Photo Gallery')]
class extends Component
{
    use WithFileUploads;
    use ChecksTenantPermissions;

    /** Existing gallery paths (relative to storage/app/public). */
    public array $galleryImages = [];

    /** Newly picked files awaiting upload. */
    public array $newUploads = [];

    /** Path of the current cover photo, or null. */
    public ?string $coverPhoto = null;

    /** Current saved copy — what business-offerings shows. */
    public string $galleryTitle    = '';
    public string $gallerySubtitle = '';

    /** Editable drafts — commit on save(). */
    public string $draftTitle    = '';
    public string $draftSubtitle = '';

    /** Confirmation id of the image armed for deletion (two-click arm pattern). */
    public ?string $armedForDelete = null;

    public function mount(): void
    {
        $this->authorizeManageGallery();

        $tenant = Auth::user()?->tenant;
        abort_unless($tenant, 403);

        $settings = TenantSetting::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('key', ['business_gallery', 'spot_cover', 'gallery_title', 'gallery_subtitle'])
            ->get()
            ->pluck('value', 'key');

        $images = $settings['business_gallery'] ?? [];
        if (! is_array($images)) {
            $images = [];
        }

        // Strip out any entries whose file no longer exists on disk, so a
        // stale path doesn't render as a broken thumbnail.
        $disk = Storage::disk('public');
        $images = array_values(array_filter(
            $images,
            fn ($p) => is_string($p) && $p !== '' && $disk->exists($p),
        ));

        $this->galleryImages = $images;
        $this->coverPhoto    = $settings['spot_cover'] ?? null;

        if ($this->coverPhoto && ! $disk->exists($this->coverPhoto)) {
            $this->coverPhoto = null;
        }

        $this->galleryTitle    = (string) ($settings['gallery_title']    ?? '');
        $this->gallerySubtitle = (string) ($settings['gallery_subtitle'] ?? '');
        $this->draftTitle      = $this->galleryTitle;
        $this->draftSubtitle   = $this->gallerySubtitle;
    }

    public function hydrate(): void
    {
        $this->authorizeManageGallery();
    }

    /**
     * Gallery edits require `manage properties`. The route-level
     * `role:admin|super-admin` gate covers the initial GET, but Livewire
     * update requests bypass route middleware — this guard re-verifies on
     * every subsequent request.
     */
    protected function authorizeManageGallery(): void
    {
        abort_unless(Auth::user()?->tenant_id, 403, 'No business is linked to your account.');

        $this->requirePermission('manage properties');
    }

    public function updatedNewUploads(): void
    {
        $this->validate([
            'newUploads'   => 'array|max:10',
            'newUploads.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240',
        ], [
            'newUploads.max'      => 'You can upload at most 10 photos at once.',
            'newUploads.*.image'  => 'Each file must be a JPG, PNG, or WebP image.',
            'newUploads.*.max'    => 'Each photo must be under 10 MB.',
        ]);
    }

    public function uploadImages(): void
    {
        $tenant = Auth::user()?->tenant;
        abort_unless($tenant, 403);

        if (empty($this->newUploads)) {
            return;
        }

        $this->validate([
            'newUploads'   => 'array|max:10',
            'newUploads.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        $disk       = Storage::disk('public');
        $compressor = app(ImageCompressionService::class);
        $folder     = "tenants/{$tenant->id}/gallery";
        $added      = 0;

        foreach ($this->newUploads as $file) {
            try {
                $path = $file->store($folder, 'public');

                if (! $path) {
                    continue;
                }

                // Compress in place to the tenant-gallery ceiling.
                $absolute = $disk->path($path);
                $compressor->compressInPlace($absolute, 'property');

                $this->galleryImages[] = $path;
                $added++;
            } catch (\Throwable $e) {
                Log::warning('Gallery upload failed for one file', [
                    'tenant_id' => $tenant->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        $this->newUploads = [];

        if ($added > 0) {
            $this->persistGallery();
            session()->flash('message', $added === 1 ? 'Photo added.' : "{$added} photos added.");
        }
    }

    public function removeUpload(int $index): void
    {
        unset($this->newUploads[$index]);
        $this->newUploads = array_values($this->newUploads);
    }

    public function armDelete(string $path): void
    {
        $this->armedForDelete = $this->armedForDelete === $path ? null : $path;
    }

    public function deleteImage(string $path): void
    {
        if ($this->armedForDelete !== $path) {
            return; // not armed → ignore
        }

        $this->armedForDelete = null;

        $disk = Storage::disk('public');

        // Remove from the array first — if the file delete below throws,
        // the array state is still consistent with what the DB will hold.
        $this->galleryImages = array_values(array_filter(
            $this->galleryImages,
            fn ($p) => $p !== $path,
        ));

        // If this image was also the cover, clear the cover.
        $clearedCover = false;
        if ($this->coverPhoto === $path) {
            $this->coverPhoto = null;
            $clearedCover     = true;
        }

        $this->persistGallery();
        if ($clearedCover) {
            $this->persistCover();
        }

        // Post-DB cleanup. If this fails, a stale file remains on disk
        // but the app is consistent — a bounded disk-usage concern,
        // not a data-integrity one.
        try {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        } catch (\Throwable $e) {
            Log::warning('Gallery file delete failed after DB update', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
        }

        session()->flash('message', 'Photo removed.');
    }

    public function moveUp(int $index): void
    {
        if ($index <= 0 || ! isset($this->galleryImages[$index])) {
            return;
        }
        $arr = $this->galleryImages;
        [$arr[$index - 1], $arr[$index]] = [$arr[$index], $arr[$index - 1]];
        $this->galleryImages = array_values($arr);
        $this->persistGallery();
    }

    public function moveDown(int $index): void
    {
        if (! isset($this->galleryImages[$index + 1])) {
            return;
        }
        $arr = $this->galleryImages;
        [$arr[$index], $arr[$index + 1]] = [$arr[$index + 1], $arr[$index]];
        $this->galleryImages = array_values($arr);
        $this->persistGallery();
    }

    public function setAsCover(string $path): void
    {
        if (! in_array($path, $this->galleryImages, true)) {
            return;
        }

        $this->coverPhoto = $path;
        $this->persistCover();
        session()->flash('message', 'Cover photo updated.');
    }

    public function clearCover(): void
    {
        $this->coverPhoto = null;
        $this->persistCover();
        session()->flash('message', 'Cover photo cleared.');
    }

    public function saveCopy(): void
    {
        $this->validate([
            'draftTitle'    => 'nullable|string|max:120',
            'draftSubtitle' => 'nullable|string|max:240',
        ]);

        $this->galleryTitle    = trim($this->draftTitle);
        $this->gallerySubtitle = trim($this->draftSubtitle);

        $this->persistSetting('gallery_title',    $this->galleryTitle ?: null);
        $this->persistSetting('gallery_subtitle', $this->gallerySubtitle ?: null);

        session()->flash('message', 'Gallery copy saved.');
    }

    protected function persistGallery(): void
    {
        $this->persistSetting('business_gallery', $this->galleryImages);
    }

    protected function persistCover(): void
    {
        $this->persistSetting('spot_cover', $this->coverPhoto);
    }

    protected function persistSetting(string $key, mixed $value): void
    {
        $tenant = Auth::user()?->tenant;
        if (! $tenant) return;

        TenantSetting::updateOrCreate(
            ['tenant_id' => $tenant->id, 'key' => $key],
            ['value' => $value],
        );
    }
};
?>

<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[11px] font-bold uppercase tracking-[0.22em] text-primary-600 dark:text-primary-400">Content</span>
            </div>
            <h1 class="font-display text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white tracking-tight">
                Photo Gallery
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Manage the photos that appear on your public offerings page.
            </p>
        </div>

        <a href="{{ route('tenant.show', Auth::user()->tenant->slug) }}" target="_blank" rel="noopener noreferrer"
           class="shrink-0 inline-flex items-center justify-center gap-2 px-5 min-h-[44px] rounded-xl
                  border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800
                  text-gray-700 dark:text-gray-200 text-sm font-semibold
                  hover:bg-gray-50 dark:hover:bg-gray-700 transition-all active:scale-95
                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
            </svg>
            Preview Public Page
        </a>
    </div>

    @if(session('message'))
        <div class="rounded-xl border border-emerald-200 dark:border-emerald-500/30 bg-emerald-50 dark:bg-emerald-500/10 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300 flex items-center gap-2"
             role="status">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('message') }}
        </div>
    @endif

    {{-- GALLERY COPY --}}
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6">
        <div class="mb-4">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Gallery Copy</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Optional. Shown above and below the gallery grid on your public page.
            </p>
        </div>

        <form wire:submit="saveCopy" class="space-y-4">
            <div>
                <label for="galleryTitle" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                    Gallery title <span class="text-gray-400 font-normal">(max 120 characters)</span>
                </label>
                <input id="galleryTitle"
                       type="text"
                       wire:model="draftTitle"
                       maxlength="120"
                       placeholder="e.g. Boardwalk winding through the mangroves"
                       class="input">
                @error('draftTitle')
                    <p class="mt-1.5 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="gallerySubtitle" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                    Gallery subtitle <span class="text-gray-400 font-normal">(max 240 characters)</span>
                </label>
                <textarea id="gallerySubtitle"
                          wire:model="draftSubtitle"
                          rows="2"
                          maxlength="240"
                          placeholder="e.g. Photographed by our guide during the sunrise tour"
                          class="textarea"></textarea>
                @error('draftSubtitle')
                    <p class="mt-1.5 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end">
                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="saveCopy"
                        class="inline-flex items-center justify-center gap-2 px-5 min-h-[44px] rounded-xl
                               bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold
                               shadow-lg shadow-primary-500/20 transition-all active:scale-[0.98]
                               disabled:opacity-60 disabled:cursor-not-allowed
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <span wire:loading.remove wire:target="saveCopy">Save Copy</span>
                    <span wire:loading wire:target="saveCopy" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Saving…
                    </span>
                </button>
            </div>
        </form>
    </div>

    {{-- UPLOAD ZONE --}}
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6">
        <div class="mb-4">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white">Add Photos</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                JPG, PNG, or WebP. Up to 10 photos at once, max 10 MB each. Photos are compressed automatically.
            </p>
        </div>

        <div x-data="{ dragging: false }"
             @dragover.prevent="dragging = true"
             @dragleave.prevent="dragging = false"
             @drop.prevent="
                 dragging = false;
                 const dt = $event.dataTransfer;
                 if (!dt || !dt.files || dt.files.length === 0) return;
                 const input = $refs.filePicker;
                 input.files = dt.files;
                 input.dispatchEvent(new Event('change', { bubbles: true }));
             "
             :class="dragging ? 'border-primary-500 bg-primary-50/40 dark:bg-primary-500/5' : 'border-gray-300 dark:border-gray-600'"
             class="rounded-2xl border-2 border-dashed transition-colors duration-200 p-6 sm:p-8 text-center">

            <input x-ref="filePicker"
                   type="file"
                   wire:model="newUploads"
                   accept="image/jpeg,image/png,image/webp"
                   multiple
                   class="hidden"
                   id="galleryFilePicker">

            <label for="galleryFilePicker" class="cursor-pointer block">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                    Drop photos here, or <span class="text-primary-600 dark:text-primary-400">browse</span>
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Up to 10 files, 10 MB each
                </p>
            </label>
        </div>

        <div wire:loading wire:target="newUploads" class="mt-4">
            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                <svg class="animate-spin h-3.5 w-3.5 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Uploading…
            </div>
        </div>

        @error('newUploads')
            <p class="mt-3 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
        @enderror
        @error('newUploads.*')
            <p class="mt-3 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
        @enderror

        @if(!empty($newUploads))
            <div class="mt-5">
                <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-3">
                    Ready to upload ({{ count($newUploads) }})
                </p>
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3">
                    @foreach($newUploads as $idx => $file)
                        <div class="relative group aspect-square rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-700 ring-1 ring-gray-200 dark:ring-gray-600">
                            <img src="{{ $file->temporaryUrl() }}"
                                 class="w-full h-full object-cover"
                                 alt=""
                                 loading="lazy">
                            <button type="button"
                                    wire:click="removeUpload({{ $idx }})"
                                    class="absolute top-1.5 right-1.5 w-7 h-7 rounded-full bg-black/60 hover:bg-black/80 text-white flex items-center justify-center transition-colors active:scale-95
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/50"
                                    aria-label="Remove from upload queue">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 flex justify-end">
                    <button type="button"
                            wire:click="uploadImages"
                            wire:loading.attr="disabled"
                            wire:target="uploadImages"
                            class="inline-flex items-center justify-center gap-2 px-6 min-h-[44px] rounded-xl
                                   bg-primary-600 hover:bg-primary-700 text-white text-sm font-bold
                                   shadow-lg shadow-primary-500/20 transition-all active:scale-[0.98]
                                   disabled:opacity-60 disabled:cursor-not-allowed
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <span wire:loading.remove wire:target="uploadImages">
                            Upload {{ count($newUploads) }} {{ \Illuminate\Support\Str::plural('Photo', count($newUploads)) }}
                        </span>
                        <span wire:loading wire:target="uploadImages" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Uploading…
                        </span>
                    </button>
                </div>
            </div>
        @endif
    </div>

    {{-- CURRENT GALLERY --}}
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">
                    Current Gallery
                    <span class="ml-1 text-gray-400 dark:text-gray-500 font-normal tabular-nums">({{ count($galleryImages) }})</span>
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Drag the ▲ ▼ buttons to reorder. The first photo shows in the offerings hero gallery strip.
                </p>
            </div>

            @if($coverPhoto)
                <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <span>Cover photo:</span>
                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30 font-semibold">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Set
                    </span>
                    <button type="button"
                            wire:click="clearCover"
                            class="text-rose-600 dark:text-rose-400 hover:underline font-medium transition-colors
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 rounded px-1">
                        Clear
                    </button>
                </div>
            @endif
        </div>

        @if(empty($galleryImages))
            <div class="rounded-2xl border border-dashed border-gray-300 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/40 px-6 py-14 text-center">
                <div class="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-2xl bg-white dark:bg-gray-800 text-gray-400 dark:text-gray-500 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">No photos yet</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Add your first photo above. It will appear on your public offerings page.
                </p>
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 sm:gap-4">
                @foreach($galleryImages as $index => $path)
                    @php
                        $isCover = $coverPhoto === $path;
                        $isArmed = $armedForDelete === $path;
                    @endphp
                    <div wire:key="gal-img-{{ md5($path) }}"
                         class="group relative aspect-square rounded-2xl overflow-hidden
                                bg-gray-100 dark:bg-gray-700
                                ring-1 ring-gray-200 dark:ring-gray-600
                                transition-all duration-200
                                {{ $isCover ? 'ring-2 ring-emerald-500 dark:ring-emerald-400' : '' }}">

                        <img src="{{ '/storage/' . ltrim($path, '/') }}"
                             class="w-full h-full object-cover"
                             alt="Gallery photo {{ $index + 1 }}"
                             loading="lazy" decoding="async">

                        @if($isCover)
                            <span class="absolute top-2 left-2 inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-500 text-white text-[10px] font-bold uppercase tracking-wider shadow-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                </svg>
                                Cover
                            </span>
                        @endif

                        <span class="absolute top-2 right-2 inline-flex items-center justify-center min-w-[22px] h-6 px-1.5 rounded-full bg-black/65 backdrop-blur text-white text-[10px] font-bold tabular-nums shadow-lg">
                            {{ $index + 1 }}
                        </span>

                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity duration-200 pointer-events-none"></div>

                        <div class="absolute inset-x-2 bottom-2 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity duration-200
                                    flex items-center justify-center gap-1.5">

                            <button type="button"
                                    wire:click="moveUp({{ $index }})"
                                    @disabled($index === 0)
                                    class="w-8 h-8 rounded-full bg-white/90 dark:bg-gray-900/90 backdrop-blur
                                           text-gray-700 dark:text-gray-200 hover:bg-white dark:hover:bg-gray-900
                                           flex items-center justify-center transition-all active:scale-90
                                           disabled:opacity-30 disabled:cursor-not-allowed
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                                    aria-label="Move photo up">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/>
                                </svg>
                            </button>

                            <button type="button"
                                    wire:click="moveDown({{ $index }})"
                                    @disabled($index === count($galleryImages) - 1)
                                    class="w-8 h-8 rounded-full bg-white/90 dark:bg-gray-900/90 backdrop-blur
                                           text-gray-700 dark:text-gray-200 hover:bg-white dark:hover:bg-gray-900
                                           flex items-center justify-center transition-all active:scale-90
                                           disabled:opacity-30 disabled:cursor-not-allowed
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                                    aria-label="Move photo down">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>

                            @if(! $isCover)
                                <button type="button"
                                        wire:click="setAsCover('{{ $path }}')"
                                        class="w-8 h-8 rounded-full bg-white/90 dark:bg-gray-900/90 backdrop-blur
                                               text-emerald-600 dark:text-emerald-400 hover:bg-white dark:hover:bg-gray-900
                                               flex items-center justify-center transition-all active:scale-90
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                                        aria-label="Set as cover photo"
                                        title="Set as cover">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>
                            @endif

                            @if($isArmed)
                                <button type="button"
                                        wire:click="deleteImage('{{ $path }}')"
                                        class="h-8 px-3 rounded-full bg-rose-600 hover:bg-rose-700
                                               text-white text-[10px] font-bold uppercase tracking-wider
                                               flex items-center justify-center transition-all active:scale-95
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                                    Confirm
                                </button>
                            @else
                                <button type="button"
                                        wire:click="armDelete('{{ $path }}')"
                                        class="w-8 h-8 rounded-full bg-white/90 dark:bg-gray-900/90 backdrop-blur
                                               text-rose-600 dark:text-rose-400 hover:bg-rose-600 hover:text-white
                                               flex items-center justify-center transition-all active:scale-90
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                                        aria-label="Delete photo"
                                        title="Delete">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>