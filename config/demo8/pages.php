<?php

return [
    '' => [
        'title' => 'Dashboard',
        'description' => '#XRS-45670',
        'view' => 'index',
        'layout' => [
            'page-title' => [
                'description' => false,
                'breadcrumb' => true,
            ],
        ],
        'assets' => [
            'vendors' => ['fullcalendar'],
            'layout' => [
                'js' => [
                    'js/layout/toolbar.js',
                ],
            ],
        ],
    ],
];
