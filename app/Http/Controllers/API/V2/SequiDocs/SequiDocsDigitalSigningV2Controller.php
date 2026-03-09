<?php

namespace App\Http\Controllers\API\V2\SequiDocs;

use App\Services\SequiDocs\DigitalSigningService;
use App\Services\SequiDocs\DocumentCompletionService;
use Illuminate\Http\Request;

class SequiDocsDigitalSigningV2Controller extends BaseController
{
    protected $digitalSigningService;

    protected $documentCompletionService;

    public function __construct(
        DigitalSigningService $digitalSigningService,
        DocumentCompletionService $documentCompletionService
    ) {
        $this->digitalSigningService = $digitalSigningService;
        $this->documentCompletionService = $documentCompletionService;
    }

    /**
     * API 2: Digital Signing & Storage
     * POST /api/v2/sequidocs/sign-and-store
     *
     * Handles digital signing, S3 upload, and triggers completion service
     */
    public function signAndStore(Request $request)
    {
        $this->checkValidations($request->all(), [
            'processed_pdf_path' => 'required|string',
            'document_name' => 'required|string',
            'envelope_document_id' => 'required|integer',
        ]);

        try {
            // Step 1: Digital Signing & Storage
            $signingResult = $this->digitalSigningService->signAndStore($request->all());

            if (! $signingResult['status']) {
                return $this->errorJsonResponse($signingResult['message'], 'sign-and-store',
                    $signingResult['error_details'] ?? []);
            }

            // Step 2: Automatically trigger Document Completion Service
            $completionData = array_merge($request->all(), $signingResult['data']);
            $completionResult = $this->documentCompletionService->complete($completionData);

            if (! $completionResult['status']) {
                return $this->errorJsonResponse($completionResult['message'], 'sign-and-store',
                    $completionResult['error_details'] ?? []);
            }

            return $this->successJsonResponse('Document signed, stored, and completed successfully', 'sign-and-store', [
                'signing_result' => $signingResult['data'],
                'completion_result' => $completionResult['data'],
            ]);

        } catch (\Throwable $e) {
            return $this->errorJsonResponse($e->getMessage(), 'sign-and-store', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
