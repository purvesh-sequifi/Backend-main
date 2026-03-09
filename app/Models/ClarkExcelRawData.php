<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClarkExcelRawData extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'clark_excel_raw_data';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'pid',
        'customer_name',
        'data_source_type',
        'source_created_at',
        'source_updated_at',
        'closer1_id',
        'sales_rep_name',
        'sales_rep_email',
        'product',
        'product_id',
        'Source',
        'job_status',
        'customer_signoff',
        'WorkDate',
        'OrigWorkDate',
        'trigger_date',
        'gross_account_value',
        'date_cancelled',
        'initial_service_date',
        'auto_pay',
        'initial_service_cost',
        'Completed',
        'UpgradeDate',
        'Orig_Monthly',
        'UpgradeMonthly',
        'Notes',
        'last_updated_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'source_created_at' => 'date',
        'source_updated_at' => 'date',
        'closer1_id' => 'integer',
        'customer_signoff' => 'date',
        'WorkDate' => 'date',
        'OrigWorkDate' => 'date',
        'trigger_date' => 'json',
        'gross_account_value' => 'float',
        'date_cancelled' => 'date',
        'initial_service_date' => 'date',
        'initial_service_cost' => 'float',
        'UpgradeDate' => 'date',
        'Orig_Monthly' => 'float',
        'UpgradeMonthly' => 'float',
        'last_updated_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get records by data source type
     */
    public static function getBySourceType(string $sourceType): Collection
    {
        return self::where('data_source_type', $sourceType)->get();
    }

    /**
     * Find by pid
     */
    public static function findByPid(string $pid): ?ClarkExcelRawData
    {
        return self::where('pid', $pid)->first();
    }

    /**
     * Get records by sales rep name
     */
    public static function getBySalesRep(string $salesRepName): Collection
    {
        return self::where('sales_rep_name', $salesRepName)->get();
    }

    /**
     * Get records by sales rep email
     */
    public static function getBySalesRepEmail(string $email): Collection
    {
        return self::where('sales_rep_email', $email)->get();
    }

    /**
     * Get records created between date range
     */
    public static function getByDateRange(string $startDate, string $endDate): Collection
    {
        return self::whereBetween('created_at', [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay(),
        ])->get();
    }
}
