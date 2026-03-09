<?php

namespace App\Models;

// use AWS\CRT\Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BucketSubTaskByJob extends Model
{
    use HasFactory;

    protected $table = 'bucket_subtask_by_job';

    // public $search;

    protected $fillable = [
        'id',
        'bucket_sutask_id',
        'job_id',
        'status',
        'date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function bucketsubtask(): HasMany
    {
        return $this->hasMany(\App\Models\BucketSubTask::class, 'bucket_id', 'bucket_sutask_id');
    }
}
