<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\Crmcustomfields;
use App\Models\Crmsaleinfo;
use App\Models\SalesMaster;
use App\Services\CustomFieldImportSyncService;
use App\Services\SalesCustomFieldCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CustomSalesFieldController
 * 
 * Handles CRUD operations for custom sales fields.
 * All endpoints are protected by the EnsureFeatureEnabled middleware.
 * 
 * Note: This is a single-tenant application where CompanyProfile::first() 
 * returns the company for the current deployment.
 */
class CustomSalesFieldController extends Controller
{
    /**
     * Check if the current user has permission to manage custom fields.
     * Only super admins can manage custom sales fields.
     * 
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    private function authorizeManageCustomFields(): void
    {
        $user = auth()->user();
        
        if (!$user || $user->is_super_admin != '1') {
            abort(403, 'You do not have permission to manage custom sales fields.');
        }
    }

    /**
     * Get the company ID for the current deployment
     */
    private function getCompanyId(): int
    {
        return CompanyProfile::first()->id;
    }

    /**
     * List all active custom fields for the company
     */
    public function index(Request $request): JsonResponse
    {
        $fields = Crmcustomfields::forCompany($this->getCompanyId())
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function ($field) {
                // Use the new Custom Field-specific usage check
                $usageDetails = $field->getCustomFieldUsageDetails();
                $fieldData = $this->formatFieldResponse($field, false);
                $fieldData['can_archive'] = !$usageDetails['is_used'];
                if ($usageDetails['is_used']) {
                    $fieldData['usage_details'] = $usageDetails['usage'];
                }
                return $fieldData;
            });

        return response()->json([
            'status' => true,
            'data' => $fields,
        ]);
    }

    /**
     * Create a new custom field
     * 
     * Table schema: crmsale_custom_field
     * - name, type, value (JSON), visiblecustomer, status, sort_order, field_category, company_id
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeManageCustomFields();
        
        $companyId = $this->getCompanyId();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:text,number,date',
            'is_calculated' => 'nullable|boolean',
            'is_available_in_position' => 'nullable|boolean',
            'visiblecustomer' => 'nullable|boolean',
            'formula' => 'nullable|array',
        ]);

        // Validate formula structure if provided
        if ($request->has('formula') && $request->formula !== null) {
            $request->validate([
                'formula.operator' => 'required|in:add,subtract,multiply,divide',
                'formula.operands' => 'required|array|min:2',
                'formula.operands.*.type' => 'required|in:field,constant',
                'formula.operands.*.value' => 'nullable|numeric',
                'formula.operands.*.key' => 'nullable|string',
                'formula.operands.*.field_id' => 'nullable|integer',
            ]);
        }

        // Check for duplicate field name (case-insensitive)
        $existingField = Crmcustomfields::forCompany($companyId)
            ->whereRaw('LOWER(name) = ?', [strtolower($validated['name'])])
            ->where('status', 1)
            ->first();

        if ($existingField) {
            return response()->json([
                'status' => false,
                'message' => 'A custom field with this name already exists',
                'errors' => ['name' => ['A custom field with this name already exists']],
            ], 422);
        }

        // Get the next sort order
        $maxSortOrder = Crmcustomfields::forCompany($companyId)
            ->max('sort_order') ?? 0;

        // Build metadata for the value JSON field
        $valueJson = Crmcustomfields::buildValueJson(
            $validated['is_calculated'] ?? false,
            $validated['is_available_in_position'] ?? false,
            $validated['formula'] ?? null
        );

        $field = Crmcustomfields::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'value' => $valueJson,
            'visiblecustomer' => ($validated['visiblecustomer'] ?? true) ? 1 : 0,
            'status' => 1,
            'sort_order' => $maxSortOrder + 1,
            'field_category' => 'custom_sales',
        ]);

        // Sync to sales import fields table
        app(CustomFieldImportSyncService::class)->addToImportFields($field);

        return response()->json([
            'status' => true,
            'message' => 'Custom field created successfully',
            'data' => $this->formatFieldResponse($field),
        ], 201);
    }

    /**
     * Format field response to include extracted JSON values
     * 
     * @param Crmcustomfields $field
     * @param bool $includeUsageInfo Whether to include can_archive and usage_details
     */
    private function formatFieldResponse(Crmcustomfields $field, bool $includeUsageInfo = true): array
    {
        $response = [
            'id' => $field->id,
            'company_id' => $field->company_id,
            'name' => $field->name,
            'type' => $field->type,
            'field_category' => $field->field_category,
            'is_calculated' => $field->is_calculated,
            'is_available_in_position' => $field->is_available_in_position,
            'formula' => $field->formula,
            'visiblecustomer' => (bool) $field->visiblecustomer,
            'status' => (bool) $field->status,
            'sort_order' => $field->sort_order,
            'created_at' => $field->created_at,
            'updated_at' => $field->updated_at,
        ];

        // Include usage info for archive functionality
        if ($includeUsageInfo) {
            $isInUse = $field->isInUse();
            $response['can_archive'] = !$isInUse;
            if ($isInUse) {
                $response['usage_details'] = $field->getUsageDetails();
            }
        }

        return $response;
    }

    /**
     * Create multiple custom fields at once
     */
    public function storeBulk(Request $request): JsonResponse
    {
        $this->authorizeManageCustomFields();
        
        $companyId = $this->getCompanyId();

        $validated = $request->validate([
            'fields' => 'required|array|min:1',
            'fields.*.name' => 'required|string|max:100',
            'fields.*.type' => 'required|in:text,number,date',
            'fields.*.is_calculated' => 'nullable|boolean',
            'fields.*.is_available_in_position' => 'nullable|boolean',
            'fields.*.visiblecustomer' => 'nullable|boolean',
            'fields.*.formula' => 'nullable|array',
        ]);

        // Get existing field names (case-insensitive)
        $existingNames = Crmcustomfields::forCompany($companyId)
            ->where('status', 1)
            ->pluck('name')
            ->map(fn($name) => strtolower($name))
            ->toArray();

        // Check for duplicates within the request and against existing fields
        $duplicates = [];
        $requestedNames = [];
        foreach ($validated['fields'] as $index => $fieldData) {
            $lowerName = strtolower($fieldData['name']);

            // Check against existing fields in database
            if (in_array($lowerName, $existingNames)) {
                $duplicates[] = "Field '{$fieldData['name']}' already exists";
            }

            // Check for duplicates within the request itself
            if (in_array($lowerName, $requestedNames)) {
                $duplicates[] = "Field '{$fieldData['name']}' is duplicated in the request";
            }
            $requestedNames[] = $lowerName;
        }

        if (!empty($duplicates)) {
            return response()->json([
                'status' => false,
                'message' => 'Duplicate field names found',
                'errors' => ['fields' => $duplicates],
            ], 422);
        }

        $maxSortOrder = Crmcustomfields::forCompany($companyId)->max('sort_order') ?? 0;
        $createdFields = [];
        $importSyncService = app(CustomFieldImportSyncService::class);

        foreach ($validated['fields'] as $index => $fieldData) {
            // Build metadata for the value JSON field
            $valueJson = Crmcustomfields::buildValueJson(
                $fieldData['is_calculated'] ?? false,
                $fieldData['is_available_in_position'] ?? false,
                $fieldData['formula'] ?? null
            );

            $field = Crmcustomfields::create([
                'company_id' => $companyId,
                'name' => $fieldData['name'],
                'type' => $fieldData['type'],
                'value' => $valueJson,
                'visiblecustomer' => ($fieldData['visiblecustomer'] ?? true) ? 1 : 0,
                'status' => 1,
                'sort_order' => $maxSortOrder + $index + 1,
                'field_category' => 'custom_sales',
            ]);

            // Sync to sales import fields table
            $importSyncService->addToImportFields($field);

            $createdFields[] = $this->formatFieldResponse($field);
        }

        return response()->json([
            'status' => true,
            'message' => count($createdFields) . ' custom field(s) created successfully',
            'data' => $createdFields,
        ], 201);
    }

    /**
     * Get a single custom field
     */
    public function show(int $id): JsonResponse
    {
        $field = Crmcustomfields::forCompany($this->getCompanyId())
            ->findOrFail($id);

        // Use the new Custom Field-specific usage check
        $usageDetails = $field->getCustomFieldUsageDetails();
        $fieldData = $this->formatFieldResponse($field, false);
        $fieldData['can_archive'] = !$usageDetails['is_used'];
        if ($usageDetails['is_used']) {
            $fieldData['usage_details'] = $usageDetails['usage'];
        }

        return response()->json([
            'status' => true,
            'data' => $fieldData,
        ]);
    }

    /**
     * Update a custom field
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeManageCustomFields();
        
        $companyId = $this->getCompanyId();
        $field = Crmcustomfields::forCompany($companyId)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'type' => 'sometimes|in:text,number,date',
            'is_calculated' => 'nullable|boolean',
            'is_available_in_position' => 'nullable|boolean',
            'visiblecustomer' => 'nullable|boolean',
            'formula' => 'nullable|array',
            'status' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        // Validate formula structure if provided
        if ($request->has('formula') && $request->formula !== null) {
            $request->validate([
                'formula.operator' => 'required|in:add,subtract,multiply,divide',
                'formula.operands' => 'required|array|min:2',
                'formula.operands.*.type' => 'required|in:field,constant',
                'formula.operands.*.value' => 'nullable|numeric',
                'formula.operands.*.key' => 'nullable|string',
                'formula.operands.*.field_id' => 'nullable|integer',
            ]);
        }

        // Check for duplicate field name if name is being updated (case-insensitive)
        if (isset($validated['name']) && strtolower($validated['name']) !== strtolower($field->name)) {
            $existingField = Crmcustomfields::forCompany($companyId)
                ->whereRaw('LOWER(name) = ?', [strtolower($validated['name'])])
                ->where('id', '!=', $id)
                ->where('status', 1)
                ->first();

            if ($existingField) {
                return response()->json([
                    'status' => false,
                    'message' => 'A custom field with this name already exists',
                    'errors' => ['name' => ['A custom field with this name already exists']],
                ], 422);
            }
        }

        // Prepare update data with only valid table columns
        $updateData = [];

        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }
        if (isset($validated['type'])) {
            $updateData['type'] = $validated['type'];
        }
        if (isset($validated['visiblecustomer'])) {
            $updateData['visiblecustomer'] = $validated['visiblecustomer'] ? 1 : 0;
        }
        if (isset($validated['status'])) {
            $newStatus = $validated['status'] ? 1 : 0;
            
            // If restoring an archived field (status 0 -> 1), check for duplicate names
            if ($field->status == 0 && $newStatus == 1) {
                $existingField = Crmcustomfields::forCompany($companyId)
                    ->whereRaw('LOWER(name) = ?', [strtolower($field->name)])
                    ->where('id', '!=', $id)
                    ->where('status', 1)
                    ->first();

                if ($existingField) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Cannot restore this custom field because another active field with the same name already exists',
                        'errors' => ['name' => ['An active custom field with the name "' . $field->name . '" already exists']],
                    ], 422);
                }
            }
            
            $updateData['status'] = $newStatus;
        }
        if (isset($validated['sort_order'])) {
            $updateData['sort_order'] = $validated['sort_order'];
        }

        // Update the value JSON if any metadata fields are provided
        if (isset($validated['is_calculated']) || isset($validated['is_available_in_position']) || isset($validated['formula'])) {
            $updateData['value'] = Crmcustomfields::buildValueJson(
                $validated['is_calculated'] ?? $field->is_calculated,
                $validated['is_available_in_position'] ?? $field->is_available_in_position,
                $validated['formula'] ?? $field->formula
            );
        }

        if (!empty($updateData)) {
            $field->update($updateData);
            
            // Sync changes to sales import fields table
            $importSyncService = app(CustomFieldImportSyncService::class);
            
            // If status changed to archived, remove from import fields
            if (isset($updateData['status']) && $updateData['status'] == 0) {
                $importSyncService->removeFromImportFields($field);
            } elseif (isset($updateData['status']) && $updateData['status'] == 1) {
                // If restored, add back to import fields (eligibility checked in service)
                $importSyncService->addToImportFields($field);
            } elseif (isset($validated['is_calculated']) || isset($validated['is_available_in_position'])) {
                // If calculated or position status changed, re-evaluate eligibility
                // updateInImportFields will remove if no longer eligible, or add if now eligible
                $importSyncService->updateInImportFields($field->fresh());
            } elseif (isset($updateData['name']) || isset($updateData['type'])) {
                // If name or type changed, update import fields
                $importSyncService->updateInImportFields($field->fresh());
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Custom field updated successfully',
            'data' => $this->formatFieldResponse($field->fresh()),
        ]);
    }

    /**
     * Archive a custom field (set status to 0)
     * Note: Using status=0 as "archived" since the table doesn't have is_archived column
     */
    public function archive(int $id): JsonResponse
    {
        $this->authorizeManageCustomFields();
        
        $field = Crmcustomfields::forCompany($this->getCompanyId())
            ->where('status', 1)
            ->findOrFail($id);

        // Check if field is being used (Custom Sales Fields feature-specific checks)
        $usageDetails = $field->getCustomFieldUsageDetails();
        if ($usageDetails['is_used']) {
            $usage = $usageDetails['usage'];
            $messages = [];
            
            if (!empty($usage['formulas'])) {
                $messages[] = 'calculated field formulas: ' . implode(', ', $usage['formulas']);
            }
            if (!empty($usage['commissions'])) {
                $messages[] = 'commission configurations for: ' . implode(', ', $usage['commissions']);
            }
            if (!empty($usage['overrides'])) {
                $messages[] = 'override configurations for: ' . implode(', ', $usage['overrides']);
            }
            if (!empty($usage['upfronts'])) {
                $messages[] = 'upfront configurations for: ' . implode(', ', $usage['upfronts']);
            }
            
            return response()->json([
                'status' => false,
                'message' => 'Cannot archive this field because it is being used in ' . implode('; ', $messages),
                'usage' => $usage,
            ], 422);
        }

        $field->update(['status' => 0]);

        // Remove from sales import fields table
        app(CustomFieldImportSyncService::class)->removeFromImportFields($field);

        return response()->json([
            'status' => true,
            'message' => 'Custom field archived successfully',
        ]);
    }

    /**
     * Restore an archived custom field (set status to 1)
     */
    public function unarchive(int $id): JsonResponse
    {
        $this->authorizeManageCustomFields();
        
        $companyId = $this->getCompanyId();
        $field = Crmcustomfields::forCompany($companyId)
            ->where('status', 0)
            ->findOrFail($id);

        // Check if another active field with the same name already exists (case-insensitive)
        $existingField = Crmcustomfields::forCompany($companyId)
            ->whereRaw('LOWER(name) = ?', [strtolower($field->name)])
            ->where('id', '!=', $id)
            ->where('status', 1)
            ->first();

        if ($existingField) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot restore this custom field because another active field with the same name already exists',
                'errors' => ['name' => ['An active custom field with the name "' . $field->name . '" already exists']],
            ], 422);
        }

        $field->update(['status' => 1]);

        // Add back to sales import fields table
        app(CustomFieldImportSyncService::class)->addToImportFields($field);

        return response()->json([
            'status' => true,
            'message' => 'Custom field restored successfully',
        ]);
    }

    /**
     * List all archived (inactive) custom fields
     */
    public function archivedList(Request $request): JsonResponse
    {
        $fields = Crmcustomfields::forCompany($this->getCompanyId())
            ->where('status', 0)
            ->orderBy('name')
            ->get()
            ->map(fn($field) => $this->formatFieldResponse($field));

        return response()->json([
            'status' => true,
            'data' => $fields,
        ]);
    }

    /**
     * Sync all custom fields to the sales import fields table
     * This cleans up orphaned entries and adds missing ones
     */
    public function syncImportFields(): JsonResponse
    {
        $result = app(CustomFieldImportSyncService::class)->syncAllCustomFields();

        return response()->json([
            'status' => $result['success'],
            'message' => $result['message'],
            'data' => [
                'added' => $result['added'] ?? 0,
                'updated' => $result['updated'] ?? 0,
                'removed' => $result['removed'] ?? 0,
            ],
        ]);
    }

    /**
     * Check if a custom field is in use
     */
    public function checkUsage(int $id): JsonResponse
    {
        $field = Crmcustomfields::forCompany($this->getCompanyId())
            ->findOrFail($id);

        // Use the new Custom Field-specific usage check
        $usageDetails = $field->getCustomFieldUsageDetails();

        return response()->json([
            'status' => true,
            'data' => [
                'is_in_use' => $usageDetails['is_used'],
                'can_archive' => !$usageDetails['is_used'],
                'usage_details' => $usageDetails['usage'],
            ],
        ]);
    }

    /**
     * Get dropdown list of custom fields for position configuration
     * Returns only custom fields from database (number type, active, available in position)
     */
    public function positionDropdown(): JsonResponse
    {
        // Get custom fields from database
        $customFields = Crmcustomfields::forCompany($this->getCompanyId())
            ->active()
            ->numberType()
            ->availableInPosition()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn($field) => [
                'type' => 'custom_field',
                'value' => 'custom_field_' . $field->id,
                'id' => $field->id,
                'name' => $field->name,
                'is_calculated' => $field->is_calculated,
                'symbol' => $this->getOperatorSymbol($field->formula),
                'sort_order' => $field->sort_order,
            ])
            ->values()
            ->toArray();

        // Calculate the next sort order for new custom fields
        $maxSortOrder = Crmcustomfields::forCompany($this->getCompanyId())
            ->max('sort_order') ?? 0;

        return response()->json([
            'ApiName' => 'getPositionDropdownFields',
            'status' => true,
            'data' => [
                'fields' => $customFields,
                'current_sort_order' => $maxSortOrder + 1,
                'total_available' => count($customFields),
            ],
        ]);
    }

    /**
     * Convert formula operator to display symbol
     * 
     * @param array|null $formula The formula array from value JSON
     * @return string The display symbol for the operator
     */
    private function getOperatorSymbol(?array $formula): string
    {
        if (!$formula || !isset($formula['operator'])) {
            return '';
        }

        return match ($formula['operator']) {
            'add' => '+',
            'subtract' => '-',
            'multiply' => '×',
            'divide' => '÷',
            default => '',
        };
    }

    /**
     * Save custom field values for a sale
     * 
     * @param Request $request Contains pid (alphanumeric) and values array
     */
    public function saveValues(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pid' => 'required|string', // pid can be alphanumeric
            'values' => 'required|array',
            'values.*.field_id' => 'required|exists:crmsale_custom_field,id',
            'values.*.value' => 'required',
        ]);

        $saleInfo = Crmsaleinfo::firstOrCreate(
            ['pid' => $validated['pid']],
            ['created_id' => auth()->id()]
        );

        // Only store NON-CALCULATED field values
        // Calculated fields should always be computed on-the-fly to avoid stale data
        $fieldValues = [];
        foreach ($validated['values'] as $val) {
            $field = Crmcustomfields::find($val['field_id']);
            
            // Skip calculated fields - they should not be stored
            if ($field && $field->is_calculated) {
                continue;
            }
            
            $fieldValues[$val['field_id']] = $val['value'];
        }

        $saleInfo->update(['custom_field_values' => $fieldValues]);

        return response()->json([
            'status' => true,
            'message' => 'Custom field values saved successfully',
        ]);
    }

    /**
     * Get custom field values for a sale (raw values only)
     * 
     * @param string $pid The sale PID (can be alphanumeric)
     */
    public function getValues(string $pid): JsonResponse
    {
        $saleInfo = Crmsaleinfo::where('pid', $pid)->first();

        if (!$saleInfo || !$saleInfo->custom_field_values) {
            return response()->json([
                'status' => true,
                'data' => [],
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $saleInfo->custom_field_values,
        ]);
    }

    /**
     * Get custom sales field details for a sale (for sales details page)
     * 
     * Returns all active custom fields with their values for the given sale.
     * Fields are returned in sort_order, with value populated from crmsaleinfo.
     * 
     * @param string $pid The sale PID (can be alphanumeric)
     */
    public function getSaleDetails(string $pid): JsonResponse
    {
        $companyId = $this->getCompanyId();
        
        // Get all active custom fields for the company
        $fields = Crmcustomfields::forCompany($companyId)
            ->active()
            ->where('field_category', 'custom_sales')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Get stored values for this sale
        $saleInfo = Crmsaleinfo::where('pid', $pid)->first();
        $storedValues = $saleInfo?->custom_field_values ?? [];

        // Get the calculator service for computed field values
        $calculator = app(SalesCustomFieldCalculator::class);

        // Build response with field definitions and values
        $fieldsWithValues = $fields->map(function ($field) use ($storedValues, $pid, $calculator) {
            $fieldId = (string) $field->id;
            $value = $storedValues[$fieldId] ?? null;
            
            // For calculated fields, use the calculator service to compute the value
            if ($field->is_calculated && $field->formula) {
                $calculatedValue = $calculator->getCustomFieldValue($pid, $field->id);
                if ($calculatedValue !== null) {
                    $value = $calculatedValue;
                }
            }

            return [
                'id' => $field->id,
                'name' => $field->name,
                'type' => $field->type,
                'value' => $value,
                'is_calculated' => $field->is_calculated,
                'formula' => $field->formula,
                'visiblecustomer' => (bool) $field->visiblecustomer,
                'sort_order' => $field->sort_order,
            ];
        });

        return response()->json([
            'ApiName' => 'getSaleCustomFieldDetails',
            'status' => true,
            'data' => [
                'pid' => $pid,
                'fields' => $fieldsWithValues,
                'total_fields' => $fieldsWithValues->count(),
            ],
        ]);
    }

    /**
     * Calculate field value based on formula (for custom fields only)
     * 
     * @param Crmcustomfields $field The calculated field
     * @param array $storedValues All stored field values for the sale
     * @return float|null The calculated value or null if calculation fails
     */
    private function calculateFieldValue(Crmcustomfields $field, array $storedValues): ?float
    {
        $formula = $field->formula;
        if (!$formula || !isset($formula['type']) || !isset($formula['operands'])) {
            return null;
        }

        $operands = [];
        foreach ($formula['operands'] as $operand) {
            if ($operand['type'] === 'field') {
                $fieldId = (string) $operand['field_id'];
                $operandValue = $storedValues[$fieldId] ?? null;
                if ($operandValue === null) {
                    return null; // Cannot calculate without all operand values
                }
                $operands[] = (float) $operandValue;
            } elseif ($operand['type'] === 'constant') {
                $operands[] = (float) ($operand['value'] ?? 0);
            }
        }

        if (count($operands) < 2) {
            return null;
        }

        return match ($formula['type']) {
            'addition' => array_sum($operands),
            'subtraction' => $operands[0] - $operands[1],
            'multiplication' => $operands[0] * $operands[1],
            'division' => $operands[1] != 0 ? $operands[0] / $operands[1] : null,
            default => null,
        };
    }

    /**
     * Calculate field value based on formula using sale data
     * 
     * Supports two operand types in the operands array:
     * 1. Field type: { "type": "field", "key": "gross_account_value" }
     * 2. Constant type: { "type": "constant", "value": 0.5 }
     * 
     * Formula structure in database:
     * {
     *   "operands": [
     *     { "type": "field", "key": "gross_account_value" },
     *     { "type": "constant", "value": 0.5 }
     *   ],
     *   "operator": "add" | "subtract" | "multiply" | "divide"
     * }
     * 
     * Also supports legacy format:
     * {
     *   "field_1": "gross_account_value",
     *   "operator": "multiply",
     *   "field_2": "initial_service_cost"
     * }
     * 
     * @param Crmcustomfields $field The calculated field
     * @param array $storedValues All stored custom field values for the sale
     * @param SalesMaster|null $saleMaster The sale master record with system field values
     * @return float|null The calculated value or null if calculation fails
     */
    private function calculateFieldValueFromSale(Crmcustomfields $field, array $storedValues, ?SalesMaster $saleMaster): ?float
    {
        $formula = $field->formula;
        if (!$formula) {
            return null;
        }

        $operator = $formula['operator'] ?? null;
        if ($operator === null) {
            return null;
        }

        // Check if using operands array format (new format)
        if (isset($formula['operands']) && is_array($formula['operands'])) {
            return $this->calculateFromOperandsArray($formula['operands'], $operator, $storedValues, $saleMaster);
        }

        // Legacy format with field_1 and field_2
        $value1 = $this->getOperandValue($formula['field_1'] ?? null, $storedValues, $saleMaster);
        $value2 = $this->getOperandValue($formula['field_2'] ?? null, $storedValues, $saleMaster);

        // If either operand is null, cannot calculate
        if ($value1 === null || $value2 === null) {
            return null;
        }

        return $this->performCalculation($value1, $value2, $operator);
    }

    /**
     * Calculate value from operands array format
     * 
     * @param array $operands Array of operand objects
     * @param string $operator The operator to use
     * @param array $storedValues Custom field values
     * @param SalesMaster|null $saleMaster Sale master record
     * @return float|null Calculated value or null
     */
    private function calculateFromOperandsArray(array $operands, string $operator, array $storedValues, ?SalesMaster $saleMaster): ?float
    {
        if (count($operands) < 2) {
            return null;
        }

        $values = [];
        foreach ($operands as $operand) {
            $type = $operand['type'] ?? null;
            
            if ($type === 'constant') {
                // Constant value
                $value = $operand['value'] ?? null;
                if ($value === null) {
                    return null;
                }
                $values[] = (float) $value;
            } elseif ($type === 'field') {
                // Field reference - can be system field or custom field
                $key = $operand['key'] ?? $operand['field_id'] ?? null;
                if ($key === null) {
                    return null;
                }
                $value = $this->getOperandValue($key, $storedValues, $saleMaster);
                if ($value === null) {
                    return null;
                }
                $values[] = $value;
            } elseif ($type === 'custom_field') {
                // Custom field reference by ID
                $fieldId = $operand['field_id'] ?? $operand['id'] ?? null;
                if ($fieldId === null) {
                    return null;
                }
                $value = $storedValues[(string) $fieldId] ?? null;
                if ($value === null) {
                    return null;
                }
                $values[] = (float) $value;
            } else {
                return null;
            }
        }

        if (count($values) < 2) {
            return null;
        }

        return $this->performCalculation($values[0], $values[1], $operator);
    }

    /**
     * Perform the arithmetic calculation
     * 
     * @param float $value1 First operand
     * @param float $value2 Second operand
     * @param string $operator The operator
     * @return float|null Result or null if invalid operator
     */
    private function performCalculation(float $value1, float $value2, string $operator): ?float
    {
        return match (strtolower($operator)) {
            'add', 'addition', '+' => $value1 + $value2,
            'subtract', 'subtraction', '-' => $value1 - $value2,
            'multiply', 'multiplication', '*', '×' => $value1 * $value2,
            'divide', 'division', '/', '÷' => $value2 != 0 ? $value1 / $value2 : null,
            default => null,
        };
    }

    /**
     * Get the value for a formula operand
     * 
     * Supports:
     * - System sale fields: gross_account_value, initial_service_cost, total_commission_amount, etc.
     * - Custom field references: custom_field_5, custom_field_10, etc.
     * - Another custom field name directly (lookup by name)
     * - Numeric field ID (for custom fields)
     * 
     * @param string|int|null $fieldReference The field reference (column name, custom_field_id, or numeric ID)
     * @param array $storedValues Custom field values stored for the sale
     * @param SalesMaster|null $saleMaster The sale master record
     * @return float|null The field value or null if not found
     */
    private function getOperandValue(string|int|null $fieldReference, array $storedValues, ?SalesMaster $saleMaster): ?float
    {
        if ($fieldReference === null) {
            return null;
        }

        $fieldReference = (string) $fieldReference;

        // Check if it's a numeric ID (custom field ID)
        if (is_numeric($fieldReference)) {
            $value = $storedValues[$fieldReference] ?? null;
            return $value !== null ? (float) $value : null;
        }

        // Check if it's a custom field reference (e.g., "custom_field_5")
        if (str_starts_with($fieldReference, 'custom_field_')) {
            $customFieldId = str_replace('custom_field_', '', $fieldReference);
            $value = $storedValues[$customFieldId] ?? null;
            return $value !== null ? (float) $value : null;
        }

        // First, check if it's a system sale field (most common case)
        if ($saleMaster !== null) {
            // Map of supported system sale fields
            $systemFields = $this->getSystemSaleFields();
            
            // Convert display name to column name if needed
            $columnName = $systemFields[$fieldReference] ?? $fieldReference;

            // Get the value from sale master - check if attribute exists
            if (array_key_exists($columnName, $saleMaster->getAttributes()) || isset($saleMaster->{$columnName})) {
                $value = $saleMaster->{$columnName};
                if ($value !== null) {
                    return (float) $value;
                }
            }
        }

        // Check if it's a reference to another custom field by name
        $customField = Crmcustomfields::forCompany($this->getCompanyId())
            ->active()
            ->where('name', $fieldReference)
            ->first();

        if ($customField) {
            $value = $storedValues[(string) $customField->id] ?? null;
            return $value !== null ? (float) $value : null;
        }

        return null;
    }

    /**
     * Get mapping of display names to column names for system sale fields
     * 
     * @return array<string, string> Display name => column name
     */
    private function getSystemSaleFields(): array
    {
        return [
            // Common display names to column mappings
            'Gross Account Value' => 'gross_account_value',
            'gross_account_value' => 'gross_account_value',
            'Initial Service Cost' => 'initial_service_cost',
            'initial_service_cost' => 'initial_service_cost',
            'Total Commission Amount' => 'total_commission_amount',
            'total_commission_amount' => 'total_commission_amount',
            'Total Commission' => 'total_commission',
            'total_commission' => 'total_commission',
            'Total Override Amount' => 'total_override_amount',
            'total_override_amount' => 'total_override_amount',
            'Total Override' => 'total_override',
            'total_override' => 'total_override',
            'KW' => 'kw',
            'kw' => 'kw',
            'EPC' => 'epc',
            'epc' => 'epc',
            'Net EPC' => 'net_epc',
            'net_epc' => 'net_epc',
            'Dealer Fee Percentage' => 'dealer_fee_percentage',
            'dealer_fee_percentage' => 'dealer_fee_percentage',
            'Dealer Fee Amount' => 'dealer_fee_amount',
            'dealer_fee_amount' => 'dealer_fee_amount',
            'Adders' => 'adders',
            'adders' => 'adders',
            'Redline' => 'redline',
            'redline' => 'redline',
            'M1 Amount' => 'm1_amount',
            'm1_amount' => 'm1_amount',
            'M2 Amount' => 'm2_amount',
            'm2_amount' => 'm2_amount',
            'Cash Amount' => 'cash_amount',
            'cash_amount' => 'cash_amount',
            'Loan Amount' => 'loan_amount',
            'loan_amount' => 'loan_amount',
            'Projected Commission' => 'projected_commission',
            'projected_commission' => 'projected_commission',
            'Cancel Fee' => 'cancel_fee',
            'cancel_fee' => 'cancel_fee',
            'Lead Cost Amount' => 'lead_cost_amount',
            'lead_cost_amount' => 'lead_cost_amount',
        ];
    }

    /**
     * Get custom fields for a sale (for sales details page)
     * 
     * Route: GET /api/v1/sales/{pid}/custom-fields
     * 
     * Returns all active custom fields with their values for the given sale.
     * Calculated fields show computed values based on their formulas.
     * Formulas can reference both system sale fields (e.g., gross_account_value)
     * and other custom fields.
     * 
     * @param string $pid The sale PID (can be alphanumeric)
     */
    public function getSaleCustomFields(string $pid): JsonResponse
    {
        $companyId = $this->getCompanyId();
        
        // Get all active custom fields for the company
        $fields = Crmcustomfields::forCompany($companyId)
            ->active()
            ->where('field_category', 'custom_sales')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Get stored custom field values for this sale
        $saleInfo = Crmsaleinfo::where('pid', $pid)->first();
        $storedValues = $saleInfo?->custom_field_values ?? [];

        // Get the calculator service for computed field values
        $calculator = app(SalesCustomFieldCalculator::class);

        // Build response with field definitions and values
        $fieldsWithValues = $fields->map(function ($field) use ($storedValues, $pid, $calculator) {
            $fieldId = (string) $field->id;
            $storedValue = $storedValues[$fieldId] ?? null;
            $isCalculated = $field->is_calculated;
            
            // Determine if value was computed or stored
            $value = $storedValue;
            $isComputed = false;
            
            // For calculated fields, use the calculator service to compute the value
            if ($isCalculated && $field->formula) {
                $computedValue = $calculator->getCustomFieldValue($pid, $field->id);
                if ($computedValue !== null) {
                    $value = round($computedValue, 2); // Round to 2 decimal places
                    $isComputed = true;
                }
            }

            $response = [
                'id' => $field->id,
                'field_name' => $field->name,
                'field_type' => $field->type,
                'is_calculated' => $isCalculated,
                'visible_to_standard_user' => (bool) $field->visiblecustomer,
                'is_editable' => !$isCalculated, // Calculated fields are not editable
                'sort_order' => $field->sort_order,
                'value' => $value,
                'is_computed' => $isComputed,
            ];

            // Add note field for calculated fields
            if ($isCalculated) {
                $response['note'] = null;
            }

            return $response;
        });

        return response()->json([
            'ApiName' => 'getSaleCustomFields',
            'status' => true,
            'data' => $fieldsWithValues->values()->toArray(),
        ]);
    }

}
