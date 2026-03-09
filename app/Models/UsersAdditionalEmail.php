<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UsersAdditionalEmail extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'users_additional_emails';

    protected $fillable = [
        'user_id',
        'email',
    ];

    protected $hidden = [
        'created_at',
    ];

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}
