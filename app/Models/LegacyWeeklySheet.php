<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// use App\Core\Traits\SpatieLogsActivity;

class LegacyWeeklySheet extends Model
{
    use HasFactory;

    protected $table = 'legacy_weekly_sheet';

    protected $fillable = [
        'crm_id',
        'year',
        'month',
        'week',
        'week_date',
        'no_of_records',
        'new_pid',
        'sheet_type',
        'new_records',
        'updated_records',
        'contact_pushed',
        'errors',
        'status_json',
        'log_file_name',
        'in_process', // 0 = Completed, 1 = In-Progress
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function exceldata(): HasMany
    {
        return $this->hasMany(ImportExpord::class, 'weekly_sheet_id', 'id')->select('weekly_sheet_id', 'pid')->groupBy('pid');
    }
}
