<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcessedTicketCount extends Model
{
    use HasFactory;

    protected $table = 'processed_ticket_counts';

    protected $fillable = [
        'ticketIDs',
        'count',
        'start_date',
        'end_date',
        'processed_date',
        'status',
        'integration_id',
    ];
}
