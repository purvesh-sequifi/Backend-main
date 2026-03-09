<?php

namespace App\Http\Controllers\API\TicketSystem\Module;

use App\Http\Controllers\API\TicketSystem\BaseResponse\BaseController;
use App\Models\TicketModule;

class TicketModuleController extends BaseController
{
    private TicketModule $ticketModule;

    public function __construct(TicketModule $ticketModule)
    {
        $this->ticketModule = $ticketModule;
    }

    public function dropdown()
    {
        $modules = $this->ticketModule::query()->select('id', 'jira_summary')->get();
        $this->successResponse('Module Dropdown Data!!', 'Ticket Module Dropdown', $modules);
    }
}
