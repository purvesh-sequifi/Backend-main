<?php

namespace App\Http\Controllers\API\V2\SequiDocs;

use App\Models\NewSequiDocsUploadDocumentType;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SequiDocsDocumentTypeV2Controller extends BaseController
{
    /**
     * CREATE NEW SEQUI DOCS DOCUMENT TYPE
     */
    public function createSequiDocsDocumentType(Request $request): JsonResponse
    {
        // VALIDATE REQUEST
        $this->checkValidations($request->all(), [
            'document_name' => 'required|string',
        ]);

        try {
            // GET DOCUMENT NAME
            $documentName = ucwords($request->document_name);

            // CHECK IF DOCUMENT TYPE ALREADY EXISTS
            $existingDocType = NewSequiDocsUploadDocumentType::where('is_deleted', '!=', '1')
                ->where('document_name', $documentName)
                ->first();

            if ($existingDocType) {
                // DOCUMENT TYPE ALREADY EXISTS
                return $this->successJsonResponse(
                    "$documentName Document type already exists",
                    'create-sequi-docs-document-type',
                    $existingDocType,
                    200
                );
            }

            // CREATE NEW DOCUMENT TYPE
            $newDocType = new NewSequiDocsUploadDocumentType;
            $newDocType->document_name = $documentName;

            if ($newDocType->save()) {
                // DOCUMENT TYPE SAVED SUCCESSFULLY
                return $this->successJsonResponse(
                    "$documentName Document type is saved",
                    'create-sequi-docs-document-type',
                    $newDocType,
                    200
                );
            }

            // FAILED TO SAVE DOCUMENT TYPE
            return $this->errorJsonResponse(
                'Failed to save document type',
                'create-sequi-docs-document-type',
                [],
                400
            );
        } catch (Exception $error) {
            // ERROR RESPONSE
            return $this->errorJsonResponse(
                $error->getMessage(),
                'create-sequi-docs-document-type',
                [],
                400
            );
        }
    }
}
