<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Status Values: //status column value
 * 0: unprocessed
 * 1: esigned and form data merged, partial processed
 * 2: esigned, form data merged and digi signed, fully processed
 * 3: rejected
 *
 * is_pdf values
 * 0: template is based html to PDF
 * 1: template is based  on uploaded pdf
 */

/**
 * Table: envelope_documents
 * Cols:
 * 'id'
 * 'envelope_id'
 * 'is_mandatory'
 * 'upload_by_user'
 * 'status'
 * 'pdf_storage_type'
 * 'initial_pdf_path'
 * 'processed_pdf_path'
 * 'is_pdf'
 * 'pdf_file_other_parameter'
 * 'is_sign_required_for_hire'
 * 'template_name'
 * 'is_post_hiring_document'
 * 'pdf_pages_as_image'
 * 'template_category_id'
 * 'template_category_name'
 * 'deleted_at'
 * 'created_at'
 * 'updated_at'
 * 'template_category_type'
 * 'document_expiry'
 */
class EnvelopeDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'envelope_id',
        'is_mandatory',
        'upload_by_user',
        'status',
        'pdf_storage_type',
        'initial_pdf_path',
        'processed_pdf_path',
        'is_pdf',
        'pdf_file_other_parameter',
        'is_sign_required_for_hire',
        'template_name',
        'is_post_hiring_document',
        'pdf_pages_as_image',
        'template_category_id',
        'template_category_name',
        'template_category_type',
        'document_expiry',
    ];

    protected $casts = [
        'pdf_file_other_parameter' => 'json',
        'pdf_pages_as_image' => 'json',
    ];

    public function envelope(): BelongsTo
    {
        return $this->belongsTo(Envelope::class);
    }

    public function document_signers(): HasMany
    {
        return $this->hasMany(DocumentSigner::class);
    }

    public function document_signer(): HasOne
    {
        return $this->hasOne(DocumentSigner::class);
    }

    public function active_document(): HasOne
    {
        return $this->hasOne(NewSequiDocsDocument::class, 'signature_request_document_id', 'id');
    }

    public function getInitialPdfPathAttribute($value)
    {

        if (strpos($value, config('app.aws_s3bucket_old_url')) !== false) {
            // Replace the domain with the new domain
            // dd('is here');
            $newDomain = config('app.aws_s3bucket_url');

            return str_replace(config('app.aws_s3bucket_old_url'), $newDomain, $value);
        } else {
            return $value; // No replacement needed
        }

    }

    public function setInitialPdfPathAttribute($value)
    {
        if (! empty($value) && strpos($value, config('app.aws_s3bucket_old_url')) !== false) {
            // Replace the domain with the new domain
            $value = str_replace(config('app.aws_s3bucket_old_url'), config('app.aws_s3bucket_url'), $value);
        }
        $this->attributes['initial_pdf_path'] = $value;
    }
}
