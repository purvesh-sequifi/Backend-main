<?php

namespace App\Http\Controllers\API\V2\SequiDocs;

use App\Services\SequiDocs\DocumentPreparationService;
use Illuminate\Http\Request;

class SequiDocsDocumentPreparationController extends BaseController
{
    protected $documentPreparationService;

    public function __construct(DocumentPreparationService $documentPreparationService)
    {
        $this->documentPreparationService = $documentPreparationService;
    }

    /**
     * API 1: Document Preparation
     * POST /api/v2/sequidocs/prepare-document
     *
     * Handles validation, signature processing, and PDF generation
     * Preserves all Phase 1 optimizations (batch inserts, N+1 query fixes)
     */
    public function prepareDocument(Request $request)
    {
        $this->checkValidations($request->all(), [
            'form_data_attributes' => 'array',
            'signature_attributes' => 'array',
            'document_id' => 'required|exists:envelope_documents,id,deleted_at,NULL',
            'document_signer_id' => 'required|exists:document_signers,id,deleted_at,NULL',
        ]);

        try {
            $result = $this->documentPreparationService->prepareDocument($request->all());

            if (! $result['status']) {
                return $this->errorJsonResponse($result['message'], 'prepare-document',
                    $result['error_details'] ?? []);
            }

            return $this->successJsonResponse('Document prepared successfully', 'prepare-document', $result['data']);

        } catch (\Throwable $e) {
            return $this->errorJsonResponse($e->getMessage(), 'prepare-document', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
