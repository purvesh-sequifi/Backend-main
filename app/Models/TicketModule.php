<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'jira_id',
        'jira_key',
        'jira_summary',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
