<?php

namespace App\Http\Controllers;

use App\Jobs\Sales\SaleMasterJob;
use App\Models\Integration;
use App\Models\InterigationTransactionLog;
use App\Models\LegacyApiRawDataHistory;
use App\Models\Products;
use App\Models\User;
use App\Models\UsersAdditionalEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class arcsiteController extends Controller
{
    public function handle(Request $request)
    {
        $requestData = $request->all();

        return $this->arcsite($requestData);
    }

    protected function getIntegrationData($value)
    {
        $value = openssl_decrypt(
            $value,
            config('app.encryption_cipher_algo'),
            config('app.encryption_key'),
            0,
            config('app.encryption_iv')
        );

        return json_decode($value, true);
    }

    public function arcsite($requestData, $log = true): JsonResponse
    {

        if ($log == true) {
            InterigationTransactionLog::create([
                'interigation_name' => 'arcsite',
                'api_name' => 'webhook',
                'payload' => json_encode($requestData),
                'response' => json_encode($requestData),
                'url' => null,
            ]);
        }

        if ($requestData['event'] != 'proposal.approved') {
            return response()->json([
                'ApiName' => 'arcsiteWebhook',
                'status' => false,
                'message' => 'Event not matched with proposal.approved',
                'success_sync_data' => $requestData,
            ], 400);
        }

        $integration = Integration::where(['name' => 'arcsite', 'status' => 1])->first();
        if (! $integration) {
            return response()->json([
                'ApiName' => 'arcsiteWebhook',
                'status' => false,
                'message' => 'Arcsite setting is not active',
            ], 400);
        }

        $integrationData = $this->getIntegrationData($integration->value);

        if (! $integrationData || empty($integrationData['base_url']) || empty($integrationData['api_token'])) {
            return response()->json([
                'ApiName' => 'arcsiteWebhook',
                'status' => false,
                'message' => 'Invalid Arcsite API credentials',
            ], 400);
        }

        $baseUrl = $integrationData['base_url'];
        $apiToken = $integrationData['api_token'];

        // API Headers
        $headers = [
            'Authorization: Bearer '.$apiToken,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        // Fetch Drawing Line Items
        // $drawingId = $request->data['proposal_options'][0]['drawing_id'] ?? null;
        $projectId = $requestData['data']['project_id'] ?? null;
        $drawingId = $requestData['data']['approved_option']['drawing_id'] ?? null;
        $lineItemsResponse = curlRequest("{$baseUrl}/drawings/{$drawingId}/line_items", '', $headers, 'GET');
        $lineItems = json_decode($lineItemsResponse, true);
        if ($log == true) {
            InterigationTransactionLog::create([
                'interigation_name' => 'arcsite',
                'api_name' => 'get drawings line_items',
                'payload' => json_encode(['PID' => $projectId, 'drawingsID' => $drawingId]),
                'response' => $lineItemsResponse,
                'url' => "{$baseUrl}/drawings/{$drawingId}/line_items",
            ]);
        }

        // Fetch Project Details
        $projectResponse = curlRequest("{$baseUrl}/projects/{$projectId}", '', $headers, 'GET');
        $project = json_decode($projectResponse, true);
        if ($log == true) {
            InterigationTransactionLog::create([
                'interigation_name' => 'arcsite',
                'api_name' => 'get projects',
                'payload' => json_encode(['PID' => $projectId]),
                'response' => $projectResponse,
                'url' => "{$baseUrl}/projects/{$projectId}",
            ]);
        }

        // Sales Representative Details
        $repEmail = $project['sales_rep']['email'] ?? null;
        $repUser = User::where('email', $repEmail)->first();
        if (! $repUser) {
            $repUser = null;
            $matchingUser = UsersAdditionalEmail::with('user')->where('email', $repEmail)->first();
            if ($matchingUser) {
                $repUser = $matchingUser->user;
            }
        }

        if (! $repUser) {
            $import_to_sales = 2;
        } else {
            $import_to_sales = 0;
        }

        // Get Product Details
        $product = Products::withTrashed()->where('product_id', null)->first()
            ?? Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();

        $salesData = [
            'pid' => $projectId,
            'customer_name' => $project['customer']['name'] ?? null,
            'customer_email' => $project['customer']['email'] ?? null,
            'customer_phone' => $project['customer']['phone'] ?? null,
            'customer_address' => $project['customer']['address']['street'] ?? null,
            'customer_state' => $project['customer']['address']['state'] ?? null,
            'customer_city' => $project['customer']['address']['city'] ?? null,
            'customer_zip' => $project['customer']['address']['zip_code'] ?? null,
            'adders_description' => isset($project['tags']) ? implode(',', $project['tags']) : null,
            // 'gross_account_value' => ($lineItems['subtotal'] ?? 0) + ($lineItems['markup'] ?? 0) - ($lineItems['discount'] ?? 0),
            'gross_account_value' => ($lineItems['markup'] ?? 0) - (abs($lineItems['discount']) ?? 0),
            'total_amount_in_period' => ($lineItems['total'] ?? 0),
            'sales_rep_email' => $repEmail,
            'closer1_id' => $repUser->id ?? null,
            'customer_signoff' => now()->toDateString(),
            'trigger_date' => json_encode([]),
            'data_source_type' => 'Salt Lake City',
            'product_id' => $product->id ?? null,
            'product_code' => $product->product_id ?? null,
            'import_to_sales' => $import_to_sales,
        ];

        // Create history record
        LegacyApiRawDataHistory::create($salesData);

        // Dispatch Job for Background Processing
        dispatch(new SaleMasterJob('Salt Lake City', 100, 'sales-process'))->onQueue('sales-process');

        return response()->json([
            'ApiName' => 'arcsiteWebhook',
            'status' => true,
            'message' => 'Request sent successfully. Sequifi Data syncing in background',
            'success_sync_data' => $requestData,
        ], 200);
    }

    public function arcsiteLasVegas(Request $request, $log = true): JsonResponse
    {
        try {
            $requestData = $request->all();

            if ($log == true) {
                InterigationTransactionLog::create([
                    'interigation_name' => 'arcsiteLasVegasWebhook',
                    'api_name' => 'webhook',
                    'payload' => json_encode($requestData),
                    'response' => json_encode($requestData),
                    'url' => null,
                ]);
            }

            if ($requestData['event'] != 'proposal.approved') {
                return response()->json([
                    'ApiName' => 'arcsiteLasVegasWebhook',
                    'status' => false,
                    'message' => 'Event not matched with proposal.approved',
                    'success_sync_data' => $requestData,
                ], 400);
            }

            $integration = Integration::where(['description' => 'arcsite Las Vegas', 'status' => 1])->first();
            if (! $integration) {
                return response()->json([
                    'ApiName' => 'arcsiteLasVegasWebhook',
                    'status' => false,
                    'message' => 'Arcsite setting is not active',
                ], 400);
            }

            $integrationData = $this->getIntegrationData($integration->value);

            if (! $integrationData || empty($integrationData['base_url']) || empty($integrationData['api_token'])) {
                return response()->json([
                    'ApiName' => 'arcsiteLasVegasWebhook',
                    'status' => false,
                    'message' => 'Invalid Arcsite API credentials',
                ], 400);
            }

            $baseUrl = $integrationData['base_url'];
            $apiToken = $integrationData['api_token'];

            // API Headers
            $headers = [
                'Authorization: Bearer '.$apiToken,
                'Accept: application/json',
                'Content-Type: application/json',
            ];

            // Fetch Drawing Line Items
            // $drawingId = $request->data['proposal_options'][0]['drawing_id'] ?? null;
            $projectId = $requestData['data']['project_id'] ?? null;
            $drawingId = $requestData['data']['approved_option']['drawing_id'] ?? null;
            $lineItemsResponse = curlRequest("{$baseUrl}/drawings/{$drawingId}/line_items", '', $headers, 'GET');
            $lineItems = json_decode($lineItemsResponse, true);
            if ($log == true) {
                InterigationTransactionLog::create([
                    'interigation_name' => 'arcsiteLasVegasWebhook',
                    'api_name' => 'get drawings line_items',
                    'payload' => json_encode(['PID' => $projectId, 'drawingsID' => $drawingId]),
                    'response' => $lineItemsResponse,
                    'url' => "{$baseUrl}/drawings/{$drawingId}/line_items",
                ]);
            }

            // Fetch Project Details
            $projectResponse = curlRequest("{$baseUrl}/projects/{$projectId}", '', $headers, 'GET');
            $project = json_decode($projectResponse, true);
            if ($log == true) {
                InterigationTransactionLog::create([
                    'interigation_name' => 'arcsiteLasVegasWebhook',
                    'api_name' => 'get projects',
                    'payload' => json_encode(['PID' => $projectId]),
                    'response' => $projectResponse,
                    'url' => "{$baseUrl}/projects/{$projectId}",
                ]);
            }

            // Sales Representative Details
            $repEmail = $project['sales_rep']['email'] ?? null;
            $repUser = User::where('email', $repEmail)->first();
            if (! $repUser) {
                $repUser = null;
                $matchingUser = UsersAdditionalEmail::with('user')->where('email', $repEmail)->first();
                if ($matchingUser) {
                    $repUser = $matchingUser->user;
                }
            }

            try {

                if (! $repUser) {
                    $import_to_sales = 2;
                } else {
                    $import_to_sales = 0;
                }

                // Get Product Details
                $product = Products::withTrashed()->where('product_id', null)->first()
                    ?? Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();

                $salesData = [
                    'pid' => $projectId,
                    'legacy_id' => 'arcsiteLasVegas',
                    'customer_name' => $project['customer']['name'] ?? null,
                    'customer_email' => $project['customer']['email'] ?? null,
                    'customer_phone' => $project['customer']['phone'] ?? null,
                    'customer_address' => $project['customer']['address']['street'] ?? null,
                    'customer_state' => $project['customer']['address']['state'] ?? null,
                    'customer_city' => $project['customer']['address']['city'] ?? null,
                    'customer_zip' => $project['customer']['address']['zip_code'] ?? null,
                    'adders_description' => isset($project['tags']) ? implode(',', $project['tags']) : null,
                    // 'gross_account_value' => ($lineItems['subtotal'] ?? 0) + ($lineItems['markup'] ?? 0) - ($lineItems['discount'] ?? 0),
                    'gross_account_value' => ($lineItems['markup'] ?? 0) - (abs($lineItems['discount']) ?? 0),
                    'total_amount_in_period' => ($lineItems['total'] ?? 0),
                    'sales_rep_email' => $repEmail,
                    'closer1_id' => $repUser->id ?? null,
                    'customer_signoff' => now()->toDateString(),
                    'trigger_date' => json_encode([]),
                    'data_source_type' => 'Las Vegas',
                    'product_id' => $product->id ?? null,
                    'product_code' => $product->product_id ?? null,
                    'import_to_sales' => $import_to_sales,
                ];

                // Create history record
                LegacyApiRawDataHistory::create($salesData);

                // Dispatch Job for Background Processing
                dispatch(new SaleMasterJob('Las Vegas', 100, 'sales-process'))->onQueue('sales-process');

            } catch (\Exception $e) {

                InterigationTransactionLog::create([
                    'interigation_name' => 'arcsiteLasVegasWebhookError',
                    'api_name' => 'arcsiteLasVegasWebhookError',
                    'payload' => json_encode(''),
                    'response' => json_encode([
                        'error' => 'Arcsite Las Vegas LegacyApiRawDataHistory Data Processing Error: '.$e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]),
                    'url' => '',
                ]);

                \Log::error('Arcsite Las Vegas Data Processing Error: '.$e->getMessage(), [
                    'salesData' => $salesData,
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                return response()->json([
                    'ApiName' => 'arcsiteLasVegasWebhook',
                    'status' => false,
                    'message' => 'Error saving or processing sales data: '.$e->getMessage(),
                ], 500);
            }

            return response()->json([
                'ApiName' => 'arcsiteLasVegasWebhook',
                'status' => true,
                'message' => 'Request sent successfully. Sequifi Data syncing in background',
                'success_sync_data' => $requestData,
            ], 200);
        } catch (\Exception $e) {

            InterigationTransactionLog::create([
                'interigation_name' => 'arcsiteLasVegasWebhookError',
                'api_name' => 'arcsiteLasVegasWebhookError',
                'payload' => json_encode(''),
                'response' => json_encode([
                    'error' => 'Arcsite Las Vegas Webhook Critical Error: '.$e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]),
                'url' => '',
            ]);

            \Log::error('Arcsite Las Vegas Webhook Critical Error: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ApiName' => 'arcsiteLasVegasWebhook',
                'status' => false,
                'message' => 'An error occurred while processing the webhook: '.$e->getMessage(),
            ], 500);
        }
    }

    public function arcsiteStGeorge(Request $request, $log = true): JsonResponse
    {
        try {

            $requestData = $request->all();

            if ($log == true) {
                InterigationTransactionLog::create([
                    'interigation_name' => 'arcsiteStGeorgeWebhook',
                    'api_name' => 'webhook',
                    'payload' => json_encode($requestData),
                    'response' => json_encode($requestData),
                    'url' => null,
                ]);
            }

            if ($requestData['event'] != 'proposal.approved') {
                return response()->json([
                    'ApiName' => 'arcsiteStGeorgeWebhook',
                    'status' => false,
                    'message' => 'Event not matched with proposal.approved',
                    'success_sync_data' => $requestData,
                ], 400);
            }

            $integration = Integration::where(['description' => 'arcsite  St. George', 'status' => 1])->first();
            if (! $integration) {
                return response()->json([
                    'ApiName' => 'arcsiteStGeorgeWebhook',
                    'status' => false,
                    'message' => 'Arcsite setting is not active',
                ], 400);
            }

            $integrationData = $this->getIntegrationData($integration->value);

            if (! $integrationData || empty($integrationData['base_url']) || empty($integrationData['api_token'])) {
                return response()->json([
                    'ApiName' => 'arcsiteStGeorgeWebhook',
                    'status' => false,
                    'message' => 'Invalid Arcsite API credentials',
                ], 400);
            }

            $baseUrl = $integrationData['base_url'];
            $apiToken = $integrationData['api_token'];

            // API Headers
            $headers = [
                'Authorization: Bearer '.$apiToken,
                'Accept: application/json',
                'Content-Type: application/json',
            ];

            // Fetch Drawing Line Items
            // $drawingId = $request->data['proposal_options'][0]['drawing_id'] ?? null;
            $projectId = $requestData['data']['project_id'] ?? null;
            $drawingId = $requestData['data']['approved_option']['drawing_id'] ?? null;
            $lineItemsResponse = curlRequest("{$baseUrl}/drawings/{$drawingId}/line_items", '', $headers, 'GET');
            $lineItems = json_decode($lineItemsResponse, true);
            if ($log == true) {
                InterigationTransactionLog::create([
                    'interigation_name' => 'arcsiteStGeorgeWebhook',
                    'api_name' => 'get drawings line_items',
                    'payload' => json_encode(['PID' => $projectId, 'drawingsID' => $drawingId]),
                    'response' => $lineItemsResponse,
                    'url' => "{$baseUrl}/drawings/{$drawingId}/line_items",
                ]);
            }

            // Fetch Project Details
            $projectResponse = curlRequest("{$baseUrl}/projects/{$projectId}", '', $headers, 'GET');
            $project = json_decode($projectResponse, true);
            if ($log == true) {
                InterigationTransactionLog::create([
                    'interigation_name' => 'arcsiteStGeorgeWebhook',
                    'api_name' => 'get projects',
                    'payload' => json_encode(['PID' => $projectId]),
                    'response' => $projectResponse,
                    'url' => "{$baseUrl}/projects/{$projectId}",
                ]);
            }

            // Sales Representative Details
            $repEmail = $project['sales_rep']['email'] ?? null;
            $repUser = User::where('email', $repEmail)->first();
            if (! $repUser) {
                $repUser = null;
                $matchingUser = UsersAdditionalEmail::with('user')->where('email', $repEmail)->first();
                if ($matchingUser) {
                    $repUser = $matchingUser->user;
                }
            }

            try {

                if (! $repUser) {
                    $import_to_sales = 2;
                } else {
                    $import_to_sales = 0;
                }

                // Get Product Details
                $product = Products::withTrashed()->where('product_id', null)->first()
                    ?? Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();

                $salesData = [
                    'pid' => $projectId,
                    'legacy_id' => 'arcsiteStGeorge',
                    'customer_name' => $project['customer']['name'] ?? null,
                    'customer_email' => $project['customer']['email'] ?? null,
                    'customer_phone' => $project['customer']['phone'] ?? null,
                    'customer_address' => $project['customer']['address']['street'] ?? null,
                    'customer_state' => $project['customer']['address']['state'] ?? null,
                    'customer_city' => $project['customer']['address']['city'] ?? null,
                    'customer_zip' => $project['customer']['address']['zip_code'] ?? null,
                    'adders_description' => isset($project['tags']) ? implode(',', $project['tags']) : null,
                    // 'gross_account_value' => ($lineItems['subtotal'] ?? 0) + ($lineItems['markup'] ?? 0) - ($lineItems['discount'] ?? 0),
                    'gross_account_value' => ($lineItems['markup'] ?? 0) - (abs($lineItems['discount']) ?? 0),
                    'total_amount_in_period' => ($lineItems['total'] ?? 0),
                    'sales_rep_email' => $repEmail,
                    'closer1_id' => $repUser->id ?? null,
                    'customer_signoff' => now()->toDateString(),
                    'trigger_date' => json_encode([]),
                    'data_source_type' => 'St. Geroge',
                    'product_id' => $product->id ?? null,
                    'product_code' => $product->product_id ?? null,
                    'import_to_sales' => $import_to_sales,
                ];

                // Create history record
                LegacyApiRawDataHistory::create($salesData);

                // Dispatch Job for Background Processing
                dispatch(new SaleMasterJob('St. Geroge', 100, 'sales-process'))->onQueue('sales-process');

            } catch (\Exception $e) {

                InterigationTransactionLog::create([
                    'interigation_name' => 'arcsiteStGeorgeWebhookError',
                    'api_name' => 'arcsiteStGeorgeWebhookError',
                    'payload' => json_encode(''),
                    'response' => json_encode([
                        'error' => 'Arcsite St. George LegacyApiRawDataHistory Data Processing Error: '.$e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]),
                    'url' => '',
                ]);

                \Log::error('Arcsite St. George Data Processing Error: '.$e->getMessage(), [
                    'salesData' => $salesData,
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                return response()->json([
                    'ApiName' => 'arcsiteStGeorgeWebhook',
                    'status' => false,
                    'message' => 'Error saving or processing sales data: '.$e->getMessage(),
                ], 500);
            }

            return response()->json([
                'ApiName' => 'arcsiteStGeorgeWebhook',
                'status' => true,
                'message' => 'Request sent successfully. Sequifi Data syncing in background',
                'success_sync_data' => $requestData,
            ], 200);

        } catch (\Exception $e) {

            InterigationTransactionLog::create([
                'interigation_name' => 'arcsiteStGeorgeWebhookError',
                'api_name' => 'arcsiteStGeorgeWebhookError',
                'payload' => json_encode(''),
                'response' => json_encode([
                    'error' => 'Arcsite St. George Webhook Critical Error: '.$e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]),
                'url' => '',
            ]);

            \Log::error('Arcsite St. George Webhook Critical Error: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ApiName' => 'arcsiteStGeorgeWebhook',
                'status' => false,
                'message' => 'An error occurred while processing the webhook: '.$e->getMessage(),
            ], 500);
        }
    }

    public function arcsiteDenver(Request $request, $log = true): JsonResponse
    {
        try {
            $requestData = $request->all();

            if ($log == true) {
                InterigationTransactionLog::create([
                    'interigation_name' => 'arcsiteDenverWebhook',
                    'api_name' => 'webhook',
                    'payload' => json_encode($requestData),
                    'response' => json_encode($requestData),
                    'url' => null,
                ]);
            }

            if ($requestData['event'] != 'proposal.approved') {
                return response()->json([
                    'ApiName' => 'arcsiteDenverWebhook',
                    'status' => false,
                    'message' => 'Event not matched with proposal.approved',
                    'success_sync_data' => $requestData,
                ], 400);
            }

            $integration = Integration::where(['description' => 'Denver', 'status' => 1])->first();
            if (! $integration) {
                return response()->json([
                    'ApiName' => 'arcsiteDenverWebhook',
                    'status' => false,
                    'message' => 'Arcsite setting is not active',
                ], 400);
            }

            $integrationData = $this->getIntegrationData($integration->value);

            if (! $integrationData || empty($integrationData['base_url']) || empty($integrationData['api_token'])) {
                return response()->json([
                    'ApiName' => 'arcsiteDenverWebhook',
                    'status' => false,
                    'message' => 'Invalid Arcsite API credentials',
                ], 400);
            }

            $baseUrl = $integrationData['base_url'];
            $apiToken = $integrationData['api_token'];

            // API Headers
            $headers = [
                'Authorization: Bearer '.$apiToken,
                'Accept: application/json',
                'Content-Type: application/json',
            ];

            // Fetch Drawing Line Items
            // $drawingId = $request->data['proposal_options'][0]['drawing_id'] ?? null;
            $projectId = $requestData['data']['project_id'] ?? null;
            $drawingId = $requestData['data']['approved_option']['drawing_id'] ?? null;
            $lineItemsResponse = curlRequest("{$baseUrl}/drawings/{$drawingId}/line_items", '', $headers, 'GET');
            $lineItems = json_decode($lineItemsResponse, true);
            if ($log == true) {
                InterigationTransactionLog::create([
                    'interigation_name' => 'arcsiteDenverWebhook',
                    'api_name' => 'get drawings line_items',
                    'payload' => json_encode(['PID' => $projectId, 'drawingsID' => $drawingId]),
                    'response' => $lineItemsResponse,
                    'url' => "{$baseUrl}/drawings/{$drawingId}/line_items",
                ]);
            }

            // Fetch Project Details
            $projectResponse = curlRequest("{$baseUrl}/projects/{$projectId}", '', $headers, 'GET');
            $project = json_decode($projectResponse, true);
            if ($log == true) {
                InterigationTransactionLog::create([
                    'interigation_name' => 'arcsiteDenverWebhook',
                    'api_name' => 'get projects',
                    'payload' => json_encode(['PID' => $projectId]),
                    'response' => $projectResponse,
                    'url' => "{$baseUrl}/projects/{$projectId}",
                ]);
            }

            // Sales Representative Details
            $repEmail = $project['sales_rep']['email'] ?? null;
            $repUser = User::where('email', $repEmail)->first();
            if (! $repUser) {
                $repUser = null;
                $matchingUser = UsersAdditionalEmail::with('user')->where('email', $repEmail)->first();
                if ($matchingUser) {
                    $repUser = $matchingUser->user;
                }
            }

            try {
                if (! $repUser) {
                    $import_to_sales = 2;
                } else {
                    $import_to_sales = 0;
                }

                // Get Product Details
                $product = Products::withTrashed()->where('product_id', null)->first()
                    ?? Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();

                $salesData = [
                    'pid' => $projectId,
                    'legacy_id' => 'arcsiteDenver',
                    'customer_name' => $project['customer']['name'] ?? null,
                    'customer_email' => $project['customer']['email'] ?? null,
                    'customer_phone' => $project['customer']['phone'] ?? null,
                    'customer_address' => $project['customer']['address']['street'] ?? null,
                    'customer_state' => $project['customer']['address']['state'] ?? null,
                    'customer_city' => $project['customer']['address']['city'] ?? null,
                    'customer_zip' => $project['customer']['address']['zip_code'] ?? null,
                    'adders_description' => isset($project['tags']) ? implode(',', $project['tags']) : null,
                    // 'gross_account_value' => ($lineItems['subtotal'] ?? 0) + ($lineItems['markup'] ?? 0) - ($lineItems['discount'] ?? 0),
                    'gross_account_value' => ($lineItems['markup'] ?? 0) - (abs($lineItems['discount']) ?? 0),
                    'total_amount_in_period' => ($lineItems['total'] ?? 0),
                    'sales_rep_email' => $repEmail,
                    'closer1_id' => $repUser->id ?? null,
                    'customer_signoff' => now()->toDateString(),
                    'trigger_date' => json_encode([]),
                    'data_source_type' => 'Denver',
                    'product_id' => $product->id ?? null,
                    'product_code' => $product->product_id ?? null,
                    'import_to_sales' => $import_to_sales,
                ];

                // Create history record
                LegacyApiRawDataHistory::create($salesData);

                // Dispatch Job for Background Processing
                dispatch(new SaleMasterJob('Denver', 100, 'sales-process'))->onQueue('sales-process');

            } catch (\Exception $e) {
                InterigationTransactionLog::create([
                    'interigation_name' => 'arcsiteDenverWebhookError',
                    'api_name' => 'arcsiteDenverWebhookError',
                    'payload' => json_encode(''),
                    'response' => json_encode([
                        'error' => 'Arcsite Denver LegacyApiRawDataHistory Data Processing Error: '.$e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]),
                    'url' => '',
                ]);

                \Log::error('Arcsite Denver Data Processing Error: '.$e->getMessage(), [
                    'salesData' => $salesData,
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                return response()->json([
                    'ApiName' => 'arcsiteDenverWebhook',
                    'status' => false,
                    'message' => 'Error saving or processing sales data: '.$e->getMessage(),
                ], 500);
            }

            return response()->json([
                'ApiName' => 'arcsiteDenverWebhook',
                'status' => true,
                'message' => 'Request sent successfully. Sequifi Data syncing in background',
                'success_sync_data' => $requestData,
            ], 200);

        } catch (\Exception $e) {

            InterigationTransactionLog::create([
                'interigation_name' => 'arcsiteDenverWebhookError',
                'api_name' => 'arcsiteDenverWebhookError',
                'payload' => json_encode(''),
                'response' => json_encode([
                    'error' => 'Arcsite Denver Webhook Critical Error: '.$e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]),
                'url' => '',
            ]);

            \Log::error('Arcsite Denver Webhook Critical Error: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ApiName' => 'arcsiteDenverWebhook',
                'status' => false,
                'message' => 'An error occurred while processing the webhook: '.$e->getMessage(),
            ], 500);
        }
    }

    public function syncDataFromLog()
    {
        set_time_limit(3000); // Sets to 5 minutes

        $date = \Carbon\Carbon::createFromDate(2025, 6, 6)->startOfDay();

        InterigationTransactionLog::where('api_name', 'webhook')
            ->where('payload', 'like', '%proposal.approved%')
            ->where('created_at', '>=', $date)
            ->chunk(100, function ($arcSitedata) {
                foreach ($arcSitedata as $data) {
                    $this->arcsiteSyncData($data, false);
                }
            });
    }

    public function arcsiteSyncData($requestData, $log = true): JsonResponse
    {
        try {

            $customer_signoff = $requestData->created_at->toDateString();
            // echo $customer_signoff;die;
            $apiName = $requestData->api_name;
            $interigationName = $requestData->interigation_name;
            // Decode the payload from the transaction log
            $requestData = json_decode($requestData->payload, true);

            // Set data source type based on interigation name
            if ($interigationName == 'arcsite') {
                $data_source_type = 'Salt Lake City';
            } elseif ($interigationName == 'arcsiteStGeorgeWebhook') {
                $data_source_type = 'St. George';
            } elseif ($interigationName == 'arcsiteDenverWebhook') {
                $data_source_type = 'Denver';
            } elseif ($interigationName == 'arcsiteLasVegasWebhook') {
                $data_source_type = 'Las Vegas';
            } else {
                $data_source_type = 'Salt Lake City'; // Default
            }

            if ($requestData['event'] != 'proposal.approved') {
                return response()->json([
                    'ApiName' => 'arcsiteWebhook',
                    'status' => false,
                    'message' => 'Event not matched with proposal.approved',
                    'success_sync_data' => $requestData,
                ], 400);
            }

            $integration = Integration::where(['name' => 'arcsite', 'status' => 1])->first();
            if (! $integration) {
                return response()->json([
                    'ApiName' => 'arcsiteWebhook',
                    'status' => false,
                    'message' => 'Arcsite setting is not active',
                ], 400);
            }

            $integrationData = $this->getIntegrationData($integration->value);

            if (! $integrationData || empty($integrationData['base_url']) || empty($integrationData['api_token'])) {
                return response()->json([
                    'ApiName' => 'arcsiteWebhook',
                    'status' => false,
                    'message' => 'Invalid Arcsite API credentials',
                ], 400);
            }

            $baseUrl = $integrationData['base_url'];
            $apiToken = $integrationData['api_token'];

            // API Headers
            $headers = [
                'Authorization: Bearer '.$apiToken,
                'Accept: application/json',
                'Content-Type: application/json',
            ];

            // Fetch Drawing Line Items
            // $drawingId = $request->data['proposal_options'][0]['drawing_id'] ?? null;
            $projectId = $requestData['data']['project_id'] ?? null;
            $drawingId = $requestData['data']['approved_option']['drawing_id'] ?? null;
            $lineItemsResponse = curlRequest("{$baseUrl}/drawings/{$drawingId}/line_items", '', $headers, 'GET');
            $lineItems = json_decode($lineItemsResponse, true);

            // Fetch Project Details
            $projectResponse = curlRequest("{$baseUrl}/projects/{$projectId}", '', $headers, 'GET');
            $project = json_decode($projectResponse, true);
            // Sales Representative Details
            $repEmail = $project['sales_rep']['email'] ?? null;
            $repUser = User::where('email', $repEmail)->first();
            if (! $repUser) {
                $repUser = null;
                $matchingUser = UsersAdditionalEmail::with('user')->where('email', $repEmail)->first();
                if ($matchingUser) {
                    $repUser = $matchingUser->user;
                }
            }

            try {

                if (! $repUser) {
                    $import_to_sales = 2;
                } else {
                    $import_to_sales = 0;
                }

                // Get Product Details
                $product = Products::withTrashed()->where('product_id', null)->first()
                    ?? Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();

                $salesData = [
                    'pid' => $projectId,
                    'customer_name' => $project['customer']['name'] ?? null,
                    'customer_email' => $project['customer']['email'] ?? null,
                    'customer_phone' => $project['customer']['phone'] ?? null,
                    'customer_address' => $project['customer']['address']['street'] ?? null,
                    'customer_state' => $project['customer']['address']['state'] ?? null,
                    'customer_city' => $project['customer']['address']['city'] ?? null,
                    'customer_zip' => $project['customer']['address']['zip_code'] ?? null,
                    'adders_description' => isset($project['tags']) ? implode(',', $project['tags']) : null,
                    // 'gross_account_value' => ($lineItems['subtotal'] ?? 0) + ($lineItems['markup'] ?? 0) - ($lineItems['discount'] ?? 0),
                    'gross_account_value' => ($lineItems['markup'] ?? 0) - (abs($lineItems['discount']) ?? 0),
                    'total_amount_in_period' => ($lineItems['total'] ?? 0),
                    'sales_rep_email' => $repEmail,
                    'closer1_id' => $repUser->id ?? null,
                    'customer_signoff' => $customer_signoff,
                    'trigger_date' => json_encode([]),
                    'data_source_type' => $data_source_type,
                    'product_id' => $product->id ?? null,
                    'product_code' => $product->product_id ?? null,
                    'import_to_sales' => $import_to_sales,
                ];

                // Create history record
                LegacyApiRawDataHistory::create($salesData);

                // Dispatch Job for Background Processing
                dispatch(new SaleMasterJob($data_source_type, 100, 'sales-process'))->onQueue('sales-process');

            } catch (\Exception $e) {

                InterigationTransactionLog::create([
                    'interigation_name' => $interigationName.'Error',
                    'api_name' => $apiName.'Error',
                    'payload' => json_encode(''),
                    'response' => json_encode([
                        'error' => 'LegacyApiRawDataHistory Data Processing Error: '.$e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]),
                    'url' => '',
                ]);

                \Log::error('Arcsite Denver Data Processing Error: '.$e->getMessage(), [
                    'salesData' => $salesData,
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                return response()->json([
                    'ApiName' => 'arcsiteDenverWebhook',
                    'status' => false,
                    'message' => 'Error saving or processing sales data: '.$e->getMessage(),
                ], 500);
            }

            return response()->json([
                'ApiName' => $apiName, // Use our variable that works with both arrays and objects
                'status' => true,
                'message' => 'Request sent successfully. Sequifi Data syncing in background',
            ], 200);

        } catch (\Exception $e) {

            InterigationTransactionLog::create([
                'interigation_name' => $interigationName.'Error',
                'api_name' => $apiName.'Error',
                'payload' => json_encode(''),
                'response' => json_encode([
                    'error' => 'Arcsite '.$apiName.' Webhook Critical Error: '.$e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]),
                'url' => '',
            ]);

            \Log::error('Arcsite '.$apiName.' Webhook Critical Error: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ApiName' => $apiName,
                'status' => false,
                'message' => 'An error occurred while processing the webhook: '.$e->getMessage(),
            ], 500);
        }
    }
}
