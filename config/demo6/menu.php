<?php

use App\Core\Adapters\Theme;

return [
    'demo6-aside' => [
        // Dashboard
        '' => [
            'title' => 'Home',
            'icon' => '<i class="bi bi-house fs-2"></i>',
            'attributes' => [
                'link' => [
                    'data-bs-trigger' => 'hover',
                    'data-bs-dismiss' => 'click',
                    'data-bs-placement' => 'right',
                ],
            ],
            'classes' => [
                'item' => 'py-2',
                'link' => 'menu-center',
                'icon' => 'me-0',
            ],
            'path' => '',
        ],

        // Account
        'account' => [
            'title' => 'Account',
            'icon' => '<i class="bi bi-shield-check fs-2"></i>',
            'classes' => [
                'item' => 'py-2',
                'link' => 'menu-center',
                'icon' => 'me-0',
            ],
            'attributes' => [
                'item' => [
                    'data-kt-menu-trigger' => 'click',
                    'data-kt-menu-placement' => Theme::isRTL() ? 'left-start' : 'right-start',
                ],
                'link' => [
                    'data-bs-trigger' => 'hover',
                    'data-bs-dismiss' => 'click',
                    'data-bs-placement' => 'right',
                ],
            ],
            'arrow' => false,
            'sub' => [
                'class' => 'menu-sub-dropdown w-225px px-1 py-4',
                'items' => [
                    [
                        'classes' => ['content' => ''],
                        'content' => '<span class="menu-section fs-5 fw-bolder ps-1 py-1">Account</span>',
                    ],

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

        // Users
        'system' => [
            'title' => 'System',
            'icon' => '<i class="bi bi-layers fs-2"></i>',
            'classes' => [
                'item' => 'py-2',
                'link' => 'menu-center',
                'icon' => 'me-0',
            ],
            'attributes' => [
                'item' => [
                    'data-kt-menu-trigger' => 'click',
                    'data-kt-menu-placement' => Theme::isRTL() ? 'left-start' : 'right-start',
                ],
                'link' => [
                    'data-bs-trigger' => 'hover',
                    'data-bs-dismiss' => 'click',
                    'data-bs-placement' => 'right',
                ],
            ],
            'arrow' => false,
            'sub' => [
                'class' => 'menu-sub-dropdown w-225px px-1 py-4',
                'items' => [
                    [
                        'classes' => ['content' => ''],
                        'content' => '<span class="menu-section fs-5 fw-bolder ps-1 py-1">System</span>',
                    ],

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

        // Resources
        'resources' => [
            'title' => 'More',
            'icon' => '<i class="bi bi-gear fs-2"></i>',
            'classes' => [
                'item' => 'py-2',
                'link' => 'menu-center',
                'icon' => 'me-0',
            ],
            'attributes' => [
                'item' => [
                    'data-kt-menu-trigger' => 'click',
                    'data-kt-menu-placement' => Theme::isRTL() ? 'left-start' : 'right-start',
                ],
                'link' => [
                    'data-bs-trigger' => 'hover',
                    'data-bs-dismiss' => 'click',
                    'data-bs-placement' => 'right',
                ],
            ],
            'arrow' => false,
            'sub' => [
                'class' => 'menu-sub-dropdown w-225px px-1 py-4',
                'items' => [
                    [
                        'classes' => ['content' => ''],
                        'content' => '<span class="menu-section fs-5 fw-bolder ps-1 py-1">Resources</span>',
                    ],

                    // Documentation
                    [
                        'title' => 'Documentation',
                        'attributes' => [
                            'link' => [
                                'title' => 'Check out the complete documentation',
                                'data-bs-toggle' => 'tooltip',
                                'data-bs-trigger' => 'hover',
                                'data-bs-dismiss' => 'click',
                                'data-bs-placement' => 'right',
                            ],
                        ],
                        'icon' => [
                            'svg' => Theme::getSvgIcon('icons/duotune/abstract/abs027.svg', 'svg-icon-2'),
                            'font' => '<i class="bi bi-box fs-3"></i>',
                        ],
                        'path' => 'documentation/getting-started/overview',
                    ],

                    // Changelog
                    [
                        'title' => 'Changelog v'.Theme::getVersion(),
                        'icon' => [
                            'svg' => Theme::getSvgIcon('icons/duotune/general/gen005.svg', 'svg-icon-2'),
                            'font' => '<i class="bi bi-card-text fs-3"></i>',
                        ],
                        'path' => 'documentation/getting-started/changelog',
                    ],
                ],
            ],
        ],
    ],
];
