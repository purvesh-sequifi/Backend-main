<?php

return [
    'documentation' => [
        // Apply for all documentation pages
        '*' => [
            // Layout
            'layout' => [
                // Aside
                'aside' => [
                    'display' => true, // Display aside
                    'theme' => 'light', // Set aside theme(dark|light)
                    'minimize' => false, // Allow aside minimize toggle
                    'menu' => 'documentation', // Set aside menu type(main|documentation)
                ],

                'header' => [
                    'left' => 'page-title',
                ],

                'toolbar' => [
                    'display' => false,
                ],

                'page-title' => [
                    'layout' => 'documentation',
                    'description' => false,
                    'responsive' => true,
                    'responsive-target' => '#kt_header_nav', // Responsive target selector
                ],
            ],
        ],
    ],
];
