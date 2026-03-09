<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnvelopeDocResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray(Request $request): array
    {
        $documentSignersData = [];
        foreach ($this->document_signers as $signer) {
            $documentSignersData[] = [
                'signer_id' => $signer->id,
                'document_id' => $signer->envelope_document_id,
                'consent' => $signer->consent,
                'signer_email' => $signer->signer_email,
                'signer_name' => $signer->signer_name,
                'signer_role' => $signer->signer_role,
            ];
        }

        // $sign_status = 0 for not signed
        // $sign_status = 1 for signed
        $signStatus = 0;
        if ($this->status == 1 || $this->status == 2) {
            $signStatus = 1;
        }

        return [
            'document_id' => $this->id,
            'envelope_id' => $this->envelope_id,
            'is_mandatory' => $this->is_mandatory,
            'status' => $this->status,
            'sign_status' => $signStatus,
            'initial_pdf_path' => $this->initial_pdf_path,
            'processed_pdf_path' => $this->processed_pdf_path,
            'is_pdf' => $this->is_pdf,
            'pdf_file_other_parameter' => $this->pdf_file_other_parameter,
            'is_sign_required_for_hire' => $this->is_sign_required_for_hire,
            'template_name' => $this->template_name,
            'is_post_hiring_document' => $this->is_post_hiring_document,
            'template_category_id' => $this->template_category_id,
            'template_category_name' => $this->template_category_name,
            'template_category_type' => $this->template_category_type,
            'document_expiry' => $this->document_expiry,
            'signers' => $documentSignersData,
        ];
    }
}
