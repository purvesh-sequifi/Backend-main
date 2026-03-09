<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class VisibleSignature
 *
 * SignServer Digital Signature Request
 * Possible values for digi_sig_request_status are
 * 0 = pending
 * 1 = done
 */
class VisibleSignature extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // Other fillable fields here
        'document_signer_id',
        'signature_attributes',
        'form_data_attributes',
        'document_id',
    ];

    protected $casts = [
        'signature_attributes' => 'json',
        'form_data_attributes' => 'json',
    ];
}
