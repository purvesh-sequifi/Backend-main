<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserSalesOffice extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'user_sales_offices';

    protected $fillable = [
        'office_id',
        'state_id',
        'user_id',
        'status',
        'effective_date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function salesOffice(): HasOne
    {
        return $this->hasOne(\App\Models\SalesOffice::class, 'id', 'office_id');
    }

    public function userDetail(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id');
    }
}
