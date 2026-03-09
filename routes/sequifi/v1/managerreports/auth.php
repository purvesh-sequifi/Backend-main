<?php

use App\Http\Controllers\API\ManagerReport\ManagerReportsControllerV1;

Route::post('/past_pay_stub_detail_list', [ManagerReportsControllerV1::class, 'pastPayStubDetailList']);
