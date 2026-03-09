<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketFaq extends Model
{
    use HasFactory;

    protected $table = 'ticket_faqs';

    protected $fillable = [
        'faq_category_id',
        'question',
        'answer',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function faqCategory(): BelongsTo
    {
        return $this->belongsTo(TicketFaqCategory::class, 'id', 'faq_category_id');
    }
}
