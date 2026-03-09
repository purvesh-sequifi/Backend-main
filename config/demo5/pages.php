<?php

return [
    '' => [
        'title' => 'All Questions',
        'description' => '(6,299)',
        'view' => 'apps/devs/index',
        'layout' => [
            'page-title' => [
                'description' => true,
                'breadcrumb' => false,
            ],
            'toolbar' => [
                'primary-button-label' => 'Ask Question',
                'primary-button-url' => 'apps/devs/ask',
            ],
            'sidebar' => [
                'display' => true, // Display sidebar
            ],
        ],
        'assets' => [
            'vendors' => ['fullcalendar'],
        ],
    ],

    'apps' => [
        'devs' => [
            'ask' => [
                'title' => 'Ask a Questions',
                'description' => '(or any community post)',
                'view' => 'apps/devs/ask',
                'layout' => [
                    'sidebar' => [
                        'display' => true,
                    ],
                ],
                'assets' => [
                    'custom' => [
                        'js' => [],
                    ],
                ],
            ],

            'search' => [
                'title' => 'Ask a Questions',
                'description' => '(or any community post)',
                'view' => 'apps/devs/search',
                'layout' => [
                    'toolbar' => [
                        'display' => false,
                    ],
                    'sidebar' => [
                        'display' => true,
                        'search' => false,
                    ],
                ],
            ],

            'tag' => [
                'title' => 'Tag: Metronic',
                'description' => '(1,850 questions)',
                'view' => 'apps/devs/tag',
                'layout' => [
                    'toolbar' => [
                        'primary-button-label' => 'Ask Question',
                        'primary-button-url' => 'apps/devs/ask',
                    ],
                    'sidebar' => [
                        'display' => true,
                    ],
                ],
            ],

            'question' => [
                'title' => 'How to use Metronic with Laravel Framework ?',
                'description' => '',
                'view' => 'apps/devs/question',
                'layout' => [
                    'toolbar' => [
                        'display' => false,
                    ],
                    'sidebar' => [
                        'display' => true,
                    ],
                ],
            ],
        ],
    ],
];
