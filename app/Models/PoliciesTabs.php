<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PoliciesTabs extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'policies_tabs';

    protected $fillable = [
        'policies_id',
        'tabs',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function permission(): HasMany
    {
        return $this->hasMany(\App\Models\Permissions::class, 'policies_tabs_id', 'id');
    }
}
