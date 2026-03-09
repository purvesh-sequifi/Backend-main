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
            'primary-color' => '#009EF7',
            'body' => [
                'background-image' => 'patterns/'.(theme()->isDarkMode() ? 'header-bg-dark.png' : 'header-bg.jpg'),
            ],
        ],

        // Loader
        'loader' => [
            'display' => false,
            'type' => 'default', // Set default|spinner-message|spinner-logo to hide or show page loader
        ],

        // Header
        'header' => [
            'display' => true, // Display header
            'width' => 'fixed', // Set header width(fixed|fluid)
            'left' => 'menu', // Set left part content(menu|page-title)
            'fixed' => [
                'desktop' => true,  // Set fixed header for desktop
                'tablet-and-mobile' => true, // Set fixed header for talet & mobile
            ],
            'menu-icon' => 'svg', // Menu icon type(svg|font)
        ],

        // Toolbar
        'toolbar' => [
            'display' => true, // Display toolbar
            'layout' => 'default',  // Set content layout(default|documentation)
            'width' => 'fixed', // Set toolbar container width(fluid|fixed)
        ],

        // Page title
        'page-title' => [
            'display' => true, // Display page title
            'breadcrumb' => true, // Display breadcrumb
            'description' => false, // Display description
        ],

        // Aside
        'aside' => [
            'display' => false, // Display aside
            'sticky' => true, // Enable sticky aside
            'menu' => 'main', // Set aside menu(main|documentation)
            'menu-icon' => 'svg', // Menu icon type(svg|font)
        ],

        // Content
        'content' => [
            'width' => 'fixed', // Set content width(fixed|fluid)
            'layout' => 'default',  // Set content layout(default|documentation)
        ],

        // Footer
        'footer' => [
            'width' => 'fixed', // Set fixed|fluid to change width type
        ],

        // Scrolltop
        'scrolltop' => [
            'display' => true, // Display scrolltop
        ],
    ],
];
