<?php

return [
    '' => [
        'title' => 'Dashboard',
        'description' => '',
        'view' => 'index',
        'layout' => [
            'page-title' => [
                'description' => true,
                'breadcrumb' => false,
            ],
        ],
        'assets' => [
            'custom' => [
                'js' => [
                    'js/widgets.bundle.js',
                ],
            ],
            'vendors' => ['fullcalendar', 'amcharts', 'amcharts-maps'],
        ],
    ],

    'login' => [
        'title' => 'Login',
        'assets' => [
            'custom' => [
                'js' => [
                    'js/custom/authentication/sign-in/general.js',
                ],
            ],
        ],
        'layout' => [
            'main' => [
                'type' => 'blank', // Set blank layout
                'body' => [
                    'class' => theme()->isDarkMode() ? '' : 'bg-body',
                ],
            ],
        ],
    ],
    'register' => [
        'title' => 'Register',
        'assets' => [
            'custom' => [
                'js' => [
                    'js/custom/authentication/sign-up/general.js',
                ],
            ],
        ],
        'layout' => [
            'main' => [
                'type' => 'blank', // Set blank layout
                'body' => [
                    'class' => theme()->isDarkMode() ? '' : 'bg-body',
                ],
            ],
        ],
    ],
    'forgot-password' => [
        'title' => 'Forgot Password',
        'assets' => [
            'custom' => [
                'js' => [
                    'js/custom/authentication/password-reset/password-reset.js',
                ],
            ],
        ],
        'layout' => [
            'main' => [
                'type' => 'blank', // Set blank layout
                'body' => [
                    'class' => theme()->isDarkMode() ? '' : 'bg-body',
                ],
            ],
        ],
    ],

    'log' => [
        'audit' => [
            'title' => 'Audit Log',
            'assets' => [
                'custom' => [
                    'css' => [
                        'plugins/custom/datatables/datatables.bundle.css',
                    ],
                    'js' => [
                        'plugins/custom/datatables/datatables.bundle.js',
                    ],
                ],
            ],
        ],
        'system' => [
            'title' => 'System Log',
            'assets' => [
                'custom' => [
                    'css' => [
                        'plugins/custom/datatables/datatables.bundle.css',
                    ],
                    'js' => [
                        'plugins/custom/datatables/datatables.bundle.js',
                    ],
                ],
            ],
        ],
    ],

    'error' => [
        'error-404' => [
            'title' => 'Error 404',
        ],
        'error-500' => [
            'title' => 'Error 500',
        ],
    ],

    'account' => [
        'overview' => [
            'title' => 'Account Overview',
            'view' => 'account/overview/overview',
            'assets' => [
                'custom' => [
                    'js' => [
                        'js/custom/widgets.js',
                    ],
                ],
            ],
        ],

        'settings' => [
            'title' => 'Account Settings',
            'assets' => [
                'custom' => [
                    'js' => [
                        'js/custom/account/settings/profile-details.js',
                        'js/custom/account/settings/signin-methods.js',
                        'js/custom/modals/two-factor-authentication.js',
                    ],
                ],
            ],
        ],
    ],

    'users' => [
        'title' => 'User List',

        '*' => [
            'title' => 'Show User',

            'edit' => [
                'title' => 'Edit User',
            ],
        ],
    ],

    // Documentation pages
    'documentation' => [
        '*' => [
            'assets' => [
                'vendors' => ['prismjs'],
                'custom' => [
                    'js' => [
                        'js/custom/documentation/documentation.js',
                    ],
                ],
            ],

            'layout' => [
                'base' => 'docs', // Set base layout: default|docs

                // Content
                'content' => [
                    'width' => 'fixed', // Set fixed|fluid to change width type
                    'layout' => 'documentation',  // Set content type
                ],
            ],
        ],

        'getting-started' => [
            'overview' => [
                'title' => 'Overview',
                'description' => '',
                'view' => 'documentation/getting-started/overview',
            ],

            'build' => [
                'title' => 'Gulp',
                'description' => '',
                'view' => 'documentation/getting-started/build/build',
            ],

            'multi-demo' => [
                'overview' => [
                    'title' => 'Overview',
                    'description' => '',
                    'view' => 'documentation/getting-started/multi-demo/overview',
                ],
                'build' => [
                    'title' => 'Multi-demo Build',
                    'description' => '',
                    'view' => 'documentation/getting-started/multi-demo/build',
                ],
            ],

            'file-structure' => [
                'title' => 'File Structure',
                'description' => '',
                'view' => 'documentation/getting-started/file-structure',
            ],

            'customization' => [
                'sass' => [
                    'title' => 'SASS',
                    'description' => '',
                    'view' => 'documentation/getting-started/customization/sass',
                ],
                'javascript' => [
                    'title' => 'Javascript',
                    'description' => '',
                    'view' => 'documentation/getting-started/customization/javascript',
                ],
            ],

            'dark-mode' => [
                'title' => 'Dark Mode Version',
                'view' => 'documentation/getting-started/dark-mode',
            ],

            'rtl' => [
                'title' => 'RTL Version',
                'view' => 'documentation/getting-started/rtl',
            ],

            'troubleshoot' => [
                'title' => 'Troubleshoot',
                'view' => 'documentation/getting-started/troubleshoot',
            ],

            'changelog' => [
                'title' => 'Changelog',
                'description' => 'version and update info',
                'view' => 'documentation/getting-started/changelog/changelog',
            ],

            'updates' => [
                'title' => 'Updates',
                'description' => 'components preview and usage',
                'view' => 'documentation/getting-started/updates',
            ],

            'references' => [
                'title' => 'References',
                'description' => '',
                'view' => 'documentation/getting-started/references',
            ],
        ],

        'general' => [
            'datatables' => [
                'overview' => [
                    'title' => 'Overview',
                    'description' => 'plugin overview',
                    'view' => 'documentation/general/datatables/overview/overview',
                ],
            ],
            'remove-demos' => [
                'title' => 'Remove Demos',
                'description' => 'How to remove unused demos',
                'view' => 'documentation/general/remove-demos/index',
            ],
        ],

        'configuration' => [
            'general' => [
                'title' => 'General Configuration',
                'description' => '',
                'view' => 'documentation/configuration/general',
            ],
            'menu' => [
                'title' => 'Menu Configuration',
                'description' => '',
                'view' => 'documentation/configuration/menu',
            ],
            'page' => [
                'title' => 'Page Configuration',
                'description' => '',
                'view' => 'documentation/configuration/page',
            ],
            'npm-plugins' => [
                'title' => 'Add NPM Plugin',
                'description' => 'Add new NPM plugins and integrate within webpack mix',
                'view' => 'documentation/configuration/npm-plugins',
            ],
        ],
    ],
];
