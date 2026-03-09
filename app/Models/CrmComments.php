<?php

namespace App\Models;

// use AWS\CRT\Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CrmComments extends Model
{
    use HasFactory;

    protected $table = 'crm_comments';

    // public $search;

    protected $fillable = [
        'id',
        'user_id',
        'job_id',
        'bucket_id',
        'comments_parent_id',
        'comments',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function users(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(\App\Models\Crmattachments::class, 'comments_id', 'id');
    }
}
