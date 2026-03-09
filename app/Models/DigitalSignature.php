<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class DigitalSignature
 *
 * SignServer Digital Signature
 * Possible values for digi_sig_status are
 * 0 = not signed
 * 1 = signed
 */
class DigitalSignature extends Model
{
    use HasFactory, SoftDeletes;
}
