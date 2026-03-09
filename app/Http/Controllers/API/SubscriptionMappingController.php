<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Subscription\FieldMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class SubscriptionMappingController extends Controller
{
    private $mappingService;

    public function __construct(FieldMappingService $mappingService)
    {
        $this->mappingService = $mappingService;
    }

    /**
     * Map subscription data to database format
     */
    public function mapSubscription(Request $request): JsonResponse
    {
        try {
            // Validate request data
            $request->validate([
                'subscription_data' => 'required|array',
                'customer_data' => 'array',
            ]);

            // Map the data
            $mappedData = $this->mappingService->mapToDatabase(
                $request->input('subscription_data'),
                $request->input('customer_data', [])
            );

            return response()->json([
                'success' => true,
                'data' => $mappedData,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Batch map multiple subscriptions
     */
    public function batchMap(Request $request): JsonResponse
    {
        try {
            // Validate request data
            $request->validate([
                'subscriptions' => 'required|array',
                'subscriptions.*.subscription_data' => 'required|array',
                'subscriptions.*.customer_data' => 'array',
            ]);

            $mappedData = [];
            $errors = [];

            // Process each subscription
            foreach ($request->input('subscriptions') as $index => $data) {
                try {
                    $mappedData[$index] = $this->mappingService->mapToDatabase(
                        $data['subscription_data'],
                        $data['customer_data'] ?? []
                    );
                } catch (\Exception $e) {
                    $errors[$index] = $e->getMessage();
                }
            }

            return response()->json([
                'success' => true,
                'data' => $mappedData,
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get field mapping configuration
     */
    public function getConfiguration(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->mappingService->getMappingConfig(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update field mapping configuration
     */
    public function updateConfiguration(Request $request): JsonResponse
    {
        try {
            // Validate request data
            $request->validate([
                'field_mappings' => 'required|array',
                'field_mappings.api_to_db' => 'array',
                'field_mappings.customer_api_fields' => 'array',
                'field_mappings.computed_fields' => 'array',
            ]);

            // Update configuration
            $updatedConfig = $this->mappingService->updateConfiguration($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Configuration updated successfully',
                'data' => $updatedConfig,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Add new field mapping
     */
    public function addFieldMapping(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $request->validate([
                'section' => 'required|string|in:api_to_db,customer_api_fields,computed_fields',
                'field_name' => 'required|string',
                'mapping' => 'required|array',
                'mapping.db_field' => 'required_without:mapping.db_fields|string',
                'mapping.db_fields' => 'required_without:mapping.db_field|array',
                'mapping.type' => 'required|string|in:string,integer,decimal,boolean,datetime,text',
                'mapping.required' => 'required|boolean',
                'mapping.transform' => 'string',
            ]);

            // Get the configuration file path
            $configPath = config_path('field-mappings/subscription.json');

            // Read existing configuration
            $config = json_decode(File::get($configPath), true);

            // Add new field mapping
            $section = $request->input('section');
            $fieldName = $request->input('field_name');
            $mapping = $request->input('mapping');

            $config['field_mappings'][$section][$fieldName] = $mapping;

            // Update metadata
            $config['metadata']['last_updated'] = now()->format('Y-m-d');
            $config['metadata']['version'] = $this->incrementVersion($config['metadata']['version'] ?? '1.0');

            // Save the configuration
            File::put($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return response()->json([
                'success' => true,
                'message' => 'Field mapping added successfully',
                'data' => $config,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Increment version number
     */
    private function incrementVersion(string $version): string
    {
        $parts = explode('.', $version);
        $parts[count($parts) - 1]++;

        return implode('.', $parts);
    }
}

/**
 * Merge arrays recursively and handle duplicates
 */
function array_merge_recursive_distinct(array &$array1, array &$array2): array
{
    $merged = $array1;

    foreach ($array2 as $key => &$value) {
        if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
            $merged[$key] = array_merge_recursive_distinct($merged[$key], $value);
        } else {
            $merged[$key] = $value;
        }
    }

    return $merged;
}
