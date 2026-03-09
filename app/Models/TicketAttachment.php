<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TicketAttachment extends Model
{
    use HasFactory;

    const IS_JIRA_SYNCED = '1'; // Synced

    const IS_JIRA_NOT_SYNCED = '0'; // Not Synced

    protected $table = 'ticket_attachments';

    protected $fillable = [
        'attachment_type',
        'attachment_id',
        'original_file_name',
        'system_file_name',
        'mime_type',
        'size',
        'jira_id',
        'jira_synced',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function attachment(): MorphTo
    {
        return $this->morphTo();
    }
}
