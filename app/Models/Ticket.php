<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Ticket extends Model
{
    use HasFactory;

    const JIRA_ISSUE_TYPE_Id = '10009'; // Jira IssueType Id From Jira // rest/api/2/issuetype

    const JIRA_PROJECT_Id = '10006'; // Jira Project Id From Jira // rest/api/2/project

    const JIRA_HIGH_PRIORITY_Id = '2'; // Jira High Priority Id From Jira // rest/api/2/priority

    const JIRA_MEDIUM_PRIORITY_Id = '3'; // Jira Medium Priority Id From Jira // rest/api/2/priority

    const JIRA_LOW_PRIORITY_Id = '4'; // Jira Low Priority Id From Jira // rest/api/2/priority

    const JIRA_TAX_TYPE_PARENT_ID = '15559'; // Jira IssueType Id // rest/api/2/issuetype

    const JIRA_ASSIGNEE_ID = '5fc5b816dd0c5900752f8bef'; // Jira Assignee Id

    const IS_JIRA_SYNCED = '1'; // Synced

    const IS_JIRA_NOT_SYNCED = '0'; // Not Synced

    const IS_JIRA_DONE_STATUS = 'Done'; // Done Jira Status

    const IS_JIRA_TO_DO_STATUS = 'To Do'; // To Do Jira Status

    const IS_JIRA_IN_PROGRESS_STATUS = 'In Progress'; // In Progress Jira Status

    protected $table = 'tickets';

    protected $fillable = [
        'created_by',
        'ticket_id',
        'jira_ticket_id',
        'summary',
        'priority',
        'module',
        'jira_module_id',
        'description',
        'is_jira_created',
        'last_jira_sync_date',
        'ticket_status',
        'estimated_time',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function ticketAttachment(): MorphMany
    {
        return $this->morphMany(TicketAttachment::class, 'attachment');
    }

    public function createdByUser(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'created_by');
    }

    public function module(): HasOne
    {
        return $this->hasOne(TicketModule::class, 'jira_id', 'jira_module_id');
    }

    /**
     * @return mixed
     */
    public function scopeTicketDataUserWise($query)
    {
        if (auth()->user()->is_super_admin != '1') {
            return $query->where('created_by', auth()->user()->id);
        }

        return $query;
    }
}
