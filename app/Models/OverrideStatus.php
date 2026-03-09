<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OverrideStatus extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'override_status';

    protected $fillable = [
        'user_id',
        'recruiter_id',
        'type',
        'status',
        'effective_date',
        'product_id',
        'updated_by',
        'created_at',
        'updated_at',
    ];
    // protected $hidden = [
    //     'created_at',
    //      'updated_at'
    //     ];

    public function updated_by(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'updated_by');
    }

    public function user(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id');
    }

    public function recruiter(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'recruiter_id');
    }
}
