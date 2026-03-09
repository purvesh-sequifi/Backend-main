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
            'primary-color' => '#04C8C8',
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
            'width' => 'fixed', // Set fixed|fluid to change width type
            'fixed' => [
                'desktop' => false,  // Set true|false to set fixed Header for desktop mode
                'tablet-and-mobile' => false, // Set true|false to set fixed Header for tablet and mobile modes
            ],
        ],

        // Page title
        'page-title' => [
            'display' => true, // Display page title
            'breadcrumb' => true, // Display breadcrumb
            'description' => false, // Display description
            'responsive' => true, // Move page title to cotnent on mobile mode
            'responsive-breakpoint' => 'lg', // Responsive breakpoint value(e.g: md, lg, or 300px)
            'responsive-target' => '#kt_toolbar_container', // Responsive target selector
        ],

        // Aside
        'aside' => [
            'menu-icon' => 'svg', // Menu icon type(svg|font)
        ],

        // Sidebar
        'sidebar' => [
            'display' => true,
        ],

        // Content
        'content' => [
            'width' => 'fixed', // Set fixed|fluid to change width type
        ],

        // Footer
        'footer' => [
            'width' => 'fixed', // Set fixed|fluid to change width type
        ],
    ],
];
