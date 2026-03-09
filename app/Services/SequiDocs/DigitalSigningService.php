<?php

namespace App\Services\SequiDocs;

use App\Models\EnvelopeDocument;
use App\Models\NewSequiDocsSignatureRequestLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DigitalSigningService
{
    /**
     * Handle digital signing and S3 storage
     */
    public function signAndStore(array $documentData): array
    {
        try {
            $processedPdfPath = data_get($documentData, 'processed_pdf_path');
            $documentName = data_get($documentData, 'document_name');
            $envelopeDocumentId = data_get($documentData, 'envelope_document_id');

            // Validate that processed PDF exists
            if (! file_exists($processedPdfPath)) {
                return [
                    'status' => false,
                    'message' => 'Processed PDF file not found',
                ];
            }

            // Send PDF for digital signing
            $digitallySignedPdfPath = $this->sendPdfForDigitalSigner($processedPdfPath, $documentName, $envelopeDocumentId);

            if (! data_get($digitallySignedPdfPath, 'status')) {
                return [
                    'status' => false,
                    'message' => data_get($digitallySignedPdfPath, 'message'),
                ];
            }

            // Check if signed PDF exists
            if (! Storage::disk(config('signserver.signServerStorageDisk'))->exists(data_get($digitallySignedPdfPath, 'document_path'))) {
                return [
                    'status' => false,
                    'message' => 'Failed to save digitally signed PDF!!',
                ];
            }

            // Update EnvelopeDocument status and processed path
            $envelopeDocument = EnvelopeDocument::findOrFail($envelopeDocumentId);
            $envelopeDocument->processed_pdf_path = data_get($digitallySignedPdfPath, 'document_path');
            $envelopeDocument->status = 2;
            $envelopeDocument->save();

            // Upload to S3
            $awsPath = 'document/'.$documentName;
            $fileContent = Storage::disk(config('signserver.signServerStorageDisk'))->get(data_get($digitallySignedPdfPath, 'document_path'));
            $s3Return = uploadS3UsingEnv(config('app.domain_name').'/'.$awsPath, $fileContent, false, 'private');

            if (! data_get($s3Return, 'status')) {
                return [
                    'status' => false,
                    'message' => data_get($s3Return, 'message'),
                ];
            }

            // IMPORTANT: Final EnvelopeDocument updates (matching original API functionality)
            $envelopeDocument->update([
                'signed_status' => 1,
                'signed_date' => now(),
                'signed_document' => $awsPath,
            ]);

            // Get user_id and document_id from NewSequiDocsDocument for DocumentCompletionService
            // This matches the original API logic where document is found by signature_request_document_id
            $document = \App\Models\NewSequiDocsDocument::where('signature_request_document_id', $envelopeDocumentId)->first();

            if (! $document) {
                return [
                    'status' => false,
                    'message' => 'NewSequiDocsDocument not found for envelope_document_id: '.$envelopeDocumentId,
                ];
            }

            $userId = $document->user_id; // Can be null for guest users

            return [
                'status' => true,
                'message' => 'Document signed and stored successfully',
                'data' => [
                    's3_path' => $awsPath,
                    'signed_pdf_path' => data_get($digitallySignedPdfPath, 'document_path'),
                    'envelope_document_id' => $envelopeDocumentId,
                    'document_id' => $document->id,  // NewSequiDocsDocument.id for DocumentCompletionService
                    'user_id' => $userId,  // Include user_id for DocumentCompletionService (can be null for guests)
                ],
            ];

        } catch (\Throwable $e) {
            return [
                'status' => false,
                'message' => 'Digital signing failed: '.$e->getMessage(),
                'error_details' => [
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ],
            ];
        }
    }

    /**
     * Send PDF for digital signature processing
     *
     * @param  string  $pdfPath  Path to the PDF to be digitally signed
     * @param  string  $documentName  Name of the document
     * @param  int  $documentId  Document ID for logging
     * @return array Status and result data
     */
    private function sendPdfForDigitalSigner(string $pdfPath, string $documentName, int $documentId): array
    {
        if (! $pdfPath) {
            return ['status' => false, 'message' => 'Invalid PDF path'];
        }

        $signResponse = $this->applyDigitalSignature($pdfPath, $documentId);
        if (! $signResponse['status']) {
            return ['status' => false, 'message' => $signResponse['message']];
        }

        $pdf = $signResponse['data'];
        // $pdf = file_get_contents($pdfPath); // UNCOMMENT WHEN NOT USING SSL
        $documentPath = 'signed_pdfs/'.$documentName;
        Storage::disk(config('signserver.signServerStorageDisk'))->put($documentPath, $pdf);

        return ['status' => true, 'document_path' => $documentPath];
    }

    /**
     * Apply digital signature using SSL.com API
     *
     * @param  string  $pdfPath  Path to the PDF file
     * @param  int  $documentId  Document ID for logging
     * @return array Status and digitally signed PDF data
     */
    private function applyDigitalSignature(string $pdfPath, int $documentId): array
    {
        $response = $this->getAccessToken();
        if (! $response['status']) {
            NewSequiDocsSignatureRequestLog::create([
                'ApiName' => 'ssl-token-error',
                'user_array' => json_encode(['envelope_document_id' => $documentId]),
                'signature_request_response' => is_array($response) ? json_encode($response) : json_encode([$response]),
            ]);

            return ['status' => false, 'message' => $response['message']];
        }

        $token = $response['token'];
        try {
            // Read PDF file as binary content
            $pdfContent = file_get_contents($pdfPath);
            if (! $pdfContent) {
                NewSequiDocsSignatureRequestLog::create([
                    'ApiName' => 'process-signed-document',
                    'user_array' => json_encode(['envelope_document_id' => $documentId]),
                    'signature_request_response' => json_encode(['Could not read PDF file']),
                ]);

                return ['status' => false, 'message' => 'Could not read PDF file'];
            }

            // 1. UPLOAD THE PDF - using curl directly
            $ch = curl_init(config('services.ssldotcom.base_url').'/v1/pdf/upload');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/pdf',
                'Authorization: Bearer '.$token,
                'Credential-Id: '.config('services.ssldotcom.credential_id'),
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $pdfContent); // Send raw binary data
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

            $uploadResponse = curl_exec($ch);
            if (curl_errno($ch)) {
                curl_close($ch);

                return ['status' => false, 'message' => 'Upload request error: '.curl_error($ch)];
            }
            curl_close($ch);

            $uploadObj = json_decode($uploadResponse, true);
            $uploadedId = $uploadObj['id'] ?? null;
            if (! $uploadedId) {
                NewSequiDocsSignatureRequestLog::create([
                    'ApiName' => 'ssl-upload-pdf',
                    'user_array' => json_encode(['envelope_document_id' => $documentId]),
                    'signature_request_response' => is_array($uploadResponse) ? json_encode($uploadResponse) : json_encode([$uploadResponse]),
                ]);

                return ['status' => false, 'message' => 'Failed to upload PDF: '.$uploadResponse];
            }

            // 2. SIGN THE PDF - using curl directly
            $ch = curl_init(config('services.ssldotcom.base_url').'/v1/pdf/sign');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Transfer-Encoding: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer '.$token,
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['id' => $uploadedId]));
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

            $pdfContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300 && ! $error) {
                return ['status' => true, 'data' => $pdfContent];
            } else {
                NewSequiDocsSignatureRequestLog::create([
                    'ApiName' => 'ssl-retrieve-pdf',
                    'user_array' => json_encode(['envelope_document_id' => $documentId]),
                    'signature_request_response' => is_array($pdfContent) ? json_encode($pdfContent) : json_encode([$pdfContent]),
                ]);

                return ['status' => false, 'message' => 'Failed to sign PDF. Error: '.$pdfContent.', HTTP code: '.$httpCode];
            }
        } catch (\Exception $e) {
            NewSequiDocsSignatureRequestLog::create([
                'ApiName' => 'process-signed-document',
                'user_array' => json_encode(['envelope_document_id' => $documentId]),
                'signature_request_response' => json_encode([$e->getMessage().' '.$e->getLine()]),
            ]);

            return ['status' => false, 'message' => $e->getMessage().' '.$e->getLine()];
        }
    }

    /**
     * Get an access token from the SSL.com API (with caching)
     *
     * @return array Array with status (boolean) and token string or error message
     */
    private function getAccessToken(): array
    {
        $cacheKey = 'ssl_com_access_token';

        // Try to get cached token first
        $cachedToken = Cache::get($cacheKey);
        if ($cachedToken) {
            return ['status' => true, 'token' => $cachedToken];
        }

        try {
            $response = Http::timeout(180)->connectTimeout(30)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post(config('services.ssldotcom.token_base_url').'/oauth2/token', [
                'client_id' => config('services.ssldotcom.client_id'),
                'client_secret' => config('services.ssldotcom.client_secret'),
                'grant_type' => 'password',
                'username' => config('services.ssldotcom.username'),
                'password' => config('services.ssldotcom.password'),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['access_token'])) {
                    // Cache token for 50 minutes (SSL.com tokens typically expire in 1 hour)
                    Cache::put($cacheKey, $data['access_token'], 50 * 60); // 50 minutes in seconds

                    return ['status' => true, 'token' => $data['access_token'], 'data' => $data];
                } else {
                    return ['status' => false, 'message' => 'Access token not found in response', 'data' => $data];
                }
            }

            $error = $response->json();
            $errorMessage = isset($error['error_description']) ? $error['error_description'] : 'Unknown error';

            return ['status' => false, 'message' => $errorMessage, 'error' => $error];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Validate digital signing requirements
     */
    public function validateSigningRequirements(array $documentData): array
    {
        $requiredFields = ['processed_pdf_path', 'document_name', 'envelope_document_id'];

        foreach ($requiredFields as $field) {
            if (! isset($documentData[$field]) || empty($documentData[$field])) {
                return [
                    'status' => false,
                    'message' => "Missing required field: {$field}",
                ];
            }
        }

        return [
            'status' => true,
            'message' => 'Validation passed',
        ];
    }

    /**
     * Get signing service status
     */
    public function getSigningStatus(): array
    {
        return [
            'service' => 'DigitalSigningService',
            'status' => 'active',
            'timestamp' => now(),
            'storage_disk' => config('signserver.signServerStorageDisk'),
        ];
    }
}
