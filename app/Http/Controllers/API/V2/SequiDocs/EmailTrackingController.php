<?php

namespace App\Http\Controllers\API\V2\SequiDocs;

use App\Http\Controllers\Controller;
use App\Models\NewSequiDocsDocument;
use App\Services\SequiDocs\OfferLetterStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class EmailTrackingController extends Controller
{
    /**
     * Track email opens using a 1x1 transparent pixel
     *
     * @return \Illuminate\Http\Response
     */
    public function trackEmailOpen(Request $request, string $token)
    {
        try {
            // Find document by tracking token
            $document = NewSequiDocsDocument::where('email_tracking_token', $token)->first();

            if (! $document) {
                Log::warning('Email tracking: Invalid token', ['token' => $token]);

                return $this->generateTrackingPixel();
            }

            // Get tracking details
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();
            $timestamp = now();

            // Prepare tracking data
            $openDetails = $document->email_open_details ?? [];
            $newOpenDetail = [
                'ip' => $ipAddress,
                'user_agent' => $userAgent,
                'opened_at' => $timestamp->toDateTimeString(),
                'referer' => $request->header('referer'),
                'accept_language' => $request->header('accept-language'),
            ];

            // Add new open detail to array
            $openDetails[] = $newOpenDetail;

            // Update document tracking info
            $updateData = [
                'email_open_count' => $document->email_open_count + 1,
                'email_open_details' => $openDetails,
            ];

            // Set first open timestamp if not already set
            if (! $document->email_opened_at) {
                $updateData['email_opened_at'] = $timestamp;
            }

            $document->update($updateData);

            Log::info('Email tracking: Email opened', [
                'document_id' => $document->id,
                'user_id' => $document->user_id,
                'user_id_from' => $document->user_id_from,
                'category_id' => $document->category_id,
                'open_count' => $document->email_open_count + 1,
                'ip' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            // Handle automatic status transition for offer letters
            if ($document->category_id == 1 && $document->user_id_from === 'onboarding_employees') {
                $statusTransitioned = OfferLetterStatusService::handleEmailOpenStatusTransition($document->id);

                if ($statusTransitioned) {
                    Log::info('Email tracking: Automatic status transition completed', [
                        'document_id' => $document->id,
                        'user_id' => $document->user_id,
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('Email tracking error', [
                'token' => $token,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return $this->generateTrackingPixel();
    }

    /**
     * Generate a 1x1 transparent PNG pixel
     */
    private function generateTrackingPixel(): \Illuminate\Http\Response
    {
        // Create a 1x1 transparent PNG pixel
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

        return Response::make($pixel, 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => strlen($pixel),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Get email tracking statistics for a document
     */
    public function getTrackingStats(Request $request): JsonResponse
    {
        $request->validate([
            'document_id' => 'required|exists:new_sequi_docs_documents,id',
        ]);

        $document = NewSequiDocsDocument::find($request->document_id);

        $stats = [
            'document_id' => $document->id,
            'email_sent_at' => $document->email_sent_at,
            'email_opened_at' => $document->email_opened_at,
            'email_open_count' => $document->email_open_count,
            'is_email_opened' => ! is_null($document->email_opened_at),
            'tracking_token' => $document->email_tracking_token,
            'open_details' => $document->email_open_details ?? [],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get email tracking statistics for multiple documents
     */
    public function getBulkTrackingStats(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required',
            'user_id_from' => 'required|in:users,onboarding_employees',
            'category_id' => 'nullable|integer',
        ]);

        $query = NewSequiDocsDocument::where([
            'user_id' => $request->user_id,
            'user_id_from' => $request->user_id_from,
            'is_active' => 1,
        ]);

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        $documents = $query->select([
            'id', 'category_id', 'description', 'email_sent_at',
            'email_opened_at', 'email_open_count', 'email_tracking_token',
        ])->get();

        $stats = $documents->map(function ($document) {
            return [
                'document_id' => $document->id,
                'category_id' => $document->category_id,
                'description' => $document->description,
                'email_sent_at' => $document->email_sent_at,
                'email_opened_at' => $document->email_opened_at,
                'email_open_count' => $document->email_open_count,
                'is_email_opened' => ! is_null($document->email_opened_at),
                'has_tracking_token' => ! is_null($document->email_tracking_token),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Check if an employee is eligible for status transition on email open
     */
    public function checkStatusTransitionEligibility(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:onboarding_employees,id',
        ]);

        try {
            $transitionInfo = OfferLetterStatusService::getEmployeeStatusTransitionInfo($request->employee_id);

            return response()->json([
                'success' => true,
                'data' => $transitionInfo,
            ]);

        } catch (\Exception $e) {
            Log::error('Error checking status transition eligibility', [
                'employee_id' => $request->employee_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check status transition eligibility',
            ], 500);
        }
    }

    /**
     * Get all available status transitions for offer letter email opens
     */
    public function getAvailableStatusTransitions(): JsonResponse
    {
        try {
            $transitions = OfferLetterStatusService::getAvailableTransitions();

            return response()->json([
                'success' => true,
                'data' => [
                    'transitions' => $transitions,
                    'description' => 'These status transitions will be automatically triggered when offer letter emails are opened',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting available status transitions', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get available status transitions',
            ], 500);
        }
    }

    /**
     * Check if a document is eligible for status transition tracking
     */
    public function checkDocumentEligibility(Request $request): JsonResponse
    {
        $request->validate([
            'document_id' => 'required|exists:new_sequi_docs_documents,id',
        ]);

        try {
            $isEligible = OfferLetterStatusService::isDocumentEligibleForStatusTransition($request->document_id);

            return response()->json([
                'success' => true,
                'data' => [
                    'document_id' => $request->document_id,
                    'is_eligible' => $isEligible,
                    'message' => $isEligible
                        ? 'Document is eligible for automatic status transition on email open'
                        : 'Document is not eligible for automatic status transition',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error checking document eligibility', [
                'document_id' => $request->document_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check document eligibility',
            ], 500);
        }
    }
}
