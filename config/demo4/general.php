<?php

return [
    // Assets
    'assets' => [
        'favicon' => 'media/logos/favicon.ico',
        'fonts' => [
            'google' => [
                'Inter:300,400,500,600,700',
            ],
        ],
        'css' => [
            'plugins/global/plugins.bundle.css',
            'plugins/global/plugins-custom.bundle.css',
            'css/style.bundle.css',
        ],
        'js' => [
            'plugins/global/plugins.bundle.js',
            'js/scripts.bundle.js',
            'js/custom/widgets.js',
            'js/init.js',
        ],
    ],

    // Layout
    'layout' => [
        // Main
        'main' => [
            'type' => 'default', // Set layout type: default|blank|none
            'primary-color' => '#7239EA',
        ],

        // Loader
        'loader' => [
            'display' => false,
            'type' => 'default', // Set default|spinner-message|spinner-logo to hide or show page loader
        ],

        // Scrolltop
        'scrolltop' => [
            'display' => true, // Enable scrolltop
        ],

        // Header
        'header' => [
            'display' => true, // Set true|false to show or hide Header
            'width' => 'fluid', // Set fixed|fluid to change width type
            'fixed' => [
                'desktop' => true,  // Set true|false to set fixed Header for desktop mode
                'tablet-and-mobile' => true, // Set true|false to set fixed Header for tablet and mobile modes
            ],
            'menu-icon' => 'svg', // Menu icon type(svg|font)
            'menu' => true,
        ],

        // Page title
        'page-title' => [
            'display' => true,
            'description' => false,
            'breadcrumb' => true,
        ],

        // Toolbar
        'toolbar' => [
            'display' => false,
        ],

        // Aside
        'aside' => [
            'fixed' => true,
            'menu-icon' => 'font', // Menu icon type(svg|font)
        ],

        // Content
        'content' => [
            'width' => 'fixed', // Set fixed|fluid to change width type
            'layout' => 'default',  // Set content type,
        ],

        // Footer
        'footer' => [
            'width' => 'fixed', // Set fixed|fluid to change width type
        ],
    ],
];
