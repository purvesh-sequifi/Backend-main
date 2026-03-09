<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Products extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'id',
        'name',
        'product_id',
        'description',
        'milestone_schema_id',
        'clawback_exempt_on_ms_trigger_id',
        'effective_date',
        'status',
        'deleted_at',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function milestoneSchema(): HasOne
    {
        return $this->hasOne(MilestoneSchema::class, 'id', 'milestone_schema_id');
    }

    public function milestoneSchemaTrigger(): HasOne
    {
        return $this->hasOne(MilestoneSchemaTrigger::class, 'id', 'clawback_exempt_on_ms_trigger_id');
    }

    public function productMilestoneHistories(): HasMany
    {
        return $this->hasMany(ProductMilestoneHistories::class, 'product_id', 'id');
    }

    public function currentProductMilestoneHistories(): HasOne
    {
        $currentDate = Carbon::today()->format('Y-m-d');

        return $this->hasOne(ProductMilestoneHistories::class, 'product_id', 'id')
            ->where(function ($query) use ($currentDate) {
                $query->whereDate('effective_date', '<=', $currentDate)
                    ->orWhereDate('effective_date', '>', $currentDate);
            })->orderByRaw('CASE 
                WHEN effective_date <= ? THEN 0 
                ELSE 1 
            END, effective_date DESC, id DESC', [$currentDate]);

        // $currentDate = Carbon::today()->format('Y-m-d');
        // return $this->hasOne(ProductMilestoneHistories::class, 'product_id', 'id')
        //     ->with(['milestoneSchema', 'MilestoneSchemaTrigger'])
        //     ->where(function ($query) use ($currentDate) {
        //         // Include both past and upcoming dates
        //         $query->whereDate('effective_date', '<=', $currentDate)
        //             ->orWhereDate('effective_date', '>', $currentDate);
        //     })
        //     ->orderByRaw('CASE
        //         WHEN effective_date <= ? THEN 0
        //         ELSE 1
        //     END,  effective_date DESC, id DESC', [$currentDate]);
        // // Get the latest by effective_date
    }

    public function productMilestoneHistoriesCurrent(): HasOne
    {
        $currentDate = Carbon::today()->format('Y-m-d');

        return $this->hasOne(ProductMilestoneHistories::class, 'product_id', 'id')
            ->where(function ($query) use ($currentDate) {
                $query->whereDate('effective_date', '<=', $currentDate);
            })->orderByRaw('CASE 
                WHEN effective_date <= ? THEN 0 
                ELSE 1 
            END, effective_date DESC, id DESC', [$currentDate]);

        // $currentDate = Carbon::today()->format('Y-m-d');
        // return $this->hasOne(ProductMilestoneHistories::class, 'product_id', 'id')
        //     ->with(['milestoneSchema', 'MilestoneSchemaTrigger'])
        //     ->where(function ($query) use ($currentDate) {
        //         // Include both past and upcoming dates
        //         $query->whereDate('effective_date', '<=', $currentDate)
        //             ->orWhereDate('effective_date', '>', $currentDate);
        //     })
        //     ->orderByRaw('CASE
        //         WHEN effective_date <= ? THEN 0
        //         ELSE 1
        //     END,  effective_date DESC, id DESC', [$currentDate]);
        // // Get the latest by effective_date
    }

    public function currentProductMilestoneHistoriesList(): HasOne
    {
        $currentDate = Carbon::today()->format('Y-m-d');

        return $this->hasOne(ProductMilestoneHistories::class, 'product_id', 'id')
            ->where(function ($query) use ($currentDate) {
                $query->whereDate('effective_date', '<=', $currentDate)
                    ->orWhereDate('effective_date', '>', $currentDate);
            })->orderByRaw('CASE 
                WHEN effective_date <= ? THEN 0 
                ELSE 1 
            END, effective_date DESC, id DESC', [$currentDate]);
    }

    public function productMilestone(): HasOne
    {
        return $this->hasOne(MilestoneSchema::class, 'id', 'milestone_schema_id')->withCount('paymentsCount');
    }

    public function productmilestonehistory(): HasMany
    {
        return $this->hasMany(ProductMilestoneHistories::class, 'product_id', 'id')
            ->where('effective_date', function ($query) {
                $query->select('effective_date')
                    ->from('product_milestone_histories as tl')
                    ->whereColumn('tl.product_id', 'product_milestone_histories.product_id')
                    ->orderBy('effective_date', 'desc')
                    ->limit(1); // Get the latest effective_date
            });

    }

    public function productToMilestoneTrigger(): HasOne
    {
        return $this->hasOne(MilestoneSchema::class, 'id', 'milestone_schema_id')->with('milestone_trigger');
    }

    public function positionProduct(): HasMany
    {
        return $this->hasMany(PositionProduct::class, 'product_id', 'id')->with('positionDetails');
    }

    protected function logview($event, $data)
    {
        $term = '';
        $descvalue = '';

        switch ($event) {
            case 'updated':
                $descvalue = 'Updated : ';
                if (! empty($data)) {
                    $termParts = [];
                    foreach ($data as $key => $desc) {
                        if ($key == 'name') {
                            $termParts[] = $this->formatTerm($desc);
                            $nupdate = $this->formatChange($desc);
                            if ($nupdate != '') {
                                $descvalue .= '<br>Name: '.$nupdate;
                            }
                        }
                        if ($key == 'description') {
                            $dupdate = $this->formatChange($desc);
                            if ($dupdate != '') {
                                $descvalue .= '<br>Description: '.$dupdate;
                            }
                        }
                        if ($key == 'schema_name') {
                            $termParts[] = $this->formatTerm($desc);
                            $sdupdate = $this->formatChange($desc);
                            if ($sdupdate != '') {
                                $descvalue .= '<br>Milestone : '.$sdupdate;
                            }
                        }
                        if ($key == 'ms_trigger_name') {
                            $stdupdate = $this->formatChange($desc);
                            if ($stdupdate != '') {
                                $descvalue .= '<br>Clawback exempt : '.$stdupdate;
                            }
                        }
                        if (isset($desc['productName'])) {
                            $termParts[] = $desc['productName'];
                            $descvalue .= '<br>Status: '.(($desc['status'] == 1) ? 'Active' : 'Inactive');
                        }
                    }
                    $term = implode('<br>', $termParts);
                }
                break;

            case 'created':
                $descvalue = 'New Created : ';
                if (! empty($data)) {
                    if (isset($data['name'])) {
                        $term .= $data['name'];
                        $descvalue .= '<br>Name: '.$data['name'];
                    }
                    if (isset($data['description'])) {
                        $descvalue .= '<br>Description: '.$data['description'];
                    }
                    if (isset($data['schema_name'])) {
                        $descvalue .= '<br>Milestone : '.$data['schema_name'];
                    }
                    if (isset($data['ms_trigger_name'])) {
                        $descvalue .= '<br>Clawback exempt : '.$data['ms_trigger_name'];
                    }
                }
                break;
        }

        return [$term, $descvalue];
    }

    protected function formatTerm($field)
    {
        if (is_array($field) && isset($field['new'], $field['old'])) {
            return $field['new'].'('.$field['old'].')';
        } else {
            return '';
        }
    }

    protected function formatChange($field)
    {
        if (is_array($field) && isset($field['new'], $field['old'])) {
            return $field['old'].' changed to '.$field['new'];
        } else {
            return '';
        }
    }

    protected function setChangeLog($data)
    {
        $changeDetails = [];
        $originalData = $data['originalData'] ?? [];

        foreach ($data['changes'] as $key => $newValue) {
            if ($key === 'updated_at') {
                continue;
            }
            $oldValue = $originalData[$key] ?? null;
            if ($oldValue !== $newValue) {
                // Handling 'clawback_exempt_on_ms_trigger_id'
                if ($key == 'clawback_exempt_on_ms_trigger_id') {
                    $changeDetails['ms_trigger_name'] = [
                        'old' => $oldValue ? MilestoneSchemaTrigger::where('id', $oldValue)->value('name') : null,
                        'new' => $newValue ? MilestoneSchemaTrigger::where('id', $newValue)->value('name') : null,
                    ];
                }
                // Handling 'milestone_schema_id'
                if ($key == 'milestone_schema_id') {
                    $changeDetails['schema_name'] = [
                        'old' => $oldValue ? MilestoneSchema::where('id', $oldValue)
                            ->select(DB::raw("CONCAT(prefix, '_', schema_name) as full_schema_name"))
                            ->value('full_schema_name') : null,
                        'new' => $newValue ? MilestoneSchema::where('id', $newValue)
                            ->select(DB::raw("CONCAT(prefix, '_', schema_name) as full_schema_name"))
                            ->value('full_schema_name') : null,
                    ];
                }
                // Log the original and new values for the changed field
                $changeDetails[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changeDetails;
    }

    public function milestoneSchemaByTriggers(): HasMany
    {
        return $this->hasMany(MilestoneSchemaTrigger::class, 'milestone_schema_id', 'milestone_schema_id');
    }

    /**
     * Get the product codes for the product.
     */
    public function productCodes(): HasMany
    {
        return $this->hasMany(ProductCode::class, 'product_id', 'id');
    }

    // Maps timezone label to PHP-compatible timezone string, with fallback to app timezone.
    public static function mapTimeZone($label)
    {
        return [
            '(UTC-12:00) International Date Line West' => 'Etc/GMT+12',
            '(UTC-11:00) Coordinated Universal Time-11' => 'Etc/GMT+11',
            '(UTC-10:00) Hawaii' => 'Pacific/Honolulu',
            '(UTC-09:00) Alaska' => 'America/Anchorage',
            '(UTC-08:00) Baja California' => 'America/Tijuana',
            '(UTC-07:00) Pacific Daylight Time (US & Canada)' => 'America/Los_Angeles',
            '(UTC-08:00) Pacific Standard Time (US & Canada)' => 'America/Los_Angeles',
            '(UTC-07:00) Arizona' => 'America/Phoenix',
            '(UTC-07:00) Chihuahua, La Paz, Mazatlan' => 'America/Chihuahua',
            '(UTC-07:00) Mountain Time (US & Canada)' => 'America/Denver',
            '(UTC-06:00) Central America' => 'America/Guatemala',
            '(UTC-06:00) Central Time (US & Canada)' => 'America/Chicago',
            '(UTC-06:00) Guadalajara, Mexico City, Monterrey' => 'America/Mexico_City',
            '(UTC-06:00) Saskatchewan' => 'America/Regina',
            '(UTC-05:00) Bogota, Lima, Quito' => 'America/Bogota',
            '(UTC-05:00) Eastern Time (US & Canada)' => 'America/New_York',
            '(UTC-04:00) Eastern Daylight Time (US & Canada)' => 'America/New_York',
            '(UTC-05:00) Indiana (East)' => 'America/Indiana/Indianapolis',
            '(UTC-04:30) Caracas' => 'America/Caracas',
            '(UTC-04:00) Asuncion' => 'America/Asuncion',
            '(UTC-04:00) Atlantic Time (Canada)' => 'America/Halifax',
            '(UTC-04:00) Cuiaba' => 'America/Cuiaba',
            '(UTC-04:00) Georgetown, La Paz, Manaus, San Juan' => 'America/La_Paz',
            '(UTC-04:00) Santiago' => 'America/Santiago',
            '(UTC-03:30) Newfoundland' => 'America/St_Johns',
            '(UTC-03:00) Brasilia' => 'America/Sao_Paulo',
            '(UTC-03:00) Buenos Aires' => 'America/Argentina/Buenos_Aires',
            '(UTC-03:00) Cayenne, Fortaleza' => 'America/Fortaleza',
            '(UTC-03:00) Greenland' => 'America/Godthab',
            '(UTC-03:00) Montevideo' => 'America/Montevideo',
            '(UTC-03:00) Salvador' => 'America/Bahia',
            '(UTC-02:00) Coordinated Universal Time-02' => 'Etc/GMT+2',
            '(UTC-02:00) Mid-Atlantic - Old' => 'America/Noronha',
            '(UTC-01:00) Azores' => 'Atlantic/Azores',
            '(UTC-01:00) Cape Verde Is.' => 'Atlantic/Cape_Verde',
            '(UTC) Casablanca' => 'Africa/Casablanca',
            '(UTC) Coordinated Universal Time' => 'Etc/UTC',
            '(UTC) Edinburgh, London' => 'Europe/London',
            '(UTC+01:00) Edinburgh, London' => 'Europe/London',
            '(UTC) Dublin, Lisbon' => 'Europe/Lisbon',
            '(UTC) Monrovia, Reykjavik' => 'Atlantic/Reykjavik',
            '(UTC+01:00) Amsterdam, Berlin, Bern, Rome, Stockholm, Vienna' => 'Europe/Berlin',
            '(UTC+01:00) Belgrade, Bratislava, Budapest, Ljubljana, Prague' => 'Europe/Belgrade',
            '(UTC+01:00) Brussels, Copenhagen, Madrid, Paris' => 'Europe/Paris',
            '(UTC+01:00) Sarajevo, Skopje, Warsaw, Zagreb' => 'Europe/Warsaw',
            '(UTC+01:00) West Central Africa' => 'Africa/Lagos',
            '(UTC+01:00) Windhoek' => 'Africa/Windhoek',
            '(UTC+02:00) Athens, Bucharest' => 'Europe/Athens',
            '(UTC+02:00) Beirut' => 'Asia/Beirut',
            '(UTC+02:00) Cairo' => 'Africa/Cairo',
            '(UTC+02:00) Damascus' => 'Asia/Damascus',
            '(UTC+02:00) E. Europe' => 'Europe/Bucharest',
            '(UTC+02:00) Harare, Pretoria' => 'Africa/Harare',
            '(UTC+02:00) Helsinki, Kyiv, Riga, Sofia, Tallinn, Vilnius' => 'Europe/Helsinki',
            '(UTC+03:00) Istanbul' => 'Europe/Istanbul',
            '(UTC+02:00) Jerusalem' => 'Asia/Jerusalem',
            '(UTC+02:00) Tripoli' => 'Africa/Tripoli',
            '(UTC+03:00) Amman' => 'Asia/Amman',
            '(UTC+03:00) Baghdad' => 'Asia/Baghdad',
            '(UTC+02:00) Kaliningrad' => 'Europe/Kaliningrad',
            '(UTC+03:00) Kuwait, Riyadh' => 'Asia/Riyadh',
            '(UTC+03:00) Nairobi' => 'Africa/Nairobi',
            '(UTC+03:00) Moscow, St. Petersburg, Volgograd, Minsk' => 'Europe/Moscow',
            '(UTC+04:00) Samara, Ulyanovsk, Saratov' => 'Europe/Samara',
            '(UTC+03:30) Tehran' => 'Asia/Tehran',
            '(UTC+04:00) Abu Dhabi, Muscat' => 'Asia/Dubai',
            '(UTC+04:00) Baku' => 'Asia/Baku',
            '(UTC+04:00) Port Louis' => 'Indian/Mauritius',
            '(UTC+04:00) Tbilisi' => 'Asia/Tbilisi',
            '(UTC+04:00) Yerevan' => 'Asia/Yerevan',
            '(UTC+04:30) Kabul' => 'Asia/Kabul',
            '(UTC+05:00) Ashgabat, Tashkent' => 'Asia/Tashkent',
            '(UTC+05:00) Yekaterinburg' => 'Asia/Yekaterinburg',
            '(UTC+05:00) Islamabad, Karachi' => 'Asia/Karachi',
            '(UTC+05:30) Chennai, Kolkata, Mumbai, New Delhi' => 'Asia/Kolkata',
            '(UTC+05:30) Sri Jayawardenepura' => 'Asia/Colombo',
            '(UTC+05:45) Kathmandu' => 'Asia/Kathmandu',
            '(UTC+06:00) Nur-Sultan (Astana)' => 'Asia/Almaty',
            '(UTC+06:00) Dhaka' => 'Asia/Dhaka',
            '(UTC+06:30) Yangon (Rangoon)' => 'Asia/Yangon',
            '(UTC+07:00) Bangkok, Hanoi, Jakarta' => 'Asia/Bangkok',
            '(UTC+07:00) Novosibirsk' => 'Asia/Novosibirsk',
            '(UTC+08:00) Beijing, Chongqing, Hong Kong, Urumqi' => 'Asia/Shanghai',
            '(UTC+08:00) Krasnoyarsk' => 'Asia/Krasnoyarsk',
            '(UTC+08:00) Kuala Lumpur, Singapore' => 'Asia/Singapore',
            '(UTC+08:00) Perth' => 'Australia/Perth',
            '(UTC+08:00) Taipei' => 'Asia/Taipei',
            '(UTC+08:00) Ulaanbaatar' => 'Asia/Ulaanbaatar',
            '(UTC+08:00) Irkutsk' => 'Asia/Irkutsk',
            '(UTC+09:00) Osaka, Sapporo, Tokyo' => 'Asia/Tokyo',
            '(UTC+09:00) Seoul' => 'Asia/Seoul',
            '(UTC+09:30) Adelaide' => 'Australia/Adelaide',
            '(UTC+09:30) Darwin' => 'Australia/Darwin',
            '(UTC+10:00) Brisbane' => 'Australia/Brisbane',
            '(UTC+10:00) Canberra, Melbourne, Sydney' => 'Australia/Sydney',
            '(UTC+10:00) Guam, Port Moresby' => 'Pacific/Port_Moresby',
            '(UTC+10:00) Hobart' => 'Australia/Hobart',
            '(UTC+09:00) Yakutsk' => 'Asia/Yakutsk',
            '(UTC+11:00) Solomon Is., New Caledonia' => 'Pacific/Guadalcanal',
            '(UTC+11:00) Vladivostok' => 'Asia/Vladivostok',
            '(UTC+12:00) Auckland, Wellington' => 'Pacific/Auckland',
            '(UTC+12:00) Coordinated Universal Time+12' => 'Etc/GMT-12',
            '(UTC+12:00) Fiji' => 'Pacific/Fiji',
            '(UTC+12:00) Magadan' => 'Asia/Magadan',
            '(UTC+12:00) Petropavlovsk-Kamchatsky - Old' => 'Asia/Kamchatka',
            '(UTC+13:00) Nuku\'alofa' => 'Pacific/Tongatapu',
            '(UTC+13:00) Samoa' => 'Pacific/Apia',
        ][$label] ?? config('app.timezone');
    }
}
