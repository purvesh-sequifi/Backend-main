<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketFaqCategory extends Model
{
    use HasFactory;

    protected $table = 'ticket_faq_categories';

    protected $fillable = [
        'name',
        'order',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function faqs(): HasMany
    {
        return $this->hasMany(TicketFaq::class, 'faq_category_id', 'id');
    }
}
