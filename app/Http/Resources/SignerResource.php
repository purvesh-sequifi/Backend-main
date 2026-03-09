<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SignerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray(Request $request): array
    {
        return [
            'signer_id' => $this->id,
            'is_expired' => $this->envelope_document->envelope->envelope_expiry_status,
            'envelope_id' => $this->envelope_document->envelope->id,
            'envelope_document_id' => isset($this->envelope_document->active_document->id) ? $this->envelope_document->active_document->id : '',
            'signer_email' => $this->signer_email,
            'signer_name' => $this->signer_name,
            'signer_role' => $this->signer_role,
            'password' => $this->signer_plain_password,
        ];
    }
}
