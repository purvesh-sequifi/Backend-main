<?php

namespace App\Services;

use App\Models\ApprovalsAndRequestLock;
use App\Models\ClawbackSettlementLock;
use App\Models\Crms;
use App\Models\CrmSetting;
use App\Models\evereeTransectionLog;
use App\Models\OneTimePayments;
use App\Models\PayrollAdjustmentDetail;
use App\Models\PayrollAdjustmentLock;
use App\Models\PayrollDeductionLock;
use App\Models\PayrollHistory;
use App\Models\PayrollHourlySalaryLock;
use App\Models\PayrollOvertimeLock;
use App\Models\UserCommissionLock;
use App\Models\UserOverridesLock;
use App\Models\W2PayrollTaxDeduction;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper;
use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Facades\JournalEntry;

/**
 * QuickBooksService
 *
 * This service manages all interactions with the QuickBooks API, handling authentication,
 * token management, journal entry creation, and account management.
 *
 * The service is used by the QuickBooksController and scheduled jobs to:
 * - Authenticate with QuickBooks OAuth2
 * - Manage refresh/access tokens
 * - Create journal entries for payroll and one-time payments
 * - Map account IDs between the application and QuickBooks
 *
 * This service is a core component of the QuickBooks integration and is used by the
 * retry:journal-entry-jobs console command that runs every five minutes to ensure
 * all payroll records have corresponding journal entries.
 */
class QuickBooksService
{
    /**
     * QuickBooks OAuth Client ID (decrypted)
     */
    protected string $clientId;

    /**
     * QuickBooks OAuth Client Secret (decrypted)
     */
    protected string $clientSecret;

    /**
     * Initialize the QuickBooks service with encrypted credentials
     *
     * Decrypts and stores the QuickBooks client ID and secret from environment variables
     */
    public function __construct()
    {
        $this->clientId = openssl_decrypt(
            config('services.quickbooks.client_id'),
            config('app.encryption_cipher_algo'),
            config('app.encryption_key'),
            0,
            config('app.encryption_iv')
        );

        $this->clientSecret = openssl_decrypt(
            config('services.quickbooks.client_secret'),
            config('app.encryption_cipher_algo'),
            config('app.encryption_key'),
            0,
            config('app.encryption_iv')
        );
    }

    /**
     * Get QuickBooks settings from the database
     *
     * Retrieves QuickBooks configuration from the CRM settings table,
     * creating default settings if none exist.
     *
     * @return mixed QuickBooks settings object or JsonResponse on error
     */
    public function getQuickbooksSettings()
    {
        // Check if QuickBooks is enabled in the CRM table
        $crmData = Crms::where(['name' => 'QuickBooks'])->value('id');

        if (! $crmData) {
            createLogFile('quickbooks', '['.now().'] QuickBooks is not enabled for this client');

            return response()->json([
                'status' => false,
                'message' => 'QuickBooks is not enabled for this client',
            ], 401);
        }

        // Check if QuickBooks settings exist in database
        $crmSetting = CrmSetting::where('crm_id', $crmData)->first();

        // Create default settings if none exist
        if (empty($crmSetting)) {
            createLogFile('quickbooks', '['.now().'] Creating default QuickBooks settings');
            $data = [
                'refreshToken' => '',
                'accessToken' => '',
            ];

            $values = [
                'crm_id' => $crmData,
                'value' => json_encode($data),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            CrmSetting::insert($values);
            $crmSetting = CrmSetting::where('crm_id', $crmData)->first();
        }

        $quickbookSettings = json_decode($crmSetting['value']);
        $quickbookSettings->id = $crmSetting['id'];

        return $quickbookSettings;
    }

    /**
     * Get configured QuickBooks DataService instance
     *
     * Creates and returns a configured DataService instance for API interactions,
     * using stored tokens and credentials.
     *
     * @return DataService|JsonResponse DataService instance or error response
     */
    public function getDataService()
    {
        try {
            $quickbookSettings = $this->getQuickbooksSettings();

            // If settings retrieval returned an error response, return it
            if ($quickbookSettings instanceof JsonResponse) {
                return $quickbookSettings;
            }

            // Configure DataService
            $dataService = DataService::Configure([
                'auth_mode' => 'OAuth2',
                'ClientID' => $this->clientId,
                'ClientSecret' => $this->clientSecret,
                'scope' => 'com.intuit.quickbooks.accounting',
                'accessTokenKey' => $quickbookSettings->accessToken ?? '',
                'refreshTokenKey' => $quickbookSettings->refreshToken ?? '',
                'QBORealmID' => $quickbookSettings->realmId ?? '',
                'baseUrl' => config('services.quickbooks.base_url'),
            ]);

            return $dataService;
        } catch (Exception $e) {
            createLogFile('quickbooks', '['.now().'] Error configuring DataService: '.$e->getMessage());

            return response()->json([
                'error' => 'Error configuring QuickBooks connection',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh QuickBooks access token
     *
     * Obtains a new access token using the stored refresh token,
     * and updates stored tokens in the database.
     *
     * @return array|JsonResponse New token data or error response
     */
    public function getRefreshToken()
    {
        try {
            $quickbookSettings = $this->getQuickbooksSettings();

            // If settings retrieval returned an error response, return it
            if ($quickbookSettings instanceof JsonResponse) {
                return $quickbookSettings;
            }

            // Check if refresh token exists
            if (empty($quickbookSettings->refreshToken)) {
                createLogFile('quickbooks', '['.now().'] Cannot refresh token: No refresh token available');

                return response()->json([
                    'error' => 'No refresh token available',
                ], 400);
            }

            // Create OAuth helper and refresh token
            $oauth2LoginHelper = new OAuth2LoginHelper($this->clientId, $this->clientSecret);
            $accessTokenObj = $oauth2LoginHelper->refreshAccessTokenWithRefreshToken($quickbookSettings->refreshToken);

            $accessTokenValue = $accessTokenObj->getAccessToken();
            $refreshTokenValue = $accessTokenObj->getRefreshToken();

            if ($accessTokenValue && $refreshTokenValue) {
                // Update tokens in settings
                $quickbookSettings->refreshToken = $refreshTokenValue;
                $quickbookSettings->accessToken = $accessTokenValue;

                // Update database record
                CrmSetting::where('id', $quickbookSettings->id)
                    ->update([
                        'value' => json_encode($quickbookSettings),
                        'updated_at' => now(),
                    ]);

                createLogFile('quickbooks', '['.now().'] Successfully refreshed QuickBooks tokens');

                return [
                    'access_token' => $accessTokenValue,
                    'refresh_token' => $refreshTokenValue,
                ];
            }

            throw new Exception('Failed to obtain valid tokens from QuickBooks');
        } catch (Exception $e) {
            createLogFile('quickbooks', '['.now().'] Error refreshing tokens: '.$e->getMessage());

            return response()->json([
                'error' => 'Failed to refresh QuickBooks tokens',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create journal entries in QuickBooks for payroll or one-time payments
     *
     * Processes a collection of records (payroll history or one-time payments)
     * and creates corresponding journal entries in QuickBooks. Updates the records
     * with the QuickBooks journal entry ID upon successful creation.
     *
     * @param  Collection  $records  The records to create journal entries for
     * @param  string  $type  The type of records ('payroll' or 'one_time_payment')
     * @param  int  $count  Initial counter for entry numbering
     * @return mixed Response with created journal entries or error message
     */
    public function createJournalEntry(Collection $records, string $type = 'payroll', int $count = 0)
    {
        // Refresh token to ensure we have a valid authorization
        $tokenResponse = $this->getRefreshToken();
        if ($tokenResponse instanceof JsonResponse) {
            return $tokenResponse; // Return error if token refresh failed
        }

        // Set up QuickBooks DataService
        $dataService = $this->getDataService();
        if ($dataService instanceof JsonResponse) {
            return $dataService; // Return error if DataService initialization failed
        }

        $totalAmount = 0;
        $resultData = [];
        $fromDate = '';
        $toDate = '';
        $docNumber = 'Journal-entry';
        $amount = 0;

        createLogFile('quickbooks', '['.now().'] Creating journal entries for '.$records->count().' records of type: '.$type);

        foreach ($records as $payroll) {
            $count++;
            $i = 1;
            $lineItems = [];
            $userName = $payroll?->usersdata?->first_name.' '.$payroll?->usersdata?->last_name;
            $fromDate = $payroll?->pay_period_from;
            $toDate = $payroll?->pay_period_to;
            $payPeriod = str_replace('-', '.', date('m-d-y', strtotime($fromDate)));

            if ($type == 'one_time_payment' && $payroll->amount > 0) {
                $adjustmentName = $payroll?->adjustment?->name;
                $docNumber = "OneTimePayment-{$payroll->id}";
                $totalAmount = $payroll->amount;
                $description = "One Time Payment - {$payroll->id}";
                $adjustmentType = $payroll?->adjustment?->type;
                if (! empty($adjustmentType) && $adjustmentType == 2) {
                    $lineItems[] = [
                        'Description' => "Paid {$adjustmentName} to - {$userName}", // "Payroll for pay period {$fromDate} - {$toDate}",
                        'DetailType' => 'JournalEntryLineDetail',
                        'Id' => (string) $i++,
                        'Amount' => $payroll->amount,
                        'JournalEntryLineDetail' => [
                            'PostingType' => 'Credit',
                            'AccountRef' => [
                                'value' => $this->getAccountId('Reimbursement Account'), // Use correct account ID
                                'name' => 'Reimbursement Account',
                            ],
                        ],
                    ];
                } else {
                    $lineItems[] = [
                        'Description' => "Paid {$adjustmentName} to - {$userName}", // "Payroll for pay period {$fromDate} - {$toDate}",
                        'DetailType' => 'JournalEntryLineDetail',
                        'Id' => (string) $i++,
                        'Amount' => $payroll->amount,
                        'JournalEntryLineDetail' => [
                            'PostingType' => 'Credit',
                            'AccountRef' => [
                                'value' => $this->getAccountId('External Payment Account'), // Use correct account ID
                                'name' => 'External Payment Account',
                            ],
                        ],
                    ];
                }

            } else {
                $pay_period_from = date('m/d/Y', strtotime($fromDate));
                $pay_period_to = date('m/d/Y', strtotime($toDate));
                $docNumber = "Payroll-{$payPeriod}#{$count}";
                $description = "Payroll for pay period {$pay_period_from} to {$pay_period_to}";
                $totalAmount = $payroll->net_pay;
                $amount = 0;

                $reimbursementData = ApprovalsAndRequestLock::where(['user_id' => $payroll->user_id, 'payroll_id' => $payroll->payroll_id, 'pay_period_from' => $fromDate, 'pay_period_to' => $toDate, 'adjustment_type_id' => 2])->sum('amount');

                $reimbursementAmount = $reimbursementData ?? 0;

                if ($payroll->pay_type == 'Manualy') {
                    $amount = $this->getMarkAsPaidAmount($payroll) ?? 0;
                    $manualAmount = $payroll->net_pay + $amount - $reimbursementAmount;
                    if ($manualAmount > 0) {
                        $lineItems[] = [
                            'Description' => "Paid Externally - {$userName}",
                            'DetailType' => 'JournalEntryLineDetail',
                            'Id' => (string) $i++,
                            'Amount' => $payroll->net_pay + $amount - $reimbursementAmount,
                            'JournalEntryLineDetail' => [
                                'PostingType' => 'Credit',
                                'AccountRef' => [
                                    'value' => $this->getAccountId('External Payment Account'), // Use correct account ID
                                    'name' => 'External Payment Account',
                                ],
                            ],
                        ];
                    }

                } else {
                    $netPayAmt = $payroll->net_pay - $reimbursementAmount;
                    if ($netPayAmt > 0) {
                        $lineItems[] = [
                            'Description' => "Net Pay - {$userName}",
                            'DetailType' => 'JournalEntryLineDetail',
                            'Id' => (string) $i++,
                            'Amount' => $netPayAmt,
                            'JournalEntryLineDetail' => [
                                'PostingType' => 'Credit',
                                'AccountRef' => [
                                    'value' => $this->getAccountId('Bank Account'), // Use correct account ID
                                    'name' => 'Bank Account',
                                ],
                            ],
                        ];
                    }
                }

                // if($reimbursementAmount > 0){
                $lineItems[] = [
                    'Description' => "Reimbursement Paid - {$userName}",
                    'DetailType' => 'JournalEntryLineDetail',
                    'Id' => (string) $i++,
                    'Amount' => $reimbursementAmount,
                    'JournalEntryLineDetail' => [
                        'PostingType' => 'Credit',
                        'AccountRef' => [
                            'value' => $this->getAccountId('Reimbursement Account'), // Use correct account ID
                            'name' => 'Reimbursement Account',
                        ],
                    ],
                ];
                // }

                if ($payroll->workertype->worker_type === 'w2' && $payroll->pay_type == 'Bank') {

                    $state_tax = 0.0;
                    $federal_tax = 0.0;
                    $totalTaxes = 0;
                    $taxData = W2PayrollTaxDeduction::Select('state_income_tax', 'federal_income_tax')->where(['payment_id' => $payroll->everee_paymentId])->first();

                    if (! empty($taxData)) {
                        $totalTaxes = $taxData->state_income_tax + $taxData->federal_income_tax;
                        $totalAmount += $totalTaxes;
                    }

                    $lineItems[] = [
                        'DetailType' => 'JournalEntryLineDetail',
                        'Description' => "Employer Payroll Taxes - {$userName}",
                        'Id' => (string) $i++,
                        'Amount' => $totalTaxes,
                        'JournalEntryLineDetail' => [
                            'PostingType' => 'Credit',
                            'AccountRef' => [
                                'value' => $this->getAccountId('Taxes Account'),
                                'name' => 'Taxes Account',
                            ],
                            'TaxCodeRef' => [
                                'value' => 'TAX', // Tax Code ID
                            ],
                        ],
                    ];

                }
            }

            $debitAmount = $totalAmount + $amount;
            if ($debitAmount > 0) {
                $lineItems[] = [
                    'Description' => $description,
                    'Amount' => $totalAmount + $amount,
                    'DetailType' => 'JournalEntryLineDetail',
                    'Id' => '0',
                    'JournalEntryLineDetail' => [
                        'PostingType' => 'Debit',
                        'AccountRef' => [
                            'value' => $this->getAccountId('Payroll Expense Account'),
                            'name' => 'Payroll Expense Account',
                        ],
                    ],
                ];
            }

            // }

            if (empty($lineItems)) {
                createLogFile('quickbooks', '['.now()." ] No valid journal entry lines. Aborting API call to QuickBooks. It has negative or Zero amount payroll_history or one_time_payments id: {$payroll->id}");

                return;
            }
            $journalEntry = [
                'Line' => $lineItems,
                'DocNumber' => $docNumber,
                'TxnDate' => now()->format('Y-m-d'),
            ];

            // Create the journal entry
            try {
                createLogFile('quickbooks', '['.now().' ] Journal entry creation data : '.json_encode($journalEntry));

                $journalEntryData = JournalEntry::create($journalEntry);
                // Save the journal entry
                try {
                    $result = $dataService->Add($journalEntryData);
                    $error = $dataService->getLastError();
                    if ($error) {
                        createLogFile('quickbooks', '['.now().' ] Journal entry creation Exception : '.$error->getOAuthHelperError().'-'.$error->getResponseBody());
                    }
                } catch (Exception $e) {

                    createLogFile('quickbooks', '['.now().' ] Journal entry Exception : '.$e);
                }

                // log data inside transaction log table
                $logData = ['api_name' => 'create_journal_entry',
                    'payload' => json_encode($journalEntry),
                    'response' => json_encode($result),
                    'api_url' => 'create_journal_entry'];

                evereeTransectionLog::create($logData);
                if (empty($result)) {
                    return response()->json(['message' => 'There is some issue while creating journal entry. Please try again later.']);
                }

                if ($type == 'one_time_payment') {
                    OneTimePayments::where(['payment_status' => 3, 'everee_payment_status' => 1, 'everee_payment_req_id' => $payroll->everee_payment_req_id])->update(['quickbooks_journal_entry_id' => $result->Id]);
                } else {
                    PayrollHistory::where(['id' => $payroll->id, 'payroll_id' => $payroll->payroll_id, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->update(['quickbooks_journal_entry_id' => $result->Id]); //
                }

                $resultData[$payroll->id] = $result->Id;

            } catch (Exception $e) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
        }

        return response()->json(['message' => 'Journal Entry Created', 'data' => $resultData]);

    }

    /**
     * Function to get account id by name and id
     *
     * @param  int|string  $accountParam
     * @return $accountdata
     */
    public function getAccountId($accountParam)
    {
        if (empty($accountParam)) {
            return false;
        }

        $quickbookSettings = $this->getQuickbooksSettings();
        $accounts = $quickbookSettings->accounts ?? null;

        if (empty($accounts)) {
            // log data inside transaction log table
            $logData = ['api_name' => 'create_journal_entry',
                'payload' => json_encode($accountParam),
                'response' => json_encode($quickbookSettings),
                'api_url' => 'create_journal_entry'];
            evereeTransectionLog::create($logData);
            createLogFile('quickbooks', '['.now()." ] Empty Accounts- Account Id {$accountParam} not found in settings");

            return response()->json('Accounts not found in settings', 200);
        }

        $accountdata = is_numeric($accountParam) ? collect($accounts)
            ->firstWhere('account_id', $accountParam)?->account_name : collect($accounts)
            ->firstWhere('account_name', $accountParam)?->account_id;

        if (empty($accountdata)) {
            // log data inside transaction log table
            $logData = ['api_name' => 'create_journal_entry',
                'payload' => json_encode($accountParam),
                'response' => json_encode($quickbookSettings),
                'api_url' => 'create_journal_entry'];
            evereeTransectionLog::create($logData);
            createLogFile('quickbooks', '['.now()." ] Account Id {$accountParam} not found in settings");

            return response()->json('Accounts Id not found in settings', 200);
        }

        return $accountdata ?: null;

    }

    public function getMarkAsPaidAmount($payrollData)
    {
        if (empty($payrollData)) {
            $amount = 0;

            return $amount;
        }

        $userId = $payrollData->user_id;
        $payroll_id = $payrollData->payroll_id;
        $pay_period_from = $payrollData->pay_period_from;
        $pay_period_to = $payrollData->pay_period_to;

        // Get mark as paid data from all tables.
        $commission = UserCommissionLock::where(['user_id' => $userId, 'payroll_id' => $payroll_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_mark_paid' => '1'])->sum('amount');
        $override = UserOverridesLock::where(['user_id' => $userId, 'payroll_id' => $payroll_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_mark_paid' => '1'])->sum('amount');
        $clawback = ClawbackSettlementLock::where(['user_id' => $userId, 'payroll_id' => $payroll_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_mark_paid' => '1'])->sum('clawback_amount');
        $adjustment = PayrollAdjustmentLock::where(['user_id' => $userId, 'payroll_id' => $payroll_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to,  'is_mark_paid' => '1'])->sum('commission_amount');
        $approvalAndRequest = ApprovalsAndRequestLock::where(['user_id' => $userId, 'payroll_id' => $payroll_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_mark_paid' => '1'])->sum('amount');
        $payrollDeductions = PayrollDeductionLock::where(['user_id' => $userId, 'payroll_id' => $payroll_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_mark_paid' => '1'])->sum('amount');
        $adjustmentDetail = PayrollAdjustmentDetail::where(['user_id' => $userId, 'payroll_id' => $payroll_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_mark_paid' => '1'])->sum('amount');
        $payrollHourlySalary = PayrollHourlySalaryLock::where(['user_id' => $userId, 'payroll_id' => $payroll_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_mark_paid' => '1'])->sum('salary');
        $payrollOvertime = PayrollOvertimeLock::where(['user_id' => $userId, 'payroll_id' => $payroll_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'is_mark_paid' => '1'])->sum('total');

        $markAsPaidAmount = $commission + $override + $clawback + $adjustment + $approvalAndRequest + $payrollDeductions + $adjustmentDetail + $payrollHourlySalary + $payrollOvertime;

        return $markAsPaidAmount;
    }
}
