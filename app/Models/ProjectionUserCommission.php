<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProjectionUserCommission extends Model
{
    use HasFactory;

    protected $table = 'projection_user_commissions';

    protected $fillable = [
        'user_id',
        'milestone_schema_id',
        'product_id',
        'pid',
        'type',
        'schema_name',
        'schema_trigger',
        'is_last',
        'value_type',
        'amount',
        'customer_signoff',
    ];

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name', 'image', 'position_id', 'sub_position_id', 'is_manager', 'is_super_admin', 'stop_payroll');
    }
}
