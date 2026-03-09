<?php

namespace App\Http\Controllers;

use App\Jobs\supervisorTestJob;
use Illuminate\Http\Request;

class supervisorTestController extends Controller
{
    public function testSupervisor(Request $request)
    {
        supervisorTestJob::Dispatch($request->all());
    }
}
