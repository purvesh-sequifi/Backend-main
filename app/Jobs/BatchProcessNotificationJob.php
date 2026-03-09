<?php

namespace App\Jobs;

use App\Models\BatchProcessTracker;
use App\Models\User;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BatchProcessNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        // Exponential backoff: 30s, 60s, 120s
        return [30, 60, 120];
    }

    /**
     * The tracker ID to send notification for.
     *
     * @var int
     */
    protected $trackerId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $trackerId)
    {
        $this->trackerId = $trackerId;
        $this->onQueue('default');
        $this->onConnection('redis');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $tracker = BatchProcessTracker::find($this->trackerId);

            if (! $tracker) {
                Log::error('BatchProcessNotificationJob: Tracker not found', [
                    'tracker_id' => $this->trackerId,
                ]);

                return;
            }

            // Only send notifications for completed or error statuses
            if (! in_array($tracker->status, ['completed', 'error', 'dispatched'])) {
                return;
            }

            // Get the user if available
            if ($tracker->user_id) {
                $user = User::find($tracker->user_id);

                if ($user && $user->email) {
                    $this->sendEmail($user, $tracker);
                }
            }

            // Mark notification as sent in the tracker
            $tracker->update([
                'stats' => array_merge($tracker->stats ?? [], [
                    'notification_sent' => true,
                    'notification_sent_at' => now()->toDateTimeString(),
                ]),
            ]);

            Log::info('BatchProcessNotificationJob: Notification sent for batch process', [
                'tracker_id' => $this->trackerId,
                'status' => $tracker->status,
            ]);
        } catch (Exception $e) {
            Log::error('BatchProcessNotificationJob: Error sending notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tracker_id' => $this->trackerId,
            ]);

            throw $e;
        }
    }

    /**
     * Send an email notification to the user.
     */
    protected function sendEmail(User $user, BatchProcessTracker $tracker): void
    {
        $subject = "Batch Process {$tracker->process_type} ";
        $status = $tracker->status;

        if ($status === 'completed') {
            $subject .= 'Completed Successfully';
        } elseif ($status === 'error') {
            $subject .= 'Encountered Errors';
        } elseif ($status === 'dispatched') {
            $subject .= 'Dispatched All Batches';
        }

        $data = [
            'user' => $user,
            'tracker' => $tracker,
            'subject' => $subject,
            'process_type' => $tracker->process_type,
            'status' => $status,
            'total_records' => $tracker->total_records,
            'processed_records' => $tracker->processed_records,
            'success_count' => $tracker->success_count,
            'error_count' => $tracker->error_count,
            'started_at' => $tracker->started_at,
            'completed_at' => $tracker->completed_at,
            'stats' => $tracker->stats,
        ];

        // For actual implementation, create an email template and use Laravel's Mail facade
        // This is a placeholder for the actual implementation
        Log::info('Would send email with data:', $data);

        // Uncomment for actual implementation:
        // Mail::send('emails.batch_process_notification', $data, function($message) use ($user, $subject) {
        //     $message->to($user->email, $user->name)->subject($subject);
        // });
    }
}
