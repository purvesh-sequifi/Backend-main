<?php

namespace App\Jobs;

use App\Models\OneTimePayments;
use App\Models\PayrollHistory;
use App\Services\QuickBooksService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * CreateJournalEntryJob
 *
 * This job processes payroll data to create journal entries in QuickBooks.
 * It handles both regular payroll entries and one-time payments.
 * The job is scheduled to run via the retry:journal-entry-jobs command
 * which is configured in the task scheduler to run every five minutes.
 */
class CreateJournalEntryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The pay period start date
     */
    protected string $startDate;

    /**
     * The pay period end date
     */
    protected string $endDate;

    /**
     * The Everee payment request ID, if applicable
     */
    protected ?int $payablePaymentRequestId;

    /**
     * The maximum time the job can run in seconds
     */
    public int $timeout = 120;

    /**
     * The number of times the job may be attempted
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     *
     * @param  string  $startDate  The pay period start date
     * @param  string  $endDate  The pay period end date
     * @param  int|null  $payablePaymentRequestId  The Everee payment request ID (optional)
     */
    public function __construct(string $startDate, string $endDate, ?int $payablePaymentRequestId = null)
    {
        $this->onQueue('default');
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->payablePaymentRequestId = $payablePaymentRequestId;
    }

    /**
     * Execute the job.
     *
     * @param  QuickBooksService  $quickBooksService  The QuickBooks service dependency
     */
    public function handle(QuickBooksService $quickBooksService): void
    {
        createLogFile('quickbooks', '['.now()."] CreateJournalEntryJob started for period: {$this->startDate} to {$this->endDate}");

        try {
            $paymentType = 'payroll';
            $count = 0;
            $records = $this->getRecordsToProcess();

            // If records present then create Journal Entry
            if ($records && $records->isNotEmpty()) {
                createLogFile('quickbooks', '['.now().'] Found '.$records->count().' records to create journal entries for');

                // Call to quickBooksService create journal entry method
                $quickBooksService->createJournalEntry($records, $paymentType, $count);

                createLogFile('quickbooks', '['.now()."] Successfully created journal entries for {$paymentType} ".
                    "for Pay Period: {$this->startDate} to {$this->endDate}");
            } else {
                createLogFile('quickbooks', '['.now().'] No records found to process for pay period: '.
                    "{$this->startDate} to {$this->endDate}");
            }
        } catch (Exception $e) {
            $errorMessage = "Journal entry creation failed for pay period: {$this->startDate} to {$this->endDate}. ".
                "Error: {$e->getMessage()}";

            createLogFile('quickbooks', '['.now().'] '.$errorMessage);

            // Send to Sentry if available
            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }

            // Re-throw for job retry if tries remain
            throw $e;
        }
    }

    /**
     * Get the records that need journal entries processed.
     *
     * @return Collection The records to process
     */
    private function getRecordsToProcess(): Collection
    {
        $records = collect();

        // Check if payroll history records need processing
        if ($this->hasPayrollRecordsToProcess()) {
            $records = $this->getPayrollRecords();
        }
        // Check for one-time payments if no payroll records and payment request ID exists
        elseif ($this->payablePaymentRequestId !== null && $this->payablePaymentRequestId > 0) {
            $records = $this->getOneTimePaymentRecords();
        }

        return $records;
    }

    /**
     * Check if there are payroll records to process.
     *
     * @return bool True if payroll records need processing
     */
    private function hasPayrollRecordsToProcess(): bool
    {
        return PayrollHistory::where('pay_period_from', $this->startDate)
            ->where('pay_period_to', $this->endDate)
            ->whereIn('everee_payment_status', [0, 3])
            ->whereNull('quickbooks_journal_entry_id')
            ->exists();
    }

    /**
     * Get payroll records that need journal entries.
     *
     * @return Collection The payroll records to process
     */
    private function getPayrollRecords(): Collection
    {
        // Count existing entries for this period
        $count = PayrollHistory::where('pay_period_from', $this->startDate)
            ->where('pay_period_to', $this->endDate)
            ->whereIn('everee_payment_status', [0, 3])
            ->whereNotNull('quickbooks_journal_entry_id')
            ->where('net_pay', '>', 0)
            ->count();

        // Check for manual payments
        $hasManualPayments = PayrollHistory::where('pay_period_from', $this->startDate)
            ->where('pay_period_to', $this->endDate)
            ->where('pay_type', 'Bank')
            ->where('everee_payment_status', 3)
            ->whereNull('quickbooks_journal_entry_id')
            ->where('net_pay', '>', 0)
            ->exists();

        createLogFile('quickbooks', '['.now()."] Processing payroll for period: {$this->startDate} to {$this->endDate}, ".
            'Has manual payments: '.($hasManualPayments ? 'Yes' : 'No'));

        // Get regular or all entries based on manual payment existence
        $query = PayrollHistory::with('workertype')
            ->where('pay_period_from', $this->startDate)
            ->where('pay_period_to', $this->endDate)
            ->whereNull('quickbooks_journal_entry_id')
            ->where('net_pay', '>', 0);

        if ($hasManualPayments) {
            $query->whereIn('everee_payment_status', [0, 3]);
        } else {
            $query->where('everee_payment_status', 0);
        }

        return $query->get();
    }

    /**
     * Get one-time payment records that need journal entries.
     *
     * @return Collection The one-time payment records to process
     */
    private function getOneTimePaymentRecords(): Collection
    {
        // Safety check - if payablePaymentRequestId is null, return empty collection
        if ($this->payablePaymentRequestId === null) {
            return collect();
        }

        // Check if one-time payments exist for the given payment request ID
        $exists = OneTimePayments::where('everee_payment_req_id', $this->payablePaymentRequestId)
            ->where('everee_payment_status', 1)
            ->whereNull('quickbooks_journal_entry_id')
            ->exists();

        if (! $exists) {
            createLogFile('quickbooks', '['.now()."] No one-time payments found for request ID: {$this->payablePaymentRequestId}");

            return collect();
        }

        // Get one-time payment records
        return OneTimePayments::with(['usersdata' => function ($query) {
            $query->select('id', 'first_name', 'last_name', 'email');
        }, 'adjustment'])
            ->where(['payment_status' => 3, 'everee_payment_status' => 1])
            ->where('everee_payment_req_id', $this->payablePaymentRequestId)
            ->get();
    }
}
