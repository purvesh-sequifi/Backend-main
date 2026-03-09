<?php

return [
    // Documentation menu
    'documentation' => [
        // Getting Started
        [
            'heading' => 'Getting Started',
        ],

        // Overview
        [
            'title' => 'Overview',
            'path' => 'documentation/getting-started/overview',
            // 'role' => ['admin'],
            // 'permission' => [],
        ],

        // Build
        [
            'title' => 'Build',
            'path' => 'documentation/getting-started/build',
        ],

        [
            'title' => 'Multi-demo',
            'attributes' => ['data-kt-menu-trigger' => 'click'],
            'classes' => ['item' => 'menu-accordion'],
            'sub' => [
                'class' => 'menu-sub-accordion',
                'items' => [
                    [
                        'title' => 'Overview',
                        'path' => 'documentation/getting-started/multi-demo/overview',
                        'bullet' => '<span class="bullet bullet-dot"></span>',
                    ],
                    [
                        'title' => 'Build',
                        'path' => 'documentation/getting-started/multi-demo/build',
                        'bullet' => '<span class="bullet bullet-dot"></span>',
                    ],
                ],
            ],
        ],

        // File Structure
        [
            'title' => 'File Structure',
            'path' => 'documentation/getting-started/file-structure',
        ],

        // Customization
        [
            'title' => 'Customization',
            'attributes' => ['data-kt-menu-trigger' => 'click'],
            'classes' => ['item' => 'menu-accordion'],
            'sub' => [
                'class' => 'menu-sub-accordion',
                'items' => [
                    [
                        'title' => 'SASS',
                        'path' => 'documentation/getting-started/customization/sass',
                        'bullet' => '<span class="bullet bullet-dot"></span>',
                    ],
                    [
                        'title' => 'Javascript',
                        'path' => 'documentation/getting-started/customization/javascript',
                        'bullet' => '<span class="bullet bullet-dot"></span>',
                    ],
                ],
            ],
        ],

        // Dark skin
        [
            'title' => 'Dark Mode Version',
            'path' => 'documentation/getting-started/dark-mode',
        ],

        // RTL
        [
            'title' => 'RTL Version',
            'path' => 'documentation/getting-started/rtl',
        ],

        // Troubleshoot
        [
            'title' => 'Troubleshoot',
            'path' => 'documentation/getting-started/troubleshoot',
        ],

        // Changelog
        [
            'title' => 'Changelog <span class="badge badge-changelog badge-light-danger bg-hover-danger text-hover-white fw-bold fs-9 px-2 ms-2">v'.theme()->getVersion().'</span>',
            'breadcrumb-title' => 'Changelog',
            'path' => 'documentation/getting-started/changelog',
        ],

        // References
        [
            'title' => 'References',
            'path' => 'documentation/getting-started/references',
        ],

        // Separator
        [
            'custom' => '<div class="h-30px"></div>',
        ],

        // Configuration
        [
            'heading' => 'Configuration',
        ],

        // General
        [
            'title' => 'General',
            'path' => 'documentation/configuration/general',
        ],

        // Menu
        [
            'title' => 'Menu',
            'path' => 'documentation/configuration/menu',
        ],

        // Page
        [
            'title' => 'Page',
            'path' => 'documentation/configuration/page',
        ],

        // Page
        [
            'title' => 'Add NPM Plugin',
            'path' => 'documentation/configuration/npm-plugins',
        ],

        // Separator
        [
            'custom' => '<div class="h-30px"></div>',
        ],

        // General
        [
            'heading' => 'General',
        ],

        // DataTables
        [
            'title' => 'DataTables',
            'classes' => ['item' => 'menu-accordion'],
            'attributes' => ['data-kt-menu-trigger' => 'click'],
            'sub' => [
                'class' => 'menu-sub-accordion',
                'items' => [
                    [
                        'title' => 'Overview',
                        'path' => 'documentation/general/datatables/overview',
                        'bullet' => '<span class="bullet bullet-dot"></span>',
                    ],
                ],
            ],
        ],

        // Remove demos
        [
            'title' => 'Remove Demos',
            'path' => 'documentation/general/remove-demos',
        ],

        // Separator
        [
            'custom' => '<div class="h-30px"></div>',
        ],

        // HTML Theme
        [
            'heading' => 'HTML Theme',
        ],

        [
            'title' => 'Components',
            'path' => '//preview.keenthemes.com/metronic8/demo1/documentation/base/utilities.html',
        ],

        [
            'title' => 'Documentation',
            'path' => '//preview.keenthemes.com/metronic8/demo1/documentation/getting-started.html',
        ],
    ],

    // Main menu
    'main' => [
        // // Dashboard
        [
            'title' => 'Dashboard',
            'path' => '',
            'icon' => theme()->getSvgIcon('demo1/media/icons/duotune/art/art002.svg', 'svg-icon-2'),
        ],

        // // Modules
        [
            'classes' => ['content' => 'pt-8 pb-2'],
            'content' => '<span class="menu-section text-muted text-uppercase fs-8 ls-1">Modules</span>',
        ],

        // Account
        [
            'title' => 'Account',
            'icon' => [
                'svg' => theme()->getSvgIcon('demo1/media/icons/duotune/communication/com006.svg', 'svg-icon-2'),
                'font' => '<i class="bi bi-person fs-2"></i>',
            ],
            'classes' => ['item' => 'menu-accordion'],
            'attributes' => [
                'data-kt-menu-trigger' => 'click',
            ],
            'sub' => [
                'class' => 'menu-sub-accordion menu-active-bg',
                'items' => [
                    [
                        'title' => 'Overview',
                        'path' => 'account/overview',
                        'bullet' => '<span class="bullet bullet-dot"></span>',
                    ],
                    [
                        'title' => 'Settings',
                        'path' => 'account/settings',
                        'bullet' => '<span class="bullet bullet-dot"></span>',
                    ],
                    [
                        'title' => 'Security',
                        'path' => '#',
                        'bullet' => '<span class="bullet bullet-dot"></span>',
                        'attributes' => [
                            'link' => [
                                'title' => 'Coming soon',
                                'data-bs-toggle' => 'tooltip',
                                'data-bs-trigger' => 'hover',
                                'data-bs-dismiss' => 'click',
                                'data-bs-placement' => 'right',
                            ],
                        ],
                    ],
                ],
            ],
        ],

        // System
        [
            'title' => 'System',
            'icon' => [
                'svg' => theme()->getSvgIcon('demo1/media/icons/duotune/general/gen025.svg', 'svg-icon-2'),
                'font' => '<i class="bi bi-layers fs-3"></i>',
            ],
            'classes' => ['item' => 'menu-accordion'],
            'attributes' => [
                'data-kt-menu-trigger' => 'click',
            ],
            'sub' => [
                'class' => 'menu-sub-accordion menu-active-bg',
                'items' => [
                    [
                        'title' => 'Settings',
                        'path' => '#',
                        'bullet' => '<span class="bullet bullet-dot"></span>',
                        'attributes' => [
                            'link' => [
                                'title' => 'Coming soon',
                                'data-bs-toggle' => 'tooltip',
                                'data-bs-trigger' => 'hover',
                                'data-bs-dismiss' => 'click',
                                'data-bs-placement' => 'right',
                            ],
                        ],
                    ],
                    [
                        'title' => 'Audit Log',
                        'path' => 'log/audit',
                        'bullet' => '<span class="bullet bullet-dot"></span>',
                    ],
                    [
                        'title' => 'System Log',
                        'path' => 'log/system',
                        'bullet' => '<span class="bullet bullet-dot"></span>',
                    ],
                    [
                        'title' => 'Error 404',
                        'path' => 'error/error-404',
                        'bullet' => '<span class="bullet bullet-dot"></span>',
                    ],
                    [
                        'title' => 'Error 500',
                        'path' => 'error/error-500',
                        'bullet' => '<span class="bullet bullet-dot"></span>',
                    ],
                ],
            ],
        ],

        // // Help
        [
            'classes' => ['content' => 'pt-8 pb-2'],
            'content' => '<span class="menu-section text-muted text-uppercase fs-8 ls-1">Help</span>',
        ],

        // Documentation
        [
            'title' => 'Documentation',
            'icon' => theme()->getSvgIcon('demo1/media/icons/duotune/abstract/abs027.svg', 'svg-icon-2'),
            'path' => 'documentation/getting-started/overview',
        ],

        // Changelog
        [
            'title' => 'Changelog v'.theme()->getVersion(),
            'icon' => theme()->getSvgIcon('demo1/media/icons/duotune/coding/cod003.svg', 'svg-icon-2'),
            'path' => 'documentation/getting-started/changelog',
        ],

    ],

    // Horizontal menu
    'horizontal' => [
        // Dashboard
        [
            'title' => 'Dashboard',
            'path' => '',
            'classes' => ['item' => 'me-lg-1'],
        ],

        // Resources
        [
            'title' => 'Resources',
            'classes' => ['item' => 'menu-lg-down-accordion me-lg-1', 'arrow' => 'd-lg-none'],
            'attributes' => [
                'data-kt-menu-trigger' => 'click',
                'data-kt-menu-placement' => 'bottom-start',
            ],
            'sub' => [
                'class' => 'menu-sub-lg-down-accordion menu-sub-lg-dropdown menu-rounded-0 py-lg-4 w-lg-225px',
                'items' => [
                    // Documentation
                    [
                        'title' => 'Documentation',
                        'icon' => theme()->getSvgIcon('demo1/media/icons/duotune/abstract/abs027.svg', 'svg-icon-2'),
                        'path' => 'documentation/getting-started/overview',
                    ],

                    // Changelog
                    [
                        'title' => 'Changelog v'.theme()->getVersion(),
                        'icon' => theme()->getSvgIcon('demo1/media/icons/duotune/general/gen005.svg', 'svg-icon-2'),
                        'path' => 'documentation/getting-started/changelog',
                    ],
                ],
            ],
        ],

        // Account
        [
            'title' => 'Account',
            'classes' => ['item' => 'menu-lg-down-accordion me-lg-1', 'arrow' => 'd-lg-none'],
            'attributes' => [
                'data-kt-menu-trigger' => 'click',
                'data-kt-menu-placement' => 'bottom-start',
            ],
            'sub' => [
                'class' => 'menu-sub-lg-down-accordion menu-sub-lg-dropdown menu-rounded-0 py-lg-4 w-lg-225px',
                'items' => [
                    [
                        'title' => 'Overview',
                        'path' => 'account/overview',
                        'bullet' => '<span class="bullet bullet-dot"></span>',
                    ],
                    [
                        'title' => 'Settings',
                        'path' => 'account/settings',
                        'bullet' => '<span class="bullet bullet-dot"></span>',
                    ],
                    [
                        'title' => 'Security',
                        'path' => '#',
                        'bullet' => '<span class="bullet bullet-dot"></span>',
                        'attributes' => [
                            'link' => [
                                'title' => 'Coming soon',
                                'data-bs-toggle' => 'tooltip',
                                'data-bs-trigger' => 'hover',
                                'data-bs-dismiss' => 'click',
                                'data-bs-placement' => 'right',
                            ],
                        ],
                    ],
                ],
            ],
        ],

        // System
        [
            'title' => 'System',
            'classes' => ['item' => 'menu-lg-down-accordion me-lg-1', 'arrow' => 'd-lg-none'],
            'attributes' => [
                'data-kt-menu-trigger' => 'click',
                'data-kt-menu-placement' => 'bottom-start',
            ],
            'sub' => [
                'class' => 'menu-sub-lg-down-accordion menu-sub-lg-dropdown menu-rounded-0 py-lg-4 w-lg-225px',
                'items' => [
                    [
                        'title' => 'Settings',
                        'path' => '#',
                        'bullet' => '<span class="bullet bullet-dot"></span>',
                        'attributes' => [
                            'link' => [
                                'title' => 'Coming soon',
                                'data-bs-toggle' => 'tooltip',
                                'data-bs-trigger' => 'hover',
                                'data-bs-dismiss' => 'click',
                                'data-bs-placement' => 'right',
                            ],
                        ],
                    ],
                    [
                        'title' => 'Audit Log',
                        'path' => 'log/audit',
                        'bullet' => '<span class="bullet bullet-dot"></span>',
                    ],
                    [
                        'title' => 'System Log',
                        'path' => 'log/system',
                        'bullet' => '<span class="bullet bullet-dot"></span>',
                    ],
                ],
            ],
        ],
    ],
];
