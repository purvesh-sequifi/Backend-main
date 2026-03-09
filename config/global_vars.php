<?php

use App\Models\TierDuration;

return [

    'CORE_POSITION_DISPLAY' => ['dev1', 'demo', 'preprod', 'aveyo', 'aveyo2', 'flex'],
    'DEFAULT_PRODUCT_ID' => 'DBP',
    'REDLINE_TYPE_ARRAY' => ['Fixed', 'Shift Based on Location', 'Shift Based on Product', 'Shift Based on Product & Location'],
    'TIER_RESET' => [TierDuration::WEEKLY, TierDuration::MONTHLY, TierDuration::QUARTERLY, TierDuration::SEMI_ANNUALLY, TierDuration::ANNUALLY],

    'DEMO_SERVERS' => ['pdev', 'pstage', 'sdev', 'sstage', 'mstage', 'uat', 'preprod', 'solardemo', 'pest', 'pestdev', 'fiber', 'mortgage', 'turf', 'adam'],

];
