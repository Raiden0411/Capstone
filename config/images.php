<?php
// config/images.php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Compression Ceiling (KB)
    |--------------------------------------------------------------------------
    | Every uploaded image is compressed until it fits under this ceiling.
    | Per-context overrides below take precedence.
    */
    'max_size_kb' => env('IMAGE_MAX_KB', 2048),   // 2 MB system-wide default

    /*
    |--------------------------------------------------------------------------
    | Default Max Dimensions
    |--------------------------------------------------------------------------
    | Images wider or taller than these are scaled down proportionally
    | (aspect ratio preserved). Pass 0 to disable a dimension.
    */
    'max_width'  => env('IMAGE_MAX_WIDTH', 2560),
    'max_height' => env('IMAGE_MAX_HEIGHT', 2560),

    /*
    |--------------------------------------------------------------------------
    | EXIF Handling (Intervention Image v3 constructor options)
    |--------------------------------------------------------------------------
    | auto_orientate  →  maps to ImageManager(autoOrientation: ...)
    |   v3 rotates the image automatically to match the EXIF orientation
    |   tag when the file is decoded. This is ON by default in v3 — the
    |   old v2 `orientate()` method no longer exists. Turning this off
    |   would leave iPhone portrait photos stored sideways.
    |
    | strip_exif  →  maps to ImageManager(strip: ...)
    |   Removes EXIF / IPTC / comment metadata during encoding. Privacy
    |   (GPS coordinates, device model) AND size (blocks can be 20–100 KB
    |   per photo). Note: the GD driver discards EXIF on its own during
    |   encode; this option matters mainly for Imagick.
    */
    'auto_orientate' => env('IMAGE_AUTO_ORIENTATE', true),
    'strip_exif'     => env('IMAGE_STRIP_EXIF', true),

    /*
    |--------------------------------------------------------------------------
    | Per-Context Overrides
    |--------------------------------------------------------------------------
    | Keyed by the context string the caller passes to storeImage(). Falls
    | through to the defaults above when the context isn't listed.
    */
    'contexts' => [
        'avatars'        => ['max_size_kb' => 512,  'max_width' => 800,  'max_height' => 800],
        'employees'      => ['max_size_kb' => 512,  'max_width' => 800,  'max_height' => 800],
        'tenant-logo'    => ['max_size_kb' => 512,  'max_width' => 1024, 'max_height' => 1024],
        'tenant-cover'   => ['max_size_kb' => 2048, 'max_width' => 2560, 'max_height' => 1440],
        'property'       => ['max_size_kb' => 2048, 'max_width' => 2560, 'max_height' => 2560],
        'event'          => ['max_size_kb' => 2048, 'max_width' => 2560, 'max_height' => 1440],
        'kyb-document'   => ['max_size_kb' => 2048, 'max_width' => 3000, 'max_height' => 3000],
        'site'           => ['max_size_kb' => 2048, 'max_width' => 2560, 'max_height' => 1440],
        'marker-icon'    => ['max_size_kb' => 256,  'max_width' => 512,  'max_height' => 512],
    ],

    /*
    |--------------------------------------------------------------------------
    | Quality Ladder (JPEG / WEBP)
    |--------------------------------------------------------------------------
    | Compression walks down this ladder until the file fits under the
    | context's max_size_kb. The last entry is the quality floor — if even
    | that isn't small enough, the image is scaled down and the ladder
    | restarts from the top.
    */
    'quality_ladder' => [88, 80, 72, 64, 56],

    /*
    |--------------------------------------------------------------------------
    | Convert Oversized PNGs Without Transparency → JPEG
    |--------------------------------------------------------------------------
    */
    'convert_png_to_jpeg' => true,

    /*
    |--------------------------------------------------------------------------
    | Modern Format Preference
    |--------------------------------------------------------------------------
    */
    'prefer_modern_format' => env('IMAGE_PREFER_WEBP', false),

    /*
    |--------------------------------------------------------------------------
    | Driver: 'gd' | 'imagick'
    |--------------------------------------------------------------------------
    */
    'driver' => env('IMAGE_DRIVER', 'gd'),
];