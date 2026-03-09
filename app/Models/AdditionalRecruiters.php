<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdditionalRecruiters extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $table = 'additional_recruters';

    protected $fillable = [
        'user_id',
        'hiring_id',
        'recruiter_id',
        'system_per_kw_amount',
        'system_type',
    ];

    protected $hidden = [
        'created_at',
    ];

    public function additionalRecruiterDetail(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'recruiter_id')->select('id', 'first_name', 'last_name');
    }
}
