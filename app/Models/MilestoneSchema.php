<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MilestoneSchema extends Model
{
    use HasFactory;

    protected $table = 'milestone_schemas';

    protected $fillable = [
        'id',
        'schema_name',
        'schema_description',
        'status',
        'is_updated_once',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function milestone_trigger(): HasMany
    {
        return $this->hasMany(MilestoneSchemaTrigger::class, 'milestone_schema_id', 'id');
    }

    public function paymentsCount(): HasMany
    {
        return $this->hasMany(MilestoneSchemaTrigger::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(ProductMilestoneHistories::class, 'milestone_schema_id', 'id');
    }

    protected function formatChange($field)
    {
        if (is_array($field) && isset($field['new'], $field['old'])) {
            return 'changed from '.$field['old'].' to '.$field['new'];
        }

        return '';
    }
}
