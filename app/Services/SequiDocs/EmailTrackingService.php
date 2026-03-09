<?php

namespace App\Services\SequiDocs;

use App\Models\NewSequiDocsDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmailTrackingService
{
    /**
     * Generate a unique tracking token for email tracking
     */
    public static function generateTrackingToken(): string
    {
        return Str::random(32).'_'.time();
    }

    /**
     * Add tracking pixel to email template
     */
    public static function addTrackingPixelToEmail(string $emailTemplate, string $trackingToken): string
    {
        // Generate tracking URL with proper domain
        $trackingUrl = self::generateTrackingUrl($trackingToken);

        // Create tracking pixel HTML
        $trackingPixel = '<img src="'.$trackingUrl.'" width="1" height="1" style="display:none;" alt="" />';

        // Try to add pixel before closing body tag, or at the end if no body tag
        if (stripos($emailTemplate, '</body>') !== false) {
            $emailTemplate = str_ireplace('</body>', $trackingPixel.'</body>', $emailTemplate);
        } else {
            $emailTemplate .= $trackingPixel;
        }

        return $emailTemplate;
    }

    /**
     * Generate tracking URL with proper domain for email clients
     */
    public static function generateTrackingUrl(string $trackingToken): string
    {
        // Check if we have a dedicated email tracking URL configured
        $emailTrackingUrl = config('services.email_tracking.url');

        // Fallback to email_tracking_domain, then app.url
        if (! $emailTrackingUrl) {
            $emailTrackingUrl = config('app.email_tracking_domain') ?: config('app.url');
        }

        // If still localhost, log a warning for production consideration
        if (str_contains($emailTrackingUrl, 'localhost')) {
            Log::warning('Email tracking using localhost URL - may not work in external email clients', [
                'tracking_token' => $trackingToken,
                'url' => $emailTrackingUrl,
            ]);
        }

        // Build the full tracking URL
        $baseUrl = rtrim($emailTrackingUrl, '/');

        return $baseUrl.'/api/v2/sequidocs/email-tracking/track/'.$trackingToken;
    }

    /**
     * Initialize email tracking for a document
     *
     * @return string|null Returns tracking token on success, null on failure
     */
    public static function initializeEmailTracking(int $documentId): ?string
    {
        try {
            $document = NewSequiDocsDocument::find($documentId);

            if (! $document) {
                Log::error('Email tracking: Document not found', ['document_id' => $documentId]);

                return null;
            }

            // Generate unique tracking token
            $trackingToken = self::generateTrackingToken();

            // Update document with tracking info
            $document->update([
                'email_tracking_token' => $trackingToken,
                'email_sent_at' => now(),
                'email_open_count' => 0,
                'email_opened_at' => null,
                'email_open_details' => null,
            ]);

            Log::info('Email tracking initialized', [
                'document_id' => $documentId,
                'tracking_token' => $trackingToken,
                'user_id' => $document->user_id,
                'category_id' => $document->category_id,
            ]);

            return $trackingToken;

        } catch (\Exception $e) {
            Log::error('Email tracking initialization failed', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return null;
        }
    }

    /**
     * Check if email has been opened
     */
    public static function getEmailOpenStatus(int $documentId): array
    {
        $document = NewSequiDocsDocument::find($documentId);

        if (! $document) {
            return [
                'exists' => false,
                'is_opened' => false,
                'open_count' => 0,
                'first_opened_at' => null,
                'last_opened_at' => null,
            ];
        }

        $openDetails = $document->email_open_details ?? [];
        $lastOpenedAt = null;

        if (! empty($openDetails)) {
            $lastOpen = end($openDetails);
            $lastOpenedAt = $lastOpen['opened_at'] ?? null;
        }

        return [
            'exists' => true,
            'is_opened' => ! is_null($document->email_opened_at),
            'open_count' => $document->email_open_count,
            'first_opened_at' => $document->email_opened_at,
            'last_opened_at' => $lastOpenedAt,
            'email_sent_at' => $document->email_sent_at,
            'tracking_token' => $document->email_tracking_token,
        ];
    }

    /**
     * Get email tracking statistics for offer letters
     */
    public static function getOfferLetterTrackingStats(int $userId, string $userIdFrom = 'onboarding_employees'): array
    {
        $offerLetters = NewSequiDocsDocument::where([
            'user_id' => $userId,
            'user_id_from' => $userIdFrom,
            'category_id' => 1, // Offer letter category
            'is_active' => 1,
        ])->get();

        $stats = [
            'total_offer_letters' => $offerLetters->count(),
            'emails_sent' => $offerLetters->whereNotNull('email_sent_at')->count(),
            'emails_opened' => $offerLetters->whereNotNull('email_opened_at')->count(),
            'total_opens' => $offerLetters->sum('email_open_count'),
            'offer_letters' => [],
        ];

        foreach ($offerLetters as $document) {
            $stats['offer_letters'][] = [
                'document_id' => $document->id,
                'description' => $document->description,
                'email_sent_at' => $document->email_sent_at,
                'email_opened_at' => $document->email_opened_at,
                'email_open_count' => $document->email_open_count,
                'is_email_opened' => ! is_null($document->email_opened_at),
            ];
        }

        return $stats;
    }

    /**
     * Update document status management to include email tracking
     */
    public static function getDocumentStatusWithEmailTracking(int $userId, string $userIdFrom = 'onboarding_employees'): array
    {
        // Get all documents (active and inactive) for comprehensive email tracking stats
        $documents = NewSequiDocsDocument::where([
            'user_id' => $userId,
            'user_id_from' => $userIdFrom,
        ])->get();

        // Get only active documents for current document status
        $activeDocuments = $documents->where('is_active', 1);

        // Use all documents for email tracking stats (including inactive ones)
        $offerLetterSigned = $documents->where('category_id', 1)->where('signed_status', 1)->count();
        $offerLetterEmailOpened = $documents->where('category_id', 1)->whereNotNull('email_opened_at')->count();
        $offerLetterEmailSent = $documents->where('category_id', 1)->whereNotNull('email_sent_at')->count();

        // Use active documents for current document status
        $otherDocumentsTotal = $activeDocuments->where('category_id', '!=', 1)->count();
        $otherDocumentsSigned = $activeDocuments->where('category_id', '!=', 1)->where('signed_status', 1)->count();

        return [
            'offer_letter_signed' => $offerLetterSigned,
            'offer_letter_email_sent' => $offerLetterEmailSent,
            'offer_letter_email_opened' => $offerLetterEmailOpened,
            'other_documents_total' => $otherDocumentsTotal,
            'other_documents_signed' => $otherDocumentsSigned,
            'has_offer_letter' => $activeDocuments->where('category_id', 1)->count() > 0,
            'offer_letter_email_open_rate' => $offerLetterEmailSent > 0 ?
                round(($offerLetterEmailOpened / $offerLetterEmailSent) * 100, 2) : 0,
        ];
    }
}
