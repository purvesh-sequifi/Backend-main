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
            'primary-color' => '#009EF7', // Primary color used in email templates
            'page-bg-white' => false, // Set true if page background color is white
        ],

        // Docs
        'docs' => [
            'logo-path' => [
                'default' => 'logos/logo-1.svg',
                'dark' => 'logos/logo-1-dark.svg',
            ],
            'logo-class' => 'h-25px',
        ],

        // Illustration
        'illustrations' => [
            'set' => 'sketchy-1',
        ],

        // Loader
        'loader' => [
            'display' => false,
            'type' => 'default', // Set default|spinner-message|spinner-logo to hide or show page loader
        ],

        // Header
        'header' => [
            'width' => 'fluid', // Set header width(fixed|fluid)
            'fixed' => [
                'tablet-and-mobile' => true, // Set fixed header for talet & mobile
            ],
        ],

        // Aside
        'aside' => [
            'minimized' => false, // Set aside minimized by default
            'minimize' => true, // Allow aside minimize toggle
        ],

        // Content
        'content' => [
            'width' => 'fixed', // Set content width(fixed|fluid)
        ],

        // Footer
        'footer' => [
            'width' => 'fluid', // Set fixed|fluid to change width type
        ],

        // Scrolltop
        'scrolltop' => [
            'display' => true, // Display scrolltop
        ],
    ],
];
