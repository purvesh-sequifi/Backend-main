<?php

namespace App\Http\Controllers\API\QuickBooks;

use App\Http\Controllers\Controller;
use App\Jobs\CreateJournalEntryJob;
use App\Models\Crms;
use App\Models\CrmSetting;
use App\Models\evereeTransectionLog;
use App\Services\QuickBooksService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Exception\IdsException;
use QuickBooksOnline\API\Exception\SdkException;
use QuickBooksOnline\API\Facades\Account;

/**
 * QuickBooksController
 *
 * This controller manages the integration between the application and QuickBooks Online.
 * It handles authentication (OAuth), token management, account creation, journal entry creation,
 * retrieval of journal reports, and error/logging strategies for QuickBooks API interactions.
 *
 * Responsibilities include:
 * - Connecting and authenticating with QuickBooks
 * - Handling OAuth callback and token refresh
 * - Creating and managing QuickBooks accounts
 * - Creating and retrieving journal entries and reports
 * - Logging and error handling for all QuickBooks-related operations
 */
class QuickBooksController extends Controller
{
    protected $quickBooksService;

    protected $quickbookSettings;

    protected $dataService;

    protected $clientId;

    protected $clientSecret;

    /**
     * Class constructor
     *
     * Handles the service initialization and environment variables retrieval.
     * If the service returns a JsonResponse (like 401), stop further execution.
     */
    public function __construct(QuickBooksService $quickBooksService)
    {
        $this->quickBooksService = $quickBooksService;
        $this->quickbookSettings = $this->quickBooksService->getQuickbooksSettings();
        $this->clientId = openssl_decrypt(config('services.quickbooks.client_id'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
        $this->clientSecret = openssl_decrypt(config('services.quickbooks.client_secret'), config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));

        // If the service returns a JsonResponse (like 401), stop further execution
        if ($this->quickbookSettings instanceof \Illuminate\Http\JsonResponse) {
            response()->json($this->quickbookSettings->getData(), $this->quickbookSettings->getStatusCode())->send();
            exit; // Stops further execution
        }

        $this->dataService = DataService::Configure([
            'auth_mode' => 'OAuth2',
            'ClientID' => $this->clientId,
            'ClientSecret' => $this->clientSecret,
            'scope' => config('services.quickbooks.oauth_scope'),
            'RedirectURI' => config('services.quickbooks.redirect_uri'),
            'baseUrl' => config('services.quickbooks.base_url'), // Or 'production' if needed
            'state' => config('services.quickbooks.api_url'),
        ]);

        // $this->dataService->setLogLocation(storage_path('logs/quickbooks.log'));
    }

    /**
     * Function to get the authorization URL using connect button
     */
    public function connect(): JsonResponse
    {

        $OAuth2LoginHelper = $this->dataService->getOAuth2LoginHelper();

        $authorizationUrl = $OAuth2LoginHelper->getAuthorizationCodeURL();

        return response()->json(['message' => 'Quickbooks Authorization Url', 'data' => $authorizationUrl], 200);
    }

    /**
     * Handle the callback from QuickBooks OAuth flow, and insert the realmId and authCode in crm_settings.
     * Also adds necessary accounts to QuickBooks required for QuickBooks journal entry.
     */
    public function handle(Request $request): JsonResponse
    {
        $authCode = $request->input('code');
        $realmId = $request->input('realmId');
        if (empty($authCode) && empty($realmId)) {
            return response()->json(['message' => 'QuickBook Authentication code and Realm Id is missing in the request'], 400);
        }

        $OAuth2LoginHelper = $this->dataService->getOAuth2LoginHelper();
        $accessTokenObj = $OAuth2LoginHelper->exchangeAuthorizationCodeForToken($authCode, $realmId);

        $this->dataService->updateOAuth2Token($accessTokenObj);

        $quickbookSettings = $this->quickbookSettings;
        $quickbookSettings->authCode = $authCode;
        $quickbookSettings->realmId = $realmId;
        $quickbookSettings->refreshToken = $accessTokenObj->getRefreshToken();
        $quickbookSettings->accessToken = $accessTokenObj->getAccessToken();

        // Update realmId and authCode in crm_settings
        $crmSettings = CrmSetting::where('id', $quickbookSettings->id)->update(['value' => json_encode($quickbookSettings), 'updated_at' => now()]);

        // Create QuickBooks default accounts, which we use for journal entry
        $accounts = $this->createQuickbookAccounts();

        if ($crmSettings && $accounts) {
            Crms::Where(['name' => 'QuickBooks'])->update(['status' => 1]);
            $message = 'Successfully connected to QuickBooks!';
            $status = 'success';

            $url = rtrim(config('app.frontend_base_url', config('app.url')), '/').'/integration/quickbooks-callback?status='.$status.'&message='.urlencode($message);

            return Redirect::away($url);
        } else {
            $message = 'Error connecting to QuickBooks!';
            $status = 'error';
            $url = rtrim(config('app.frontend_base_url', config('app.url')), '/').'/integration/quickbooks-callback?status='.$status.'&message='.urlencode($message);

            return Redirect::away($url);
        }
    }

    /**
     * Refreshes the QuickBooks access token.
     */
    public function refreshToken(): JsonResponse
    {
        $tokens = $this->quickBooksService->getRefreshToken();

        return response()->json([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
        ]);
    }

    /**
     * Create Accounts in QuickBooks
     */
    public function createQuickbookAccounts(): JsonResponse
    {

        $refreshToken = $this->quickBooksService->getRefreshToken();
        createLogFile('quickbooks', '['.now().' ] refresh token generated inside create accounts: '.json_encode($refreshToken));

        // Set up QuickBooks DataService
        $dataService = $this->quickBooksService->getDataService();
        $quickbookSettings = $this->quickBooksService->getQuickbooksSettings();

        $quickbookAccounts = config('quickbooks_accounts_config');
        unset($quickbookSettings->accounts);

        createLogFile('quickbooks', '['.now().' ] Accounts Reset inside the createQuickbookAccounts ');

        try {
            // Loop through each account and create it
            $createdAccounts = [];
            $accounts = [];
            $count = 0;
            $settingId = $quickbookSettings->id;
            foreach ($quickbookAccounts as $accountData) {
                $account = Account::create($accountData);

                // Add the account to QuickBooks
                $result = $dataService->add($account);

                // Collect created accounts details
                if ($result) {
                    createLogFile('quickbooks', '['.now().' ] Created quickbooks account details: '.json_encode($result));

                    $accounts[$count]['account_id'] = $result->Id;
                    $accounts[$count]['account_name'] = $result->Name;

                    $createdAccounts[] = $result;
                } else {
                    $accountName = $accountData['Name'];
                    $query = "SELECT * FROM Account WHERE Name = '$accountName'";
                    $accountDetails = $this->quickBooksService->getdataService()->Query($query);
                    createLogFile('quickbooks', '['.now().' ] existing quickbooks account details: '.json_encode($accountDetails));

                    if ($accountDetails) {
                        $accounts[$count]['account_id'] = $accountDetails[0]->Id;
                        $accounts[$count]['account_name'] = $accountDetails[0]->Name;
                    }
                }
                // increment the count
                $count++;
            }

            if (! empty($accounts)) {
                unset($quickbookSettings->id);
                $quickbookSettings->accounts = $accounts;
                $crmSettings = CrmSetting::where('id', $settingId)->update(['value' => json_encode($quickbookSettings), 'updated_at' => now()]);

            }

            $data = ['newly_created_accounts' => $createdAccounts,
                'existing_accounts' => $accounts];

            // log data inside transaction log table
            $logData = ['api_name' => 'create_quickbook_accounts',
                'payload' => json_encode($quickbookAccounts),
                'response' => json_encode($data),
                'api_url' => 'create_quickbook_account'];

            evereeTransectionLog::create($logData);

            return response()->json([
                'message' => 'Accounts created successfully',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }

    }

    /**
     * Create Journal Entry to QuickBooks
     */
    public function createJournalEntry(request $request): JsonResponse
    {
        $request->validate([
            'pay_period_from' => 'required|date',
            'pay_period_to' => 'required|date|after_or_equal:pay_period_from',
            'everee_payment_req_id' => 'integer',
        ]);

        CreateJournalEntryJob::dispatch(
            $request->input('pay_period_from'),
            $request->input('pay_period_to'),
            $request->input('everee_payment_req_id')
        );

        return response()->json(['message' => 'Job dispatched!']);
    }

    /**
     * Get a paginated report of journal entries by date range.
     */
    public function getJournalReport(request $request): JsonResponse
    {
        $this->quickBooksService->getRefreshToken();
        $companyInfo = $this->quickBooksService->getdataService()->getCompanyInfo();
        $companyName = $companyInfo->CompanyName;
        $validator = Validator::make($request->all(), [
            'start_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $startDate = $request->input('start_date', Carbon::today()->subDays(45)->toDateString());
        $endDate = $request->input('end_date', Carbon::today()->toDateString());
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);

        try {

            $query = "SELECT Id, TxnDate, DocNumber, Line FROM JournalEntry WHERE TxnDate >= '$startDate' AND TxnDate <= '$endDate'";

            $journalEntries = $this->quickBooksService->getdataService()->Query($query);

            if ($journalEntries) {
                $response = [];
                foreach ($journalEntries as $entry) {
                    $journalId = $entry->Id;
                    $transactionDate = $entry->TxnDate;
                    $docNumber = $entry->DocNumber ?? '-';
                    $lines = [];

                    foreach ($entry->Line as $line) {
                        $postingType = $line->JournalEntryLineDetail->PostingType ?? '-';
                        $amount = $line->Amount ?? '0';

                        $debitAmount = ($postingType == 'Debit') ? $amount : '0';
                        $creditAmount = ($postingType == 'Credit') ? $amount : '0';
                        $accountId = $line->JournalEntryLineDetail->AccountRef ?? null;

                        $accountName = $this->quickBooksService->getAccountId($accountId);

                        $lines[] = [
                            'transaction_date' => $transactionDate,
                            'transaction_type' => 'Journal Entry',
                            'num' => $docNumber,
                            'name' => $line->JournalEntryLineDetail->AccountRef->name ?? '-',
                            'line_description' => $line->Description ?? '-',
                            'full_name' => $accountName ?? '-',
                            'debit' => $debitAmount,
                            'credit' => $creditAmount,
                        ];
                    }

                    // Calculate total debit
                    $totalDebit = array_reduce($lines, function ($sum, $line) {
                        return $sum + floatval($line['debit']);
                    }, 0);

                    // Add to response array
                    $response[] = [
                        'journal_entry_id' => $journalId,
                        'total_lines' => count($lines),
                        'transactions' => $lines,
                        'total_debit' => number_format($totalDebit, 2),
                        'company_name' => $companyName,
                    ];
                }
            } else {
                $error = $this->dataService->getLastError();
                if ($error) {
                    return response()->json('Error: '.$error->getHttpStatusCode().' - '.$error->getResponseBody(), 400);

                } else {
                    return response()->json('No Journal Report Found ', 200);
                }
            }
            // Added company name to response array
            $paginatedData = $this->getPaginatedData($response, $page, $perPage);
            $paginatedArray = $paginatedData->getData(true);
            $paginatedArray['company_name'] = $companyName;

            // Returning paginated response with company name
            return response()->json($paginatedArray);

        } catch (IdsException|SdkException $e) {
            createLogFile('quickbooks', '['.now().' ] QuickBooks SDK Error: '.$e->getMessage());
        } catch (\Exception $e) {
            createLogFile('quickbooks', '['.now().' ] QuickBooks Journal Report Error: '.$e->getMessage());

            return response()->json([
                'error' => 'Failed to create QuickBooks accounts',
                'details' => $e->getMessage(),
            ], 500);

        }

        return response()->json('No Journal Report Found ', 200);
    }

    /**
     * Fetch a Journal Entry by ID from QuickBooks.
     *
     * @param  string  $journalEntryId  The ID of the Journal Entry.
     * @return \Illuminate\Http\JsonResponse The Journal Entry details, or a 404 error if not found.
     */
    public function getJournalEntryById(string $journalEntryId): JsonResponse
    {
        try {
            // Get the DataService instance
            $dataService = $this->quickBooksService->getDataService();
            $companyInfo = $dataService->getCompanyInfo();
            $companyName = $companyInfo->CompanyName;

            // Make the HTTP request
            $journalEntry = $dataService->FindById('journalentry', $journalEntryId);

            if ($journalEntry) {
                // Convert the journal entry to array for easier manipulation
                $journalEntryArray = json_decode(json_encode($journalEntry), true);

                // Process each line item to fetch account name
                if (isset($journalEntryArray['Line']) && is_array($journalEntryArray['Line'])) {
                    foreach ($journalEntryArray['Line'] as $key => $line) {
                        if (isset($line['JournalEntryLineDetail']) && isset($line['JournalEntryLineDetail']['AccountRef'])) {
                            $accountId = $line['JournalEntryLineDetail']['AccountRef'];

                            // Check if accountId is already an array with a value field
                            if (is_array($accountId) && isset($accountId['value'])) {
                                $accountIdValue = $accountId['value'];
                            } else {
                                $accountIdValue = $accountId;
                            }

                            // Fetch account details to get the name
                            try {
                                $account = $dataService->FindById('account', $accountIdValue);

                                if ($account) {
                                    // Get account name and create a full AccountRef object
                                    $accountName = 'Unknown Account';

                                    // Convert account object to array to safely access properties
                                    $accountArray = json_decode(json_encode($account), true);

                                    if (isset($accountArray['Name'])) {
                                        $accountName = $accountArray['Name'];
                                    } elseif (isset($accountArray['FullyQualifiedName'])) {
                                        $accountName = $accountArray['FullyQualifiedName'];
                                    }

                                    // Update the AccountRef with name
                                    $journalEntryArray['Line'][$key]['JournalEntryLineDetail']['AccountRef'] = [
                                        'value' => $accountIdValue,
                                        'name' => $accountName,
                                    ];
                                }
                            } catch (\Exception $e) {
                                // If we can't fetch the account, just continue with the ID
                                createLogFile('quickbooks', '['.now().'] Error fetching account details: '.$e->getMessage());
                            }
                        }
                    }
                }

                return response()->json([
                    'message' => 'Journal Entry fetched Successfully!',
                    'data' => $journalEntryArray,
                    'company_name' => $companyName,
                ]);
            }

            return response()->json('Journal Entry not found', 404);

        } catch (IdsException|SdkException $e) {
            createLogFile('quickbooks', '['.now().' ] QuickBooks SDK Error: '.$e->getMessage());
        } catch (\Exception $e) {
            createLogFile('quickbooks', '['.now().' ] QuickBooks Journal Entry Error: '.$e->getMessage());

            return response()->json([
                'error' => 'Failed to Fetch the Journal Entry',
                'details' => $e->getMessage(),
            ], 500);
        }

        return response()->json('Something went wrong, please try again later', 500);
    }

    /**
     * Create a paginated response from the given data.
     *
     * @param  array  $data  The data to be paginated.
     * @param  int  $page  The current page number.
     * @param  int  $perPage  The number of items per page.
     * @return \Illuminate\Http\JsonResponse The paginated response.
     */
    public function getPaginatedData(array $data, int $page = 1, int $perPage = 10): JsonResponse
    {
        $total = count($data); // Total items from API response

        // Step 2: Calculate the start offset for the current page
        $start = ($page - 1) * $perPage;

        // Step 3: Slice the data manually
        $paginatedData = array_slice($data, $start, $perPage);

        // Step 4: Create a LengthAwarePaginator instance
        $paginator = new LengthAwarePaginator(
            $paginatedData, // Sliced data for the current page
            $total, // Total items
            $perPage, // Items per page
            $page, // Current page
            ['path' => url()->current()] // URL path for pagination
        );

        // Step 5: Return paginated response
        return response()->json($paginator);
    }
}
