<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PipelineSubTask extends Model
{
    /**
     * status = 0 is incomplete
     * status = 1 is complete
     */
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $fillable = ['pipeline_lead_status_id', 'description'];

    protected $table = 'pipeline_sub_tasks';

    // protected $hidden = ['deleted_at'];

    /**
     * PipelineLeadStatus is kind of bucket
     * so here i am relating subtask to pipeline(bucket)
     */
    public function pipeline_lead_status(): BelongsTo
    {
        return $this->belongsTo(PipelineLeadStatus::class);
    }

    public function completedByLeads(): HasMany
    {
        return $this->hasMany(PipelineSubTaskCompleteByLead::class, 'pipeline_sub_task_id')->where('completed', '1');
    }

    // protected static function boot()
    // {
    //     parent::boot();

    //     static::deleting(function ($pipelineSubTask) {
    //         // Delete related PipelineSubTaskCompleteByLead records
    //         $pipelineSubTask->completedByLeads()->delete();
    //     });
    // }
}
