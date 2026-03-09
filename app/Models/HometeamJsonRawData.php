<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HometeamJsonRawData extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'hometeam_json_raw_data';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'pid',
        'customer_name',
        'customer_address',
        'customer_city',
        'customer_state',
        'customer_zip',
        'customer_email',
        'customer_phone',
        'sales_rep_name',
        'sales_rep_email',
        'install_partner',
        'customer_signoff',
        'm1_date',
        'source_created_at',
        'source_updated_at',
        'date_cancelled',
        'last_service_date',
        'product',
        'product_id',
        'gross_account_value',
        'service_schedule',
        'initial_service_cost',
        'trigger_date',
        'service_completed',
        'length_of_agreement',
        'auto_pay',
        'job_status',
        'bill_status',
        'subscription_payment',
        'adders_description',
        'last_updated_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'm1_date' => 'date',
        'source_created_at' => 'datetime',
        'source_updated_at' => 'datetime',
        'date_cancelled' => 'date',
        'last_service_date' => 'date',
        'gross_account_value' => 'float',
        'initial_service_cost' => 'float',
        'service_completed' => 'boolean',
        'length_of_agreement' => 'integer',
        'auto_pay' => 'boolean',
        'subscription_payment' => 'float',
        'last_updated_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get sanitized data - helper method in alignment with the project's
     * existing input sanitization patterns for JSON data.
     *
     * @param  mixed  $value  The value to sanitize
     * @param  string  $type  The type of data to sanitize (email, phone, numeric, text)
     * @return mixed
     */
    public static function getSanitizedValue($value, string $type = 'text')
    {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case 'email':
                // Sanitize email
                $value = filter_var($value, FILTER_SANITIZE_EMAIL);
                break;
            case 'phone':
                // Sanitize phone - keep only digits
                $value = preg_replace('/[^0-9]/', '', $value);
                break;
            case 'numeric':
                // Sanitize numeric values
                $value = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                break;
            default:
                // Default text sanitization
                $value = filter_var($value, FILTER_SANITIZE_STRING);
                break;
        }

        return $value;
    }

    /**
     * Create a new record with sanitized data
     *
     * @param  array  $data  Raw data to be sanitized and saved
     */
    public static function createSanitized(array $data): HometeamJsonRawData
    {
        $sanitizedData = [
            'pid' => self::getSanitizedValue($data['pid'] ?? null),
            'customer_name' => self::getSanitizedValue($data['customer_name'] ?? null),
            'customer_address' => self::getSanitizedValue($data['customer_address'] ?? null),
            'customer_city' => self::getSanitizedValue($data['customer_city'] ?? null),
            'customer_state' => self::getSanitizedValue($data['customer_state'] ?? null),
            'customer_zip' => self::getSanitizedValue($data['customer_zip'] ?? null),
            'customer_email' => self::getSanitizedValue($data['customer_email'] ?? null, 'email'),
            'customer_phone' => self::getSanitizedValue($data['customer_phone'] ?? null, 'phone'),
            'sales_rep_name' => self::getSanitizedValue($data['sales_rep_name'] ?? null),
            'sales_rep_email' => self::getSanitizedValue($data['sales_rep_email'] ?? null, 'email'),
            'install_partner' => self::getSanitizedValue($data['install_partner'] ?? null),
            'customer_signoff' => self::getSanitizedValue($data['customer_signoff'] ?? null),
            'm1_date' => $data['m1_date'] ?? null,
            'source_created_at' => $data['source_created_at'] ?? null,
            'source_updated_at' => $data['source_updated_at'] ?? null,
            'date_cancelled' => $data['date_cancelled'] ?? null,
            'last_service_date' => $data['last_service_date'] ?? null,
            'product' => self::getSanitizedValue($data['product'] ?? null),
            'product_id' => self::getSanitizedValue($data['product_id'] ?? null),
            'gross_account_value' => self::getSanitizedValue($data['gross_account_value'] ?? null, 'numeric'),
            'service_schedule' => self::getSanitizedValue($data['service_schedule'] ?? null),
            'initial_service_cost' => self::getSanitizedValue($data['initial_service_cost'] ?? null, 'numeric'),
            'service_completed' => $data['service_completed'] ?? null,
            'length_of_agreement' => $data['length_of_agreement'] ?? null,
            'auto_pay' => $data['auto_pay'] ?? null,
            'job_status' => self::getSanitizedValue($data['job_status'] ?? null),
            'bill_status' => self::getSanitizedValue($data['bill_status'] ?? null),
            'subscription_payment' => self::getSanitizedValue($data['subscription_payment'] ?? null, 'numeric'),
            'adders_description' => self::getSanitizedValue($data['adders_description'] ?? null),
            'last_updated_date' => $data['last_updated_date'] ?? null,
        ];

        return self::create($sanitizedData);
    }

    /**
     * Batch insert sanitized records with optimized processing
     *
     * @param  array  $records  Array of record data to be sanitized and saved
     * @param  int  $chunkSize  Size of batch chunks for optimal DB performance
     * @return int Number of records created
     */
    public static function batchCreateSanitized(array $records, int $chunkSize = 100): int
    {
        $sanitizedRecords = [];

        foreach ($records as $record) {
            $sanitizedRecords[] = [
                'pid' => self::getSanitizedValue($record['pid'] ?? null),
                'customer_name' => self::getSanitizedValue($record['customer_name'] ?? null),
                'customer_address' => self::getSanitizedValue($record['customer_address'] ?? null),
                'customer_city' => self::getSanitizedValue($record['customer_city'] ?? null),
                'customer_state' => self::getSanitizedValue($record['customer_state'] ?? null),
                'customer_zip' => self::getSanitizedValue($record['customer_zip'] ?? null),
                'customer_email' => self::getSanitizedValue($record['customer_email'] ?? null, 'email'),
                'customer_phone' => self::getSanitizedValue($record['customer_phone'] ?? null, 'phone'),
                'sales_rep_name' => self::getSanitizedValue($record['sales_rep_name'] ?? null),
                'sales_rep_email' => self::getSanitizedValue($record['sales_rep_email'] ?? null, 'email'),
                'install_partner' => self::getSanitizedValue($record['install_partner'] ?? null),
                'customer_signoff' => self::getSanitizedValue($record['customer_signoff'] ?? null),
                'm1_date' => $record['m1_date'] ?? null,
                'source_created_at' => $record['source_created_at'] ?? null,
                'source_updated_at' => $record['source_updated_at'] ?? null,
                'date_cancelled' => $record['date_cancelled'] ?? null,
                'last_service_date' => $record['last_service_date'] ?? null,
                'product' => self::getSanitizedValue($record['product'] ?? null),
                'product_id' => self::getSanitizedValue($record['product_id'] ?? null),
                'gross_account_value' => self::getSanitizedValue($record['gross_account_value'] ?? null, 'numeric'),
                'service_schedule' => self::getSanitizedValue($record['service_schedule'] ?? null),
                'initial_service_cost' => self::getSanitizedValue($record['initial_service_cost'] ?? null, 'numeric'),
                'service_completed' => $record['service_completed'] ?? null,
                'length_of_agreement' => $record['length_of_agreement'] ?? null,
                'auto_pay' => $record['auto_pay'] ?? null,
                'job_status' => self::getSanitizedValue($record['job_status'] ?? null),
                'bill_status' => self::getSanitizedValue($record['bill_status'] ?? null),
                'subscription_payment' => self::getSanitizedValue($record['subscription_payment'] ?? null, 'numeric'),
                'adders_description' => self::getSanitizedValue($record['adders_description'] ?? null),
                'last_updated_date' => $record['last_updated_date'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert in chunks for better performance
        $totalInserted = 0;
        foreach (array_chunk($sanitizedRecords, $chunkSize) as $chunk) {
            $inserted = self::insert($chunk);
            if ($inserted) {
                $totalInserted += count($chunk);
            }
        }

        return $totalInserted;
    }
}
