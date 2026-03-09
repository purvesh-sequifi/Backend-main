<?php

return [
    '' => [
        'title' => 'Hello, Paul',
        'description' => 'You’ve got 24 New Sales',
        'view' => 'index',
        'layout' => [
            'page-title' => [
                'description' => true,
                'breadcrumb' => false,
            ],
        ],
    ],

    'dashboards' => [
        'compact' => null,
        'header' => null,
    ],

    'apps' => [
        'support-center' => [
            '*' => [
                // Layout
                'layout' => [
                    'main' => [
                        'body' => [
                            'class' => 'page-bg-image-lg',
                        ],
                    ],
                ],

                // Aside
                'aside' => [
                    'display' => false,
                ],

                // Toolbar
                'toolbar' => [
                    'display' => false,
                ],
            ],
        ],
    ],

    'layouts' => null,
];
