<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxDocumentCheck extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'file_name', 'tax_year', 'document_type', 'sent_at'];
}
