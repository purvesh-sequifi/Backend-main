<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserProfileHistory extends Model
{
    use HasFactory;

    protected $table = 'user_profile_history';

    protected $fillable = [
        'user_id',
        'batch_no',
        'updated_by',
        'field_name',
        'old_value',
        'new_value',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        // 'created_at',
        // 'updated_at',
    ];

    public function updater(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'updated_by')->select('id', 'first_name', 'last_name', 'redline', 'image', 'position_id');
    }
}
