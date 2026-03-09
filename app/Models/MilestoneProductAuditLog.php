<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MilestoneProductAuditLog extends Model
{
    use HasFactory;

    protected $table = 'milestone_product_audiotlogs';

    protected $fillable = [
        'id',
        'reference_id',
        'effective_on_date',
        'type',
        'event',
        'description',
        'user_id',
        'group',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function users(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function milestoneSchema(): HasOne
    {
        return $this->hasOne(MilestoneSchema::class, 'id', 'reference_id');
    }

    public function products(): HasOne
    {
        return $this->hasOne(Products::class, 'id', 'reference_id')->withTrashed();
    }

    public function tiersSchema(): HasOne
    {
        return $this->hasOne(TiersSchema::class, 'id', 'reference_id')->withTrashed();
    }

    public static function formatChange($data, $field)
    {
        if ($field == 'status') {
            if (is_array($data) && isset($data['new'], $data['old'])) {
                $old = '';
                if ($data['old'] == '1') {
                    $old = 'Activated';
                } else {
                    $old = 'Archived';
                }
                $new = '';
                if ($data['new'] == '1') {
                    $new = 'Activated';
                } else {
                    $new = 'Archived';
                }

                return 'changed from <span style="color: red; font-weight: bold;">'.$old.'</span> to <span style="color: green; font-weight: bold;">'.$new.'</span>';
            }
        } else {
            if (is_array($data) && isset($data['new'], $data['old'])) {
                return 'changed from <span style="color: red; font-weight: bold;">'.$data['old'].'</span> to <span style="color: green; font-weight: bold;">'.$data['new'].'</span>';
            }
        }

        return '';
    }

    public static function productFormatChange($data, $field, $finalSchema = [], $finalTrigger = [])
    {
        if ($field == 'status') {
            if (is_array($data) && isset($data['new'], $data['old'])) {
                $old = '';
                if ($data['old'] == '1') {
                    $old = 'Activated';
                } else {
                    $old = 'Archived';
                }
                $new = '';
                if ($data['new'] == '1') {
                    $new = 'Activated';
                } else {
                    $new = 'Archived';
                }

                return 'changed from <span style="color: red; font-weight: bold;">"'.$old.'"</span> to <span style="color: green; font-weight: bold;">"'.$new.'"</span>';
            }
        } elseif ($field == 'milestone_schema_id') {
            $old = @$finalSchema[$data['old']];
            $new = @$finalSchema[$data['new']];

            return 'changed from <span style="color: red; font-weight: bold;">"'.$old.'"</span> to <span style="color: green; font-weight: bold;">"'.$new.'"</span>';
        } elseif ($field == 'clawback_exempt_on_ms_trigger_id') {
            $old = @$finalTrigger[$data['old']];
            $new = @$finalTrigger[$data['new']];

            return 'changed from <span style="color: red; font-weight: bold;">"'.($old ? $old : 'NONE').'"</span> to <span style="color: green; font-weight: bold;">"'.($new ? $new : 'None').'"</span>';
        } elseif ($field == 'override_on_ms_trigger_id') {
            $old = @$finalTrigger[$data['old']];
            $new = @$finalTrigger[$data['new']];

            return 'changed from <span style="color: red; font-weight: bold;">"'.($old ? $old : 'NONE').'"</span> to <span style="color: green; font-weight: bold;">"'.($new ? $new : 'None').'"</span>';
        } else {
            if (is_array($data)) {
                return 'changed from <span style="color: red; font-weight: bold;">"'.@$data['old'].'"</span> to <span style="color: green; font-weight: bold;">"'.@$data['new'].'"</span>';
            }
        }

        return '';
    }
}
