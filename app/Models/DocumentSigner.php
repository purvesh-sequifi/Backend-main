<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentSigner extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'envelope_document_id',
        'consent',
        'signer_type',
        'signer_sequence',
        'signer_email',
        'signer_name',
        'signer_role',
        'signer_plain_password',
    ];

    public function visible_signatures_and_form_data_attributes(): HasMany
    {
        return $this->hasMany(VisibleSignature::class);
    }

    public function envelope_document(): BelongsTo
    {
        return $this->belongsTo(EnvelopeDocument::class);
    }
}
