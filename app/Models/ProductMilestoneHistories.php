<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductMilestoneHistories extends Model
{
    use HasFactory;

    protected $table = 'product_milestone_histories';

    protected $fillable = [
        'id',
        'product_id',
        'milestone_schema_id',
        'clawback_exempt_on_ms_trigger_id',
        'override_on_ms_trigger_id',
        'product_redline',
        'effective_date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function milestone(): HasOne
    {
        return $this->hasOne(MilestoneSchema::class, 'id', 'milestone_schema_id');
    }

    public function milestoneSchema(): HasOne
    {
        return $this->hasOne(MilestoneSchema::class, 'id', 'milestone_schema_id')->withCount('paymentsCount');
    }

    public function MilestoneSchemaTrigger(): HasOne
    {
        return $this->hasOne(MilestoneSchemaTrigger::class, 'id', 'clawback_exempt_on_ms_trigger_id');
    }

    public function milestoneSchemaTriggerCount(): HasOne
    {
        return $this->hasOne(MilestoneSchema::class, 'id', 'milestone_schema_id')->withCount('paymentsCount');
    }

    public function productList(): HasOne
    {
        return $this->hasOne(Products::class, 'id', 'product_id');
    }
}
