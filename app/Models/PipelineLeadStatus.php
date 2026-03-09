<?php

namespace App\Models;

// use AWS\CRT\Log;
use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PipelineLeadStatus extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $table = 'pipeline_lead_status';

    // public $search;

    protected $fillable = [
        'id',
        'status_name',
        'display_order',
        'hide_status',
        'colour_code',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function leads(): HasMany
    {
        // $search = $this->search;
        // Log::info('SEARCH------- '.$search);
        return $this->hasMany(\App\Models\Lead::class, 'pipeline_status_id', 'id')->with('recruiter', 'reportingManager')
            ->where('type', 'lead')->where('status', '!=', 'Hired')
            ->select('id', 'source', 'first_name', 'last_name', 'email', 'mobile_no', 'pipeline_status_date', 'pipeline_status_id', 'recruiter_id', 'reporting_manager_id',
                DB::raw('DATEDIFF(now(),`pipeline_status_date`) as days_in_status, overall_rating'), 'background_color');
        // ->where(function($user_qry) use($search) {
        //     $user_qry->where('first_name','like','%'.trim($search).'%')
        //     ->orWhere('last_name','like','%'.trim($search).'%');
        // });
    }

    // LeadsSubTask
    public function sub_tasks(): HasMany
    {
        return $this->hasMany(PipelineSubTask::class, 'pipeline_lead_status_id');
    }

    /**
     * Do Not Use completedSubTasks, incompleteSubTasks
     * A Pipeline Subtask cant mark as completed or incompleted
     * Only Lead subtask can be mark
     */
    public function completedSubTasks(): HasMany
    {
        return $this->hasMany(\App\Models\PipelineSubTask::class, 'pipeline_lead_status_id')->where('status', 1);
    }

    public function incompleteSubTasks(): HasMany
    {
        return $this->hasMany(\App\Models\PipelineSubTask::class, 'pipeline_lead_status_id')->where('status', 0);
    }

    public function pipelineComments(): HasMany
    {
        return $this->hasMany(PipelineComment::class, 'pipeline_lead_status_id');
    }
}
