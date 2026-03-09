<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FrequencyType extends Model
{
    use HasFactory, SpatieLogsActivity;

    const WEEKLY_ID = 2;

    const MONTHLY_ID = 5;

    const BI_WEEKLY_ID = 3;

    const SEMI_MONTHLY_ID = 4;

    const DAILY_PAY_ID = 6;

    protected $fillable = [
        'name',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public static function getFrequencyType($id)
    {
        return self::find($id)?->name ?? 'Unknown';
    }
}
