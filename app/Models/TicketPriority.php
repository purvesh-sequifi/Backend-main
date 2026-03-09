<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketPriority extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
        'status', // 0 = Disabled, 1 = Enabled
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function scopeIsEnabled($query)
    {
        return $query->where('status', '1');
    }
}
