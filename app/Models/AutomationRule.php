<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutomationRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'automation_rules';

    // Define constants for status values
    const STATUS_INACTIVE = 0;

    const STATUS_ACTIVE = 1;

    public const CATEGORY_LEAD = 'Lead';

    public const CATEGORY_ONBOARDING = 'Onboarding';

    public const AUTOMATION_EVENTS = [
        self::CATEGORY_LEAD => [
            'Lead Moves',
            'Lead Stays',
            'Subtask Status',
        ],
        self::CATEGORY_ONBOARDING => [
            'Candidate Moves',
            'Candidate Stays',
            // 'Subtask Status',
        ],
        // ....
        // ....
    ];

    public const AUTOMATION_ACTIONS = [
        self::CATEGORY_LEAD => [
            'Email Lead',
            'Email Recruiter',
            'Email Reporting Manager',
            'Email[custom email]',
            'Highlight Lead',
        ],
        self::CATEGORY_ONBOARDING => [
            'Email Candidate',
            'Email Recruiter',
            'Email Reporting Manager',
            'Email[custom email]',
        ],
        // ....
        // ....
    ];

    protected $fillable = [
        'automation_title',
        'category',
        'rule',
        'status',
        'user_id',
    ];

    protected $casts = [
        'rule' => 'json',
        // 'rule' => 'array',
    ];

    // Get all possible categories
    public static function getCategories()
    {
        return [
            self::CATEGORY_LEAD,
            self::CATEGORY_ONBOARDING,
        ];
    }

    public static function getStatuses()
    {
        return [
            self::STATUS_INACTIVE,
            self::STATUS_ACTIVE,
        ];
    }

    public static function getEvents($category = '')
    {
        if ($category && isset(self::AUTOMATION_EVENTS[$category])) {
            return [$category => self::AUTOMATION_EVENTS[$category]];
        }

        // return self::AUTOMATION_EVENTS;
        return [];
    }

    public static function getEventActions($category = '')
    {
        if ($category && isset(self::AUTOMATION_ACTIONS[$category])) {
            return [$category => self::AUTOMATION_ACTIONS[$category]];
        }

        // return self::AUTOMATION_EVENTS;
        return [];
    }

    // public function getStatusAttribute()
    // {
    //     return $this->status == self::STATUS_ACTIVE ? 'Active' : 'Inactive';
    // }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
