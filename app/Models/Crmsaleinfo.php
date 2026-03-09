<?php

namespace App\Models;

// use AWS\CRT\Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Crmsaleinfo extends Model
{
    use HasFactory;

    protected $table = 'crm_sale_info';

    // public $search;

    protected $fillable = [
        'id',
        'pid',
        'custom_fields',
        'created_id',
        'custom_field_values', // Custom Sales Field feature - stores custom field values per sale
    ];

    protected $casts = [
        'custom_field_values' => 'array', // Custom Sales Field feature
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function bucketbyjob(): HasOne
    {
        // return $this->hasOne('App\Models\Bucketbyjob','job_id', 'id');
        return $this->hasOne(\App\Models\Bucketbyjob::class, 'job_id', 'id')->select('*')->where('active', 1)->with('bucketbyjobbucket');
    }

    public function BucketSubTaskByJob(): HasMany
    {
        return $this->hasMany(\App\Models\BucketSubTaskByJob::class, 'job_id', 'id');
    }

    public function sales(): HasOne
    {
        return $this->hasOne(\App\Models\SalesMaster::class, 'pid', 'pid')->with('salesMasterProcess', 'userDetail');
    }

    public function reportsales(): HasOne
    {
        return $this->hasOne(\App\Models\SalesMaster::class, 'pid', 'pid')->with('salesMasterProcess', 'userDetail', 'override.user');
    }

    public function bucketbyjoballl(): HasOne
    {
        // return $this->hasOne('App\Models\Bucketbyjob','job_id', 'id');
        return $this->hasMany(\App\Models\Bucketbyjob::class, 'job_id', 'id')->with('bucketinfo');
    }

    public function bucketlist(): HasOne
    {
        // return $this->hasOne('App\Models\Bucketbyjob','job_id', 'id');
        return $this->hasMany(\App\Models\Buckets::class);
    }

    public function bucketbycomments(): HasMany
    {
        return $this->hasMany(\App\Models\CrmComments::class, 'job_id', 'id');
    }

    public function bucketbydocuments(): HasMany
    {
        return $this->hasMany(\App\Models\Crmattachments::class, 'job_id', 'id');
    }

    public function jobbycomments(): HasMany
    {
        return $this->hasMany(\App\Models\CrmComments::class, 'job_id', 'id');
    }

    public function jobbydocuments(): HasMany
    {
        return $this->hasMany(\App\Models\Crmattachments::class, 'job_id', 'id');
    }

    public function bucketbyjobbucket(): HasMany
    {
        return $this->hasMany('App\Models\jobucketbybucket', 'job_id', 'id')->with('jobucketbybucket');
    }

    public function allbuckets(): HasMany
    {
        // return $this->hasMany('App\Models\buckets');
    }

    public static function getdaytime($created_at)
    {
        $pastDate = Carbon::parse($created_at);
        $currentTime = Carbon::now();
        $timeDifference = $pastDate->diff($currentTime);
        $days = $timeDifference->d;
        $hours = $timeDifference->h;
        $minutes = $timeDifference->i;
        $seconds = $timeDifference->s;

        $dateTime = '';
        if ($days > 0) {
            $str = ' days';
            if ($days == 1) {
                $str = ' day';
            }
            $dateTime = $days.$str;
        } elseif ($hours > 0) {
            $str = ' hours';
            if ($hours == 1) {
                $str = ' hour';
            }
            $dateTime = $hours.$str;
        } elseif ($minutes > 0) {
            $str = ' minutes';
            if ($minutes == 1) {
                $str = ' minute';
            }
            $dateTime = $minutes.$str;
        } elseif ($seconds > 0) {
            $str = ' seconds';
            if ($seconds == 1) {
                $str = ' second';
            }
            $dateTime = $seconds.$str;
        }

        return $dateTime;
    }

    public static function getcusomefields($pid)
    {
        $job_custom_infos = json_decode(Crmsaleinfo::select('custom_fields')->where('pid', $pid)->get()->value('custom_fields'));
        $jobcustominfo = [];

        if (! empty($job_custom_infos)) {
            foreach ($job_custom_infos as $job_custom_info) {
                $jobcustominfo[$job_custom_info->custom_fild_id] = $job_custom_info->value ? $job_custom_info->value : '';
            }
        }

        $custom_filds = Crmcustomfields::get();
        $customfilds = [];
        foreach ($custom_filds as $custom_fild) {
            if (array_key_exists($custom_fild->id, $jobcustominfo)) {
                $custom_fild->value = $jobcustominfo[$custom_fild->id];
            }
            $customfilds[] = $custom_fild;
        }

        return $customfilds;

    }

    public static function checksalejob($pid)
    {
        $jobcheck = Crmsaleinfo::where('pid', $pid)->first();
        if (! empty($jobcheck)) {
            return $jobcheck;
        } else {
            return false;
        }
    }
}
