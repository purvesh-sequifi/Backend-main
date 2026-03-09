<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionHirePermission extends Model
{
    use HasFactory;

    protected $table = 'position_hire_permissions';

    protected $fillable = [
        'position_id',
        'granted_by',
    ];

    // Relationships
    public function position(): BelongsTo
    {
        return $this->belongsTo(Positions::class, 'position_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
