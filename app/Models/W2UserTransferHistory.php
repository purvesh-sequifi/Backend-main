<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class W2UserTransferHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'w2_user_transfer_histories';

    protected $fillable = [
        'user_id',
        'updater_id',
        'period_of_agreement',
        'employee_transfer_date',
        'contractor_transfer_date',
        'type',
    ];

    protected $hidden = [
        // 'created_at',
        // 'updated_at'
    ];
}
