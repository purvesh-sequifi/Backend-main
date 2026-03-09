<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class leadComment extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'lead_comments';

    protected $fillable = [
        'user_id',
        'lead_id',
        'comments',
        'status',
        'path',
        'pipeline_lead_status_id',
    ];

    protected $hidden = [
        // 'created_at',
        'updated_at',
    ];

    public function usersdata(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id');
        // ->select('id','first_name','last_name','email','image')
    }

    public function commentreaply(): HasMany
    {
        return $this->hasMany(LeadCommentReply::class, 'comment_id', 'id')->select('comment_id', 'comment_reply');
    }
}
