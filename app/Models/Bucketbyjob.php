<?php

namespace App\Models;

// use AWS\CRT\Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bucketbyjob extends Model
{
    use HasFactory;

    protected $table = 'bucket_by_job';

    // public $search;

    protected $fillable = [
        'id',
        'bucket_id',
        'job_id',
        'active',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function buckets(): HasOne
    {
        return $this->hasOne(\App\Models\Buckets::class, 'id', 'bucket_id')->select('bucket_id', 'job_id', 'active')->where('active', 1);
    }

    public function bucketinfo(): HasOne
    {
        return $this->hasOne(\App\Models\Buckets::class, 'id', 'bucket_id')->with('bucketsubtasks');
    }

    public function bucketbyjobbucket(): HasOne
    {
        return $this->hasOne(\App\Models\Buckets::class, 'id', 'bucket_id');
    }
}
