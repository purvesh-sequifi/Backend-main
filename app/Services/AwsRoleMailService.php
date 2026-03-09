<?php

namespace App\Services;

use Exception;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AwsRoleMailService
{
    /**
     * Send an email using AWS IAM Role authentication
     *
     * @param  string  $to  Email recipient
     * @param  string  $subject  Email subject
     * @param  string  $view  Email view template
     * @param  array  $data  Data to pass to the view
     * @param  array  $attachments  Optional attachments
     * @return bool Whether the email was sent successfully
     */
    public function send(string $to, string $subject, string $view, array $data = [], array $attachments = []): bool
    {
        try {
            // Use the ses-role mailer if AWS_USE_IAM_ROLE is true, otherwise use default
            $mailer = config('services.ses.use_iam_role') ? 'ses-role' : null;

            Mail::mailer($mailer)->send($view, $data, function (Message $message) use ($to, $subject, $attachments) {
                $message->to($to)
                    ->subject($subject);

                // Add attachments if any
                foreach ($attachments as $attachment) {
                    if (isset($attachment['path']) && file_exists($attachment['path'])) {
                        $message->attach($attachment['path'], $attachment['options'] ?? []);
                    }
                }
            });

            // Log the successful email send with activity logger
            activity()
                ->withProperties([
                    'recipient' => $to,
                    'subject' => $subject,
                    'auth_method' => config('services.ses.use_iam_role') ? 'IAM Role' : 'Access Keys',
                ])
                ->log('Email sent successfully');

            return true;
        } catch (Exception $e) {
            // Log the error
            Log::error('Failed to send email: '.$e->getMessage(), [
                'recipient' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
