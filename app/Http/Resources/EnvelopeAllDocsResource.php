<?php

namespace App\Http\Resources;

use App\Models\OnboardingEmployees;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnvelopeAllDocsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray(Request $request): array
    {
        $documentData = [];
        $documentSignersData = [];
        $activeDocOfferExpiryDate = '';
        foreach ($this->notPostHiringDocuments as $document) {
            $activeDocument = $document->active_document;
            if (! empty($activeDocument) && $activeDocument->is_active == 1) {
                $documentData[] = [
                    'document_id' => $document->id,
                    'is_mandatory' => $document->is_mandatory,
                    'status' => $document->status,
                    'initial_pdf_path' => $document->initial_pdf_path,
                    'processed_pdf_path' => $document->processed_pdf_path,
                    'pdf_file_other_parameter' => $document->pdf_file_other_parameter,
                    'is_sign_required_for_hire' => $document->is_sign_required_for_hire,
                    'template_name' => $document->template_name,
                    'is_post_hiring_document' => $document->is_post_hiring_document,
                    'template_category_id' => $document->template_category_id,
                    'template_category_name' => $document->template_category_name,
                    'template_category_type' => $document->template_category_type,
                    'is_pdf' => $document->is_pdf,
                    'expiry_date_time' => $document->document_expiry,
                ];

                foreach ($document->document_signers as $signer) {
                    $onboardingEmployee = OnboardingEmployees::where('email', $signer->signer_email)->first();
                    if (isset($onboardingEmployee->offer_expiry_date)) {
                        $activeDocOfferExpiryDate = $onboardingEmployee->offer_expiry_date;
                    } else {
                        $activeDocOfferExpiryDate = '';
                    }

                    $documentSignersData[] = [
                        'signer_id' => $signer->id,
                        'document_id' => $signer->envelope_document_id,
                        'consent' => $signer->consent,
                        'signer_email' => $signer->signer_email,
                        'signer_name' => $signer->signer_name,
                        'signer_role' => $signer->signer_role,
                    ];
                }
            }
        }

        if ($activeDocOfferExpiryDate) {
            return [
                'id' => $this->id,
                'envelope_name' => $this->envelope_name,
                'status' => $this->status,
                'expiry_date_time' => $activeDocOfferExpiryDate,
                'is_expired' => $this->envelope_expiry_status,
                'documents' => $documentData,
                'document_signers' => $documentSignersData,
            ];
        } else {
            return [
                'id' => $this->id,
                'envelope_name' => $this->envelope_name,
                'status' => $this->status,
                'expiry_date_time' => isset($this->expiry_date_time) ? $this->expiry_date_time->format('Y-m-d') : null,
                'is_expired' => $this->envelope_expiry_status,
                'documents' => $documentData,
                'document_signers' => $documentSignersData,
            ];
        }
    }
}
