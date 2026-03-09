<?php

return [
    'scheduling' => [
        'clock_format' => [
            '0' => '12-Hour',
            '1' => '24-Hour',
        ],
        'lunch_duration' => [
            '0' => 'None',
            '1' => '30 Min',
            '2' => '60 Min',
            '3' => '90 Min',
            '4' => '120 Min',
        ],
        // 'day_no' => [
        //     '0' => 'Sunday',
        //     '1' => 'Monday',
        //     '2' => 'Tuesday',
        //     '3' => 'Wednesday',
        //     '4' => 'Thursday',
        //     '5' => 'Friday',
        //     '6' => 'Saturday',
        // ]

        'day_no' => [
            '1' => 'Monday',
            '2' => 'Tuesday',
            '3' => 'Wednesday',
            '4' => 'Thursday',
            '5' => 'Friday',
            '6' => 'Saturday',
            '7' => 'Sunday',
        ],
    ],

    'exclude_users_from_active_billing_by_email' => [
        'devadmin@sequifi.com',
        'csteam@sequifi.com'
    ]
];
