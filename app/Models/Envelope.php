<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * status values
 * 0 or null: unprocessed
 * 1: partial processed: some of signer not signed yet
 * 2: fully processed
 */
class Envelope extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'envelope_name',
        'status',
        'password',
        'plain_password',
        'expiry_date_time',
    ];

    protected $casts = [
        'expiry_date_time' => 'datetime',
    ];

    // protected $hidden = [
    //     'plain_password'
    // ];

    public function documents(): HasMany
    {
        return $this->hasMany(EnvelopeDocument::class);
    }

    public function scopeWithNotFullyProcessed($query)
    {
        return $query->whereIn('status', ['0', '1'])->orWhere('status', null);
    }

    public function unprocessedDocuments()
    {
        return $this->documents()->where('status', 0);
    }

    public function processedAndEsignedDocuments()
    {
        return $this->documents()->where('status', 1);
    }

    public function fullyProcessedDocuments()
    {
        return $this->documents()->where('status', 2);
    }

    public function notPostHiringDocuments()
    {
        return $this->documents()->where('is_post_hiring_document', 0)->orderBy('id', 'desc')->with('active_document');
    }

    public function postHiringDocuments()
    {
        return $this->documents()->where('is_post_hiring_document', 1);
    }

    public function getEnvelopeExpiryStatusAttribute()
    {

        return now()->greaterThan($this->expiry_date_time);
    }
}
