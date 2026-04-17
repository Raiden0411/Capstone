<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Component Locations
    |---------------------------------------------------------------------------
    */

    'component_locations' => [
        resource_path('views/components'),
        resource_path('views/livewire'),
    ],

    /*
    |---------------------------------------------------------------------------
    | Component Namespaces
    |---------------------------------------------------------------------------
    | 
    | Here we define the strict boundaries for your SaaS architecture.
    |
    */

    'component_namespaces' => [
        'layouts' => resource_path('views/layouts'),
        'pages' => resource_path('views/pages'),
        'public' => resource_path('views/public'),
        'superadmin' => resource_path('views/superadmin'),
        'tenant' => resource_path('views/tenant'),
        'shared' => resource_path('views/shared'),
    ],

    /*
    |---------------------------------------------------------------------------
    | Page Layout
    |---------------------------------------------------------------------------
    */

    'component_layout' => 'layouts::app',

    /*
    |---------------------------------------------------------------------------
    | Lazy Loading Placeholder
    |---------------------------------------------------------------------------
    */

    'component_placeholder' => null,

    /*
    |---------------------------------------------------------------------------
    | Make Command
    |---------------------------------------------------------------------------
    */

    'make_command' => [
        'type' => 'sfc', 
        'emoji' => true, 
        'with' => [
            'js' => false,
            'css' => false,
            'test' => false,
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Class Namespace & Paths
    |---------------------------------------------------------------------------
    */

    'class_namespace' => 'App\\Livewire',
    'class_path' => app_path('Livewire'),
    'view_path' => resource_path('views/livewire'),

    /*
    |---------------------------------------------------------------------------
    | Temporary File Uploads
    |---------------------------------------------------------------------------
    */

    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'), 
        'rules' => null, 
        'directory' => null, 
        'middleware' => null, 
        'preview_mimes' => [ 
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5, 
        'cleanup' => true, 
    ],

    /*
    |---------------------------------------------------------------------------
    | Render On Redirect & Model Binding
    |---------------------------------------------------------------------------
    */

    'render_on_redirect' => false,
    'legacy_model_binding' => false,

    /*
    |---------------------------------------------------------------------------
    | Frontend Assets & Navigation
    |---------------------------------------------------------------------------
    */

    'inject_assets' => true,

    'navigate' => [
        'show_progress_bar' => true,
        'progress_bar_color' => '#2299dd',
    ],

    /*
    |---------------------------------------------------------------------------
    | HTML Morph Markers & Smart Keys
    |---------------------------------------------------------------------------
    */

    'inject_morph_markers' => true,
    'smart_wire_keys' => true,

    /*
    |---------------------------------------------------------------------------
    | Pagination Theme
    |---------------------------------------------------------------------------
    */

    'pagination_theme' => 'tailwind',

    /*
    |---------------------------------------------------------------------------
    | Release Token & CSP
    |---------------------------------------------------------------------------
    */

    'release_token' => 'a',
    'csp_safe' => false,

    /*
    |---------------------------------------------------------------------------
    | Payload Guards
    |---------------------------------------------------------------------------
    */

    'payload' => [
        'max_size' => 1024 * 1024, 
        'max_nesting_depth' => 10, 
        'max_calls' => 50, 
        'max_components' => 20, 
    ],
];