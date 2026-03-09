<?php

namespace App\Services\SequiDocs;

use App\Models\EnvelopeDocument;
use App\Models\NewSequiDocsDocument;
use App\Models\NewSequiDocsSignatureRequestLog;
use App\Models\VisibleSignature;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentPreparationService
{
    /**
     * Handle document preparation: validation, signature processing, and PDF generation
     */
    public function prepareDocument(array $requestData): array
    {
        try {
            $documentId = data_get($requestData, 'document_id');
            $documentSignerId = data_get($requestData, 'document_signer_id');
            $formDataAttributes = data_get($requestData, 'form_data_attributes', []);
            $signatureAttributes = data_get($requestData, 'signature_attributes', []);

            // Get and validate documents
            $documents = $this->getAndValidateDocuments($documentId);
            if (! data_get($documents, 'status')) {
                return $documents;
            }

            $envelopeDocument = data_get($documents, 'envelope_document');
            $document = data_get($documents, 'sequi_document');

            // Update signing attempt timestamp
            $document->signing_attemp_at = now();
            $document->save();

            // Process signatures and form data
            $processedData = $this->processSignaturesAndFormData(
                $formDataAttributes,
                $signatureAttributes,
                $documentId,
                $documentSignerId,
                $envelopeDocument
            );

            if (! data_get($processedData, 'status')) {
                return $processedData;
            }

            // Process PDF with Python script
            $pdfResult = $this->processPdfWithScript(
                $envelopeDocument,
                data_get($processedData, 'form_data'),
                data_get($processedData, 'image_data'),
                $documentId
            );

            if (! data_get($pdfResult, 'status')) {
                return $pdfResult;
            }

            return [
                'status' => true,
                'message' => 'Document prepared successfully',
                'data' => [
                    'processed_pdf_path' => data_get($pdfResult, 'pdf_output_path'),
                    'document_name' => data_get($pdfResult, 'document_name'),
                    'document_id' => $document->id,
                    'envelope_document_id' => $envelopeDocument->id,
                    'template_name' => $envelopeDocument->template_name,
                    'user_id' => $document->user_id,
                ],
            ];

        } catch (\Throwable $e) {
            return [
                'status' => false,
                'message' => 'Document preparation failed: '.$e->getMessage(),
                'error_details' => [
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                    'trace' => $e->getTraceAsString(),
                ],
            ];
        }
    }

    /**
     * Get and validate required documents
     */
    private function getAndValidateDocuments(int $documentId): array
    {
        // Optimized query with specific field selection and eager loading (Phase 1 Optimization)
        $envelopeDocument = EnvelopeDocument::with([
            'envelope:id,envelope_name',
        ])->select([
            'id', 'initial_pdf_path', 'is_pdf', 'template_name',
            'envelope_id', 'status', 'processed_pdf_path', 'deleted_at',
        ])->find($documentId);

        if (! $envelopeDocument) {
            return [
                'status' => false,
                'message' => 'Invalid document ID',
            ];
        }

        $document = NewSequiDocsDocument::where('signature_request_document_id', $envelopeDocument->id)->first();
        if (! $document) {
            return [
                'status' => false,
                'message' => 'Invalid document ID',
            ];
        }

        return [
            'status' => true,
            'envelope_document' => $envelopeDocument,
            'sequi_document' => $document,
        ];
    }

    /**
     * Process signatures and form data - matches original API logic
     */
    private function processSignaturesAndFormData(
        array $formDataAttributes,
        array $signatureAttributes,
        int $documentId,
        int $documentSignerId,
        $envelopeDocument
    ): array {
        try {
            $formData = [];
            $imageData = [];
            $pdfScale = 1.3; // Add missing PDF scale variable for coordinate transformation

            // Delete existing VisibleSignatures for this document and signer first
            VisibleSignature::where(['document_signer_id' => $documentSignerId, 'document_id' => $documentId])->delete();

            // Process form data attributes (if present)
            if (! empty($formDataAttributes)) {
                foreach ($formDataAttributes as $formDataAttribute) {
                    VisibleSignature::create([
                        'document_id' => $documentId,
                        'document_signer_id' => $documentSignerId,
                        'form_data_attributes' => $formDataAttribute,
                    ]);

                    $x = (int) data_get($formDataAttribute, 'x', 0);
                    $y = (int) data_get($formDataAttribute, 'y', 0);

                    if (data_get($formDataAttribute, 'type') == 'signature') {
                        // Apply PDF coordinate scaling for signature form data (matches original API lines 278-284)
                        if ($envelopeDocument->is_pdf) {
                            $x = (int) (data_get($formDataAttribute, 'y', 0)) / $pdfScale;
                            $y = (int) data_get($formDataAttribute, 'x', 0) / $pdfScale;
                        }
                        $written = data_get($formDataAttribute, 'bigSizeDataUrl') ? 'hand_written' : 'draw';
                        $imageData[] = [
                            'text' => data_get($formDataAttribute, 'text', ''),
                            'x' => $x,
                            'y' => $y,
                            'page_number' => (int) data_get($formDataAttribute, 'page_number', 1),
                            'signature_type' => $written,
                            'type' => 'image',
                        ];
                    } else {
                        // Apply PDF coordinate scaling for non-signature form data (matches original API lines 296-299)
                        if ($envelopeDocument->is_pdf) {
                            $x = (int) data_get($formDataAttribute, 'y', 0) / $pdfScale;
                            $y = (int) (data_get($formDataAttribute, 'x', 0) + 15) / $pdfScale;
                        }
                        $formData[] = [
                            'text' => data_get($formDataAttribute, 'text', ''),
                            'x' => $x,
                            'y' => $y,
                            'page_number' => (int) data_get($formDataAttribute, 'page_number', 1),
                            'type' => data_get($formDataAttribute, 'type', ''),
                        ];
                    }
                }
            }

            // Process signature attributes (matches original API structure)
            if (! empty($signatureAttributes)) {
                foreach ($signatureAttributes as $signatureAttribute) {
                    VisibleSignature::create([
                        'document_id' => $documentId,
                        'document_signer_id' => $documentSignerId,
                        'signature_attributes' => $signatureAttribute,
                    ]);

                    $x = (int) data_get($signatureAttribute, 'x', 0);
                    $y = (int) data_get($signatureAttribute, 'y', 0);

                    // Apply PDF coordinate scaling for signature attributes (matches original API lines 321-324)
                    if ($envelopeDocument->is_pdf) {
                        $x = (int) data_get($signatureAttribute, 'y', 0) / $pdfScale;
                        $y = (int) data_get($signatureAttribute, 'x', 0) / $pdfScale;
                    }
                    $written = data_get($signatureAttribute, 'bigSizeDataUrl') ? 'hand_written' : 'draw';

                    $imageData[] = [
                        'text' => data_get($signatureAttribute, 'data', ''),
                        'x' => $x,
                        'y' => $y,
                        'page_number' => (int) data_get($signatureAttribute, 'page_number', 1),
                        'signature_type' => $written,
                        'type' => 'image',
                    ];
                }
            }

            return [
                'status' => true,
                'form_data' => $formData,
                'image_data' => $imageData,
            ];

        } catch (\Throwable $e) {
            return [
                'status' => false,
                'message' => 'Signature processing failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Process PDF with Python script - matches original API logic
     */
    private function processPdfWithScript($envelopeDocument, array $formData, array $imageData, int $documentId): array
    {
        try {
            // Generate PDF with Python script (matches original API lines 317-334)
            $templateName = isset($envelopeDocument->template_name) ? str_replace(' ', '_', $envelopeDocument->template_name) : 'document';
            $documentName = $templateName.'_'.time().'_'.rand(111, 9999).'.pdf';
            $processedPdfPath = storage_path('app/processed_pdf/'.Str::random(26).'.pdf');

            // Process PDF with Python script using traditional approach
            $context = getStreamContext();
            $removeTags = ['[text_entry]', '[s:employee]'];
            $unSignedPDF = $envelopeDocument->initial_pdf_path;

            // Debug logging - check if source PDF exists
            \Log::info('PDF Processing Debug', [
                'document_id' => $documentId,
                'initial_pdf_path' => $unSignedPDF,
                'source_pdf_exists' => file_exists($unSignedPDF),
                'envelope_name' => data_get($envelopeDocument, 'envelope.envelope_name'),
            ]);

            $unSignedPdfContent = file_get_contents($unSignedPDF, false, $context);
            if ($unSignedPdfContent === false) {
                throw new \Exception("Failed to read source PDF: {$unSignedPDF}");
            }

            $pdfPath = 'unsigned_pdfs/'.Str::ulid().'.pdf';
            Storage::disk(config('signserver.signServerStorageDisk'))->put($pdfPath, $unSignedPdfContent);

            // Use storage_path directly for local disk (matches working SequiDocsDigitalSignatureController pattern)
            $absolutePdfPath = storage_path('app/'.$pdfPath);
            $pdfOutputPath = storage_path('app/processed_pdf/'.Str::ulid().'.pdf');

            // Debug: Log the exact storage configuration and paths
            \Log::info('Storage Configuration Debug', [
                'document_id' => $documentId,
                'storage_disk_config' => config('signserver.signServerStorageDisk'),
                'storage_path' => storage_path('app/'),
                'absolute_pdf_path' => $absolutePdfPath,
                'pdf_output_path' => $pdfOutputPath,
                'source_pdf_exists' => file_exists($absolutePdfPath),
                'processed_pdf_dir' => dirname($pdfOutputPath),
                'processed_pdf_dir_exists' => is_dir(dirname($pdfOutputPath)),
            ]);

            // Create output directory if it doesn't exist
            $outputDir = dirname($pdfOutputPath);
            if (! is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
                \Log::info('Created output directory', ['path' => $outputDir]);
            }

            // Debug logging - verify paths and permissions
            \Log::info('PDF Processing Paths', [
                'document_id' => $documentId,
                'source_pdf_size' => strlen($unSignedPdfContent),
                'absolute_pdf_path' => $absolutePdfPath,
                'pdf_output_path' => $pdfOutputPath,
                'source_exists' => file_exists($absolutePdfPath),
                'output_dir_exists' => is_dir($outputDir),
                'output_dir_writable' => is_writable($outputDir),
                'storage_disk' => config('signserver.signServerStorageDisk'),
            ]);

            $params = [
                'pdf_input' => $absolutePdfPath,
                'pdf_output' => $pdfOutputPath,
                'replacements' => $formData,
                'replacements_image' => $imageData,
                'removeTags' => $removeTags,
                'envelope_name' => data_get($envelopeDocument, 'envelope.envelope_name'),
            ];

            $json = json_encode($params, JSON_UNESCAPED_SLASHES);

            // Debug log the Python script parameters
            \Log::info('Python Script Parameters', [
                'document_id' => $documentId,
                'params' => $params,
                'form_data_count' => count($formData),
                'image_data_count' => count($imageData),
            ]);

            $scriptResult = callPyScript('sequidocs-alter-pdf-data.py', [$json]);

            // Log the script result for debugging
            \Log::info('Python Script Result', [
                'document_id' => $documentId,
                'script_result' => $scriptResult,
                'expected_output_path' => $pdfOutputPath,
                'output_file_exists' => file_exists($pdfOutputPath),
                'output_file_size' => file_exists($pdfOutputPath) ? filesize($pdfOutputPath) : 0,
                'directory_exists' => is_dir(dirname($pdfOutputPath)),
                'directory_writable' => is_writable(dirname($pdfOutputPath)),
                'directory_contents' => is_dir(dirname($pdfOutputPath)) ? scandir(dirname($pdfOutputPath)) : 'N/A',
            ]);

            if (! data_get($scriptResult, 'success')) {
                NewSequiDocsSignatureRequestLog::create([
                    'ApiName' => 'prepare-document',
                    'user_array' => json_encode(['envelope_document_id' => $documentId]),
                    'signature_request_response' => is_array($scriptResult) ? json_encode($scriptResult) : json_encode([$scriptResult]),
                ]);

                return [
                    'status' => false,
                    'message' => 'PDF processing failed: '.data_get($scriptResult, 'message'),
                    'error_type' => data_get($scriptResult, 'error_type', 'unknown'),
                    'details' => data_get($scriptResult, 'details'),
                ];
            }

            // Verify the output file was actually created
            if (! file_exists($pdfOutputPath)) {
                \Log::error('PDF output file not created', [
                    'document_id' => $documentId,
                    'expected_path' => $pdfOutputPath,
                    'script_result' => $scriptResult,
                ]);

                return [
                    'status' => false,
                    'message' => 'PDF processing completed but output file not found',
                    'error_type' => 'file_not_created',
                    'details' => ['expected_path' => $pdfOutputPath],
                ];
            }

            return [
                'status' => true,
                'pdf_output_path' => $pdfOutputPath,
                'document_name' => $documentName,
            ];

        } catch (\Throwable $e) {
            \Log::error('PDF Processing Exception', [
                'document_id' => $documentId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => false,
                'message' => 'PDF processing failed: '.$e->getMessage(),
                'error_details' => [
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ],
            ];
        }
    }
}
