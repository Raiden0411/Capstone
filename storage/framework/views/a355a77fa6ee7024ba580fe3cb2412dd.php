
<div
    x-data="cropModal()"
    x-init="init()"
    x-on:keydown.escape.window="open && cancel()"
    :class="open ? '' : 'hidden'"
    wire:ignore
    tabindex="-1"
    class="fixed inset-0 z-[3000] outline-none"
    role="dialog"
    aria-modal="true"
    aria-labelledby="crop-modal-title"
>
    
    <div
        x-on:click="cancel()"
        class="absolute inset-0 bg-black/75 backdrop-blur-sm"
        aria-hidden="true"
    ></div>

    
    <div class="absolute inset-0 flex items-center justify-center p-3 sm:p-4 pointer-events-none">
        <div class="relative w-full max-w-3xl bg-white dark:bg-gray-900 rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh] pointer-events-auto">

            
            <div class="flex items-center justify-between gap-3 px-5 py-3.5 border-b border-gray-200 dark:border-gray-800 shrink-0">
                <div class="min-w-0">
                    <h3 id="crop-modal-title" class="text-sm font-semibold text-gray-900 dark:text-white truncate" x-text="title"></h3>
                    <p
                        x-text="description || ''"
                        :class="description ? 'block' : 'hidden'"
                        class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 truncate"
                    ></p>
                </div>
                <button
                    type="button"
                    x-on:click="cancel()"
                    class="shrink-0 p-2 rounded-lg text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:hover:text-white dark:hover:bg-gray-800 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                    aria-label="Close"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            
            <div class="flex-1 min-h-0 bg-gray-100 dark:bg-gray-950">
                <div class="w-full h-[58vh] max-h-[640px] min-h-[280px]">
                    <cropper-canvas
                        style="width: 100%; height: 100%; display: block;"
                    >
                        <cropper-image
                            alt=""
                            rotatable
                            scalable
                            skewable
                            translatable
                        ></cropper-image>

                        <cropper-shade hidden></cropper-shade>
                        <cropper-handle action="select" plain></cropper-handle>

                        <cropper-selection
                            initial-coverage="0.85"
                            movable
                            resizable
                        >
                            <cropper-grid role="grid" bordered covered></cropper-grid>
                            <cropper-crosshair centered></cropper-crosshair>
                            <cropper-handle action="move" theme-color="rgba(255,255,255,0.35)"></cropper-handle>
                            <cropper-handle action="n-resize"></cropper-handle>
                            <cropper-handle action="e-resize"></cropper-handle>
                            <cropper-handle action="s-resize"></cropper-handle>
                            <cropper-handle action="w-resize"></cropper-handle>
                            <cropper-handle action="ne-resize"></cropper-handle>
                            <cropper-handle action="nw-resize"></cropper-handle>
                            <cropper-handle action="se-resize"></cropper-handle>
                            <cropper-handle action="sw-resize"></cropper-handle>
                        </cropper-selection>
                    </cropper-canvas>
                </div>
            </div>

            
            <div class="flex items-center justify-between gap-2 px-5 py-3 border-t border-gray-200 dark:border-gray-800 shrink-0 flex-wrap">
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        x-on:click="rotate(-90)"
                        class="inline-flex items-center justify-center p-2 rounded-lg text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                        title="Rotate left"
                        aria-label="Rotate left"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                        </svg>
                    </button>
                    <button
                        type="button"
                        x-on:click="rotate(90)"
                        class="inline-flex items-center justify-center p-2 rounded-lg text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                        title="Rotate right"
                        aria-label="Rotate right"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2m18-10l-6 6m6-6l-6-6"/>
                        </svg>
                    </button>
                    <button
                        type="button"
                        x-on:click="reset()"
                        class="inline-flex items-center justify-center p-2 rounded-lg text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                        title="Reset"
                        aria-label="Reset"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        x-on:click="cancel()"
                        :disabled="processing"
                        class="px-4 py-2 rounded-lg text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        x-on:click="confirm()"
                        :disabled="processing"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold shadow-sm transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <svg
                            :class="processing ? 'block animate-spin motion-reduce:animate-none' : 'hidden'"
                            xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" aria-hidden="true"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span x-text="processing ? 'Processing…' : 'Save & upload'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views\components\image-crop-modal.blade.php ENDPATH**/ ?>