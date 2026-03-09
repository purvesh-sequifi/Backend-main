<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V2\Sales;

use App\Http\Controllers\Controller;
use App\Models\SalesMaster;
use App\Models\SaleMastersHistory;
use App\Models\Products;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SalesHistoryController extends Controller
{
    /**
     * Get sale history by PID with pagination
     *
     * @param string $pid Sale PID
     * @return JsonResponse
     */
    public function getHistoryByPid(string $pid): JsonResponse
    {
        try {
            $sale = SalesMaster::where('pid', $pid)->first();

            if (!$sale) {
                return response()->json([
                    'status' => false,
                    'message' => 'Sale not found with PID: ' . $pid
                ], 404);
            }

            // Get pagination parameters
            $perPage = request()->input('per_page', 25);
            $page = request()->input('page', 1);

            // Get paginated history with relationships (DESC order)
            $historyPaginated = SaleMastersHistory::where('sale_master_id', $sale->id)
                ->with(['changedBy:id,first_name,last_name,email'])
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $history = $historyPaginated->map(function ($item) use ($sale) {
                // Format changed fields with beautified names
                $formattedFields = [];
                foreach ($item->changed_fields ?? [] as $field) {
                    // Skip state_id from display (internal field)
                    if ($field === 'state_id') {
                        continue;
                    }

                    $oldValue = $item->old_values[$field] ?? null;
                    $newValue = $item->new_values[$field] ?? null;

                    // Special handling for milestone_trigger:
                    // If old value is null but we have legacy_m1_date/legacy_m2_date, reconstruct old milestones
                    if ($field === 'milestone_trigger' && $oldValue === null) {
                        $legacyM1 = $item->old_values['legacy_m1_date'] ?? null;
                        $legacyM2 = $item->old_values['legacy_m2_date'] ?? null;

                        if ($legacyM1 !== null || $legacyM2 !== null) {
                            // Reconstruct old milestone array from legacy dates
                            $milestoneNames = $this->getMilestoneNames($sale->product_id);
                            $legacyDates = [];

                            if ($legacyM1 !== null) {
                                $legacyDates[] = [
                                    'name' => $milestoneNames[0] ?? 'M1 Date',
                                    'trigger' => $milestoneNames[0] ? $this->getMilestoneTrigger($sale->product_id, 0) : 'Initial Service Date',
                                    'date' => $legacyM1
                                ];
                            }
                            if ($legacyM2 !== null) {
                                $legacyDates[] = [
                                    'name' => $milestoneNames[1] ?? 'M2 Date',
                                    'trigger' => $milestoneNames[1] ? $this->getMilestoneTrigger($sale->product_id, 1) : 'Service Completion Date',
                                    'date' => $legacyM2
                                ];
                            }

                            $oldValue = $legacyDates;
                        }
                    }

                    // Format values
                    $formattedOldValue = $this->formatFieldValue($field, $oldValue);
                    $formattedNewValue = $this->formatFieldValue($field, $newValue);

                    // Special handling for milestone_trigger: filter out unchanged milestones
                    if ($field === 'milestone_trigger') {
                        // Only filter if BOTH old and new are arrays with data
                        if (is_array($formattedOldValue) && !empty($formattedOldValue) &&
                            is_array($formattedNewValue) && !empty($formattedNewValue)) {
                            $filteredResult = $this->filterUnchangedMilestones($formattedOldValue, $formattedNewValue);
                            $formattedOldValue = $filteredResult['old'];
                            $formattedNewValue = $filteredResult['new'];
                        }
                        // If old is 'null' string and new is array, don't filter - show all new milestones
                        // This handles the case where milestone_trigger was null -> new milestones added
                    }

                    $formattedFields[] = [
                        'field' => $field,
                        'field_label' => $this->beautifyFieldName($field),
                        'old_value' => $formattedOldValue,
                        'new_value' => $formattedNewValue,
                    ];
                }

                return [
                    'id' => $item->id,
                    'sale_master_id' => $item->sale_master_id,
                    'pid' => $item->pid,
                    'change_type' => $item->change_type,
                    'data_source_type' => $item->data_source_type,
                    'old_values' => $item->old_values,
                    'new_values' => $item->new_values,
                    'changed_fields' => $item->changed_fields,
                    'formatted_changes' => $formattedFields,
                    'changed_by' => $item->changed_by,
                    'changed_by_user' => $item->changedBy
                        ? trim($item->changedBy->first_name . ' ' . $item->changedBy->last_name)
                        : 'System',
                    'ip_address' => $item->ip_address,
                    'user_agent' => $item->user_agent,
                    'reason' => $item->reason,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Sale history retrieved successfully',
                'data' => $history,
                'pagination' => [
                    'total' => $historyPaginated->total(),
                    'count' => $history->count(),
                    'per_page' => $historyPaginated->perPage(),
                    'current_page' => $historyPaginated->currentPage(),
                    'total_pages' => $historyPaginated->lastPage(),
                    'has_more' => $historyPaginated->hasMorePages(),
                ],
                'sale_id' => $sale->id,
                'pid' => $pid
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching sale history by PID', [
                'pid' => $pid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Error fetching sale history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sale history by sale master ID
     *
     * @param int $id Sale Master ID
     * @return JsonResponse
     */
    public function getHistoryById(int $id): JsonResponse
    {
        try {
            $sale = SalesMaster::find($id);

            if (!$sale) {
                return response()->json([
                    'status' => false,
                    'message' => 'Sale not found with ID: ' . $id
                ], 404);
            }

            // Get history with relationships
            $history = SaleMastersHistory::where('sale_master_id', $id)
                ->with(['changedBy:id,first_name,last_name,email'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'sale_master_id' => $item->sale_master_id,
                        'pid' => $item->pid,
                        'change_type' => $item->change_type,
                        'data_source_type' => $item->data_source_type,
                        'old_values' => $item->old_values,
                        'new_values' => $item->new_values,
                        'changed_fields' => $item->changed_fields,
                        'changed_by' => $item->changed_by,
                        'changed_by_user' => $item->changedBy
                            ? trim($item->changedBy->first_name . ' ' . $item->changedBy->last_name)
                            : 'System',
                        'ip_address' => $item->ip_address,
                        'user_agent' => $item->user_agent,
                        'reason' => $item->reason,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                    ];
                });

            return response()->json([
                'status' => true,
                'message' => 'Sale history retrieved successfully',
                'data' => $history,
                'count' => $history->count(),
                'sale_id' => $id,
                'pid' => $sale->pid
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching sale history by ID', [
                'sale_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Error fetching sale history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get history for a specific field across all sales
     *
     * @param string $field Field name
     * @return JsonResponse
     */
    public function getHistoryByField(string $field): JsonResponse
    {
        try {
            $history = SaleMastersHistory::whereJsonContains('changed_fields', $field)
                ->with(['changedBy:id,first_name,last_name,email', 'saleMaster:id,pid,customer_name'])
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Field history retrieved successfully',
                'data' => $history,
                'count' => $history->count(),
                'field' => $field
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching field history', [
                'field' => $field,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Error fetching field history'
            ], 500);
        }
    }

    /**
     * Get history by data source type
     *
     * @param string $sourceType Data source type
     * @return JsonResponse
     */
    public function getHistoryByDataSource(string $sourceType): JsonResponse
    {
        try {
            $history = SaleMastersHistory::where('data_source_type', $sourceType)
                ->with(['changedBy:id,first_name,last_name,email', 'saleMaster:id,pid,customer_name'])
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Data source history retrieved successfully',
                'data' => $history,
                'count' => $history->count(),
                'data_source_type' => $sourceType
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching data source history', [
                'source_type' => $sourceType,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Error fetching data source history'
            ], 500);
        }
    }

    /**
     * Beautify field name for display (dynamic based on company type)
     *
     * @param string $field
     * @return string
     */
    protected function beautifyFieldName(string $field): string
    {
        // Get company type for dynamic labels
        $companyProfile = \App\Models\CompanyProfile::first();
        $companyType = $companyProfile->company_type ?? 'Solar';

        // Base labels (non-worker fields)
        $customLabels = [
            'product_id' => 'Product',
            'product_code' => 'Product Code',
            'kw' => 'KW',
            'epc' => 'EPC',
            'net_epc' => 'Net EPC',
            'milestone_trigger' => 'Milestone Trigger',
            'customer_signoff' => 'Sale Date',
        ];

        // Dynamic worker field labels based on company type
        if (in_array($companyType, ['Pest', 'Fiber'])) {
            // Pest & Fiber: Sales Rep / Sales Setter
            $workerLabels = [
                'closer1_id' => 'Sales Rep 1',
                'closer2_id' => 'Sales Rep 2',
                'closer1_name' => 'Sales Rep 1 Name',
                'closer2_name' => 'Sales Rep 2 Name',
                'setter1_id' => 'Sales Setter 1',
                'setter2_id' => 'Sales Setter 2',
                'setter1_name' => 'Sales Setter 1 Name',
                'setter2_name' => 'Sales Setter 2 Name',
                'sales_rep_name' => 'Sales Rep Name',
                'sales_rep_email' => 'Sales Rep Email',
                'sales_setter_name' => 'Sales Setter Name',
            ];
        } elseif ($companyType === 'Mortgage') {
            // Mortgage: MLO / LOA
            $workerLabels = [
                'closer1_id' => 'MLO 1',
                'closer2_id' => 'MLO 2',
                'closer1_name' => 'MLO 1 Name',
                'closer2_name' => 'MLO 2 Name',
                'setter1_id' => 'LOA 1',
                'setter2_id' => 'LOA 2',
                'setter1_name' => 'LOA 1 Name',
                'setter2_name' => 'LOA 2 Name',
                'sales_rep_name' => 'MLO Name',
                'sales_rep_email' => 'MLO Email',
                'sales_setter_name' => 'LOA Name',
            ];
        } else {
            // Solar / Turf / Default: Closer / Setter
            $workerLabels = [
                'closer1_id' => 'Closer 1',
                'closer2_id' => 'Closer 2',
                'closer1_name' => 'Closer 1 Name',
                'closer2_name' => 'Closer 2 Name',
                'setter1_id' => 'Setter 1',
                'setter2_id' => 'Setter 2',
                'setter1_name' => 'Setter 1 Name',
                'setter2_name' => 'Setter 2 Name',
                'sales_rep_name' => 'Closer Rep Name',
                'sales_rep_email' => 'Closer Rep Email',
                'sales_setter_name' => 'Setter Rep Name',
            ];
        }

        // Merge base labels with dynamic worker labels
        $customLabels = array_merge($customLabels, $workerLabels);

        if (isset($customLabels[$field])) {
            return $customLabels[$field];
        }

        // Convert snake_case to Title Case
        return Str::title(str_replace('_', ' ', $field));
    }

    /**
     * Format field value for display
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @return mixed
     */
    protected function formatFieldValue(string $field, $value)
    {
        // Handle null values
        if ($value === null) {
            return 'null';
        }

        // Format product_id to product name
        if ($field === 'product_id' && is_numeric($value)) {
            $product = Products::find($value);
            return $product ? $product->name : $value;
        }

        // Format state_id to state name
        if ($field === 'state_id' && is_numeric($value)) {
            $state = \App\Models\State::find($value);
            return $state ? $state->name : $value;
        }

        // Format worker IDs to names
        if (in_array($field, ['closer1_id', 'closer2_id', 'setter1_id', 'setter2_id']) && is_numeric($value)) {
            $user = User::find($value);
            return $user ? trim($user->first_name . ' ' . $user->last_name) : 'User #' . $value;
        }

        // Format adders
        if ($field === 'adders' && $value) {
            return 'SOW';
        }

        // Format milestone_trigger JSON to readable string
        if ($field === 'milestone_trigger') {
            return $this->formatMilestoneTrigger($value);
        }

        // Format date fields - remove timestamp if present (ISO 8601 format)
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)) {
            // Extract date portion from ISO datetime string
            return substr($value, 0, 10); // Returns YYYY-MM-DD
        }

        return $value;
    }

    /**
     * Format milestone_trigger JSON to structured array for comparison
     *
     * @param mixed $milestones
     * @return array|string
     */
    protected function formatMilestoneTrigger($milestones)
    {
        // If null, return 'null' string for display
        if ($milestones === null) {
            return 'null';
        }

        if (is_string($milestones)) {
            $milestones = json_decode($milestones, true);
        }

        if (!is_array($milestones) || empty($milestones)) {
            return 'null';
        }

        // If already in the correct format (from legacy dates), return as is
        if (isset($milestones[0]['name']) && isset($milestones[0]['date']) && isset($milestones[0]['trigger'])) {
            return $milestones;
        }

        $formatted = [];
        foreach ($milestones as $milestone) {
            $name = $milestone['name'] ?? 'Unknown';
            $trigger = $milestone['trigger'] ?? $milestone['on_trigger'] ?? $name;
            $date = $milestone['date'] ?? 'Not set';
            $formatted[] = [
                'name' => $name,
                'trigger' => $trigger,
                'date' => $date,
            ];
        }

        return $formatted;
    }

    /**
     * Get milestone names for a product from milestone schema
     *
     * @param int $productId
     * @return array
     */
    protected function getMilestoneNames(int $productId): array
    {
        try {
            $product = Products::find($productId);

            if (!$product || !$product->milestone_schema_id) {
                // Fallback to default milestone schema (ID 1)
                $milestoneSchemaId = 1;
            } else {
                $milestoneSchemaId = $product->milestone_schema_id;
            }

            $milestones = \App\Models\MilestoneSchemaTrigger::where('milestone_schema_id', $milestoneSchemaId)
                ->orderBy('id')
                ->pluck('name')
                ->toArray();

            return $milestones;
        } catch (\Exception $e) {
            // Fallback to default names
            return ['M1 Date', 'M2 Date'];
        }
    }

    /**
     * Get milestone trigger name for a product
     *
     * @param int $productId
     * @param int $index
     * @return string
     */
    protected function getMilestoneTrigger(int $productId, int $index): string
    {
        try {
            $product = Products::find($productId);

            if (!$product || !$product->milestone_schema_id) {
                // Fallback to default milestone schema (ID 1)
                $milestoneSchemaId = 1;
            } else {
                $milestoneSchemaId = $product->milestone_schema_id;
            }

            $milestones = \App\Models\MilestoneSchemaTrigger::where('milestone_schema_id', $milestoneSchemaId)
                ->orderBy('id')
                ->get();

            if (isset($milestones[$index])) {
                return $milestones[$index]->on_trigger;
            }

            // Fallback
            return $index === 0 ? 'Initial Service Date' : 'Service Completion Date';
        } catch (\Exception $e) {
            // Fallback
            return $index === 0 ? 'Initial Service Date' : 'Service Completion Date';
        }
    }

    /**
     * Filter out unchanged milestones (where date didn't change)
     * Only show milestones where the date actually changed between old and new
     *
     * @param array $oldMilestones
     * @param array $newMilestones
     * @return array ['old' => filtered old array, 'new' => filtered new array]
     */
    protected function filterUnchangedMilestones($oldMilestones, $newMilestones): array
    {
        // If old is null/empty (null -> new milestones), show all new milestones without filtering
        if ((!is_array($oldMilestones) || empty($oldMilestones)) && is_array($newMilestones) && !empty($newMilestones)) {
            return ['old' => $oldMilestones, 'new' => $newMilestones];
        }

        // If new is null/empty (milestones -> null), show all old milestones without filtering
        if (is_array($oldMilestones) && !empty($oldMilestones) && (!is_array($newMilestones) || empty($newMilestones))) {
            return ['old' => $oldMilestones, 'new' => $newMilestones];
        }

        // If not both arrays, return as is
        if (!is_array($oldMilestones) || !is_array($newMilestones)) {
            return ['old' => $oldMilestones, 'new' => $newMilestones];
        }

        $filteredOld = [];
        $filteredNew = [];

        // Create a map of old milestones by trigger name for quick lookup
        $oldMap = [];
        foreach ($oldMilestones as $oldMilestone) {
            if (isset($oldMilestone['trigger']) && isset($oldMilestone['date'])) {
                $oldMap[$oldMilestone['trigger']] = $oldMilestone;
            }
        }

        // Compare new milestones with old ones
        foreach ($newMilestones as $newMilestone) {
            if (!isset($newMilestone['trigger']) || !isset($newMilestone['date'])) {
                // If missing required fields, include it
                $filteredNew[] = $newMilestone;
                continue;
            }

            $trigger = $newMilestone['trigger'];
            $newDate = $newMilestone['date'];

            // Check if this milestone exists in old values
            if (isset($oldMap[$trigger])) {
                $oldDate = $oldMap[$trigger]['date'];

                // Only include if dates are different
                if ($oldDate !== $newDate) {
                    $filteredOld[] = $oldMap[$trigger];
                    $filteredNew[] = $newMilestone;
                }
            } else {
                // New milestone not in old values, include it
                $filteredNew[] = $newMilestone;
            }
        }

        // If all milestones filtered out, return empty arrays
        return [
            'old' => $filteredOld,
            'new' => $filteredNew,
        ];
    }
}

