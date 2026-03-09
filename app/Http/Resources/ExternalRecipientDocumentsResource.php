<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExternalRecipientDocumentsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray(Request $request): array
    {
        $attachments = [];
        if ($this->category_id) {
            if ($this->signed_document) {
                array_push($attachments, $this->signed_document);
            }
        }

        if (! $this->category_id) {
            if ($this->upload_document_file) {
                $attachments = $this->upload_document_file;
            }
        }

        return [
            'id' => $this->id,
            'type' => $this->description,
            'date_sent' => $this->document_send_date,
            'attachments' => $attachments,
            'date_signed' => $this->signed_date,
            'required' => $this->is_sign_required_for_hire,
        ];
    }
}
