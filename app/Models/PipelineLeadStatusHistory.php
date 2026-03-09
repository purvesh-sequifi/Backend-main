<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PipelineLeadStatusHistory extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'pipeline_leads_status_history';

    protected $fillable = [
        'id',
        'lead_id',
        'old_status_id',
        'new_status_id',
        'updater_id',
    ];

    protected $hidden = [
        // 'created_at',
        // 'updated_at'
    ];

    public function updater(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'updater_id')->select('id', 'first_name', 'last_name');
    }

    public function old_status(): HasOne
    {
        return $this->hasOne(\App\Models\PipelineLeadStatus::class, 'id', 'old_status_id');
    }

    public function new_status(): HasOne
    {
        return $this->hasOne(\App\Models\PipelineLeadStatus::class, 'id', 'new_status_id');
    }
}
