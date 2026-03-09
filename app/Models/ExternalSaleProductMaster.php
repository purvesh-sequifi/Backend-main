<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExternalSaleProductMaster extends Model
{
    protected $table = 'external_sale_product_master';

    protected $fillable = [
        'pid',
        'product_id',
        'milestone_id',
        'milestone_schema_id',
        'milestone_date',
        'type',
        'is_last_date',
        'is_exempted',
        'is_override',
        'is_projected',
        'is_paid',
        'worker_id',
        'worker_type',
        'amount',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function milestoneSchemaTrigger(): HasOne
    {
        return $this->hasOne(MilestoneSchemaTrigger::class, 'id', 'milestone_schema_id');
    }

    public function productdata(): HasOne
    {
        return $this->hasOne(\App\Models\Products::class, 'id', 'product')->where('status', 1);
    }

    public function worker(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'worker_id')->select('id', 'first_name', 'last_name', 'email', 'image', 'office_id', 'sub_position_id', 'terminate', 'dismiss', 'contract_ended', 'stop_payroll')->with('office', 'reconciliations');
    }
}
