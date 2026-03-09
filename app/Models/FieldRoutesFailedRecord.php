<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldRoutesFailedRecord extends Model
{
    protected $fillable = [
        'subscription_id',
        'customer_id',
        'raw_data',
        'failure_reason',
        'failure_description',
        'failure_type',
        'failed_at',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'failed_at' => 'datetime',
    ];

    /**
     * Failure type constants
     */
    const FAILURE_TYPE_VALIDATION = 'validation';

    const FAILURE_TYPE_TRANSFORMATION = 'transformation';

    const FAILURE_TYPE_BUSINESS_RULE = 'business_rule';

    const FAILURE_TYPE_MISSING_DATA = 'missing_data';

    const FAILURE_TYPE_INVALID_DATA = 'invalid_data';
}
