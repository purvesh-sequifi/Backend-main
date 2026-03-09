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
            'base' => 'default', // Set base layout: default|docs
            'type' => 'default', // Set layout type: default|blank|none
            'primary-color' => '#009EF7', // Primary color used in email templates
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
            'display' => true, // Display header
            'width' => 'fixed', // Set header width(fixed|fluid)
            'menu' => true, // Display header menu
            'fixed' => [
                'desktop' => true,  // Set fixed header for desktop
                'tablet-and-mobile' => true, // Set fixed header for talet & mobile
            ],
            'menu-icon' => 'svg', // Menu icon type(svg|font)
        ],

        // Toolbar
        'toolbar' => [
            'display' => true, // Display toolbar
        ],

        // Page title
        'page-title' => [
            'display' => true, // Display page title
            'breadcrumb' => true, // Display breadcrumb
            'description' => false, // Display description
            'layout' => 'default', // Set layout(default|select)
            'direction' => 'row', // Flex direction(column|row))
            'responsive' => true, // Move page title to cotnent on mobile mode
            'responsive-breakpoint' => 'lg', // Responsive breakpoint value(e.g: md, lg, or 300px)
            'responsive-target' => '#kt_toolbar_container', // Responsive target selector
        ],

        // Aside
        'aside' => [
            'display' => true, // Display aside
        ],

        // Sidebar
        'sidebar' => [
            'display' => true, // Display sidebar
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
