<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadCommentReply extends Model
{
    use HasFactory;

    protected $table = 'lead_comment_replies';

    protected $fillable = [
        'comment_id',
        'comment_reply',
    ];
}
