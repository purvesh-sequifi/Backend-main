<?php

namespace App\Models;

// use AWS\CRT\Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Crmattachments extends Model
{
    use HasFactory;

    protected $table = 'crm_attachments';

    // public $search;

    protected $fillable = [
        'id',
        'user_id',
        'job_id',
        'bucket_id',
        'comments_id',
        'path_id',
        'path',
        'status',
        'created_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function bucketbyjob(): HasOne
    {
        // return $this->hasOne('App\Models\Bucketbyjob','job_id', 'id');
        return $this->hasOne(\App\Models\Bucketbyjob::class, 'job_id', 'id')->select('*')->where('active', 1);
    }

    public function BucketSubTaskByJob(): HasMany
    {
        return $this->hasMany(\App\Models\BucketSubTaskByJob::class, 'job_id', 'id');
    }

    public function sales(): HasOne
    {
        return $this->hasOne(\App\Models\SalesMaster::class, 'pid', 'pid')->with('salesMasterProcess', 'userDetail');
    }

    public function bucketbyjoballl(): HasOne
    {
        // return $this->hasOne('App\Models\Bucketbyjob','job_id', 'id');
        return $this->hasMany(\App\Models\Bucketbyjob::class, 'job_id', 'id')->with('bucketinfo');
    }

    public function bucketlist(): HasOne
    {
        // return $this->hasOne('App\Models\Bucketbyjob','job_id', 'id');
        return $this->hasMany(\App\Models\Buckets::class);
    }

    public function bucketbycomments(): HasMany
    {
        return $this->hasMany(\App\Models\CrmComments::class, 'job_id', 'id')->with('users', 'attachments');
    }

    public function buckets(): HasOne
    {
        return $this->hasOne(\App\Models\Buckets::class, 'id', 'bucket_id');
    }
}
