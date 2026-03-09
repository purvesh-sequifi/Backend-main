<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserCommissionAuditHistory;
use App\Models\UserRedlinesAuditHistory;
use App\Models\UserUpfrontAuditHistory;
use App\Models\UserWithheldAuditHistory;
use App\Models\UserSelfGenCommissionAuditHistory;
use App\Models\UserOverrideAuditHistory;
use App\Models\UserOrganizationAuditHistory;
use App\Models\UserTransferAuditHistory;
use App\Models\UserWagesAuditHistory;
use App\Models\UserDeductionAuditHistory;
use App\Models\Crmcustomfields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EmploymentCompensationHistoryController extends Controller
{
    /**
     * Category to Model mapping.
     */
    protected array $categoryModels = [
        'commission' => UserCommissionAuditHistory::class,
        'redlines' => UserRedlinesAuditHistory::class,
        'upfront' => UserUpfrontAuditHistory::class,
        'withheld' => UserWithheldAuditHistory::class,
        'selfgen' => UserSelfGenCommissionAuditHistory::class,
        'override' => UserOverrideAuditHistory::class,
        'organization' => UserOrganizationAuditHistory::class,
        'transfer' => UserTransferAuditHistory::class,
        'wages' => UserWagesAuditHistory::class,
        'deduction' => UserDeductionAuditHistory::class,
    ];

    /**
     * Category display names for API response.
     */
    protected array $categoryDisplayNames = [
        'commission' => 'Commission',
        'redlines' => 'Redlines',
        'upfront' => 'Upfront',
        'withheld' => 'Withholding',
        'selfgen' => 'Self Gen Commission',
        'override' => 'Override',
        'organization' => 'Organization',
        'transfer' => 'Transfer',
        'wages' => 'Wages',
        'deduction' => 'Deduction',
    ];

    /**
     * Get combined employment compensation history for a user.
     * API: GET /api/v2/combine_employment_compensation_history_tracking/{user_id}
     */
    public function index(Request $request, int $user_id): JsonResponse
    {
        // Validate user exists
        $user = User::find($user_id);
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found.',
            ], 404);
        }

        // Get filter parameters
        $filter = $request->input('filter');
        $changeType = $request->input('change_type');
        $changeSource = $request->input('change_source');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $perPage = (int) $request->input('per_page', 50);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $historyArray = [];
        $totalCount = 0;

        // Determine which categories to fetch
        $categoriesToFetch = $filter ? [$filter] : array_keys($this->categoryModels);

        foreach ($categoriesToFetch as $category) {
            if (!isset($this->categoryModels[$category])) {
                continue;
            }

            $modelClass = $this->categoryModels[$category];
            $categoryHistory = $this->fetchCategoryHistory(
                $modelClass,
                $category,
                $user_id,
                $changeType,
                $changeSource,
                $startDate,
                $endDate
            );

            foreach ($categoryHistory as $history) {
                $historyArray[] = $history;
            }
        }

        // Sort combined results
        $sortedHistory = collect($historyArray)->sortBy(function ($item) use ($sortBy, $sortOrder) {
            return $item[$sortBy] ?? $item['created_at'];
        }, SORT_REGULAR, $sortOrder === 'desc');

        // Paginate results
        $paginatedHistory = $sortedHistory->values()->slice(0, $perPage);

        return response()->json([
            'status' => true,
            'message' => 'Employment compensation history retrieved successfully.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->first_name . ' ' . $user->last_name,
                ],
                'history' => $paginatedHistory->values(),
                'total_count' => count($historyArray),
                'filters_applied' => [
                    'filter' => $filter,
                    'change_type' => $changeType,
                    'change_source' => $changeSource,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'available_categories' => array_keys($this->categoryModels),
                'available_change_types' => ['create', 'update', 'delete'],
                'available_change_sources' => ['user_profile', 'position_update', 'import', 'console'],
            ],
        ]);
    }

    /**
     * Fetch history for a specific category.
     */
    protected function fetchCategoryHistory(
        string $modelClass,
        string $category,
        int $userId,
        ?string $changeType,
        ?string $changeSource,
        ?string $startDate,
        ?string $endDate
    ): array {
        $query = $modelClass::with(['changedBy', 'sourceRecord.updater'])
            ->where('user_id', $userId);

        // Apply filters
        if ($changeType) {
            $query->where('change_type', $changeType);
        }

        if ($changeSource) {
            $query->where('change_source', $changeSource);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $query->orderBy('created_at', 'desc');

        $records = $query->get();
        $formattedHistory = [];

        foreach ($records as $history) {
            $changedFields = $history->changed_fields ?? [];

            // Get source_updater_id from the source record if available
            $sourceUpdaterId = null;
            $sourceUpdaterDisplay = null;
            if ($history->sourceRecord) {
                $sourceUpdaterId = $history->sourceRecord->updater_id ?? null;
                if ($sourceUpdaterId && $history->sourceRecord->updater) {
                    $updater = $history->sourceRecord->updater;
                    $sourceUpdaterDisplay = trim($updater->first_name . ' ' . $updater->last_name);
                }
            }

            foreach ($changedFields as $field) {
                $oldValue = $history->old_values[$field] ?? null;
                $newValue = $history->new_values[$field] ?? null;

                $formattedHistory[] = [
                    'id' => $history->id,
                    'source_id' => $history->source_id,
                    'source_updater_id' => $sourceUpdaterId,
                    'source_updater_display' => $sourceUpdaterDisplay,
                    'category' => $category,
                    'category_display' => $this->categoryDisplayNames[$category] ?? ucfirst($category),
                    'change_type' => $history->change_type,
                    'change_type_display' => $this->getChangeTypeDisplay($history->change_type),
                    'change_source' => $history->change_source,
                    'change_source_display' => $this->getChangeSourceDisplay($history->change_source),
                    'field' => $field,
                    'field_display' => $this->beautifyFieldName($field),
                    'old_value' => $this->formatFieldValue($field, $oldValue),
                    'new_value' => $this->formatFieldValue($field, $newValue),
                    'description' => $this->buildChangeDescription($category, $field, $oldValue, $newValue, $history->change_type),
                    'changed_by' => $history->changed_by,
                    'changed_by_display' => $this->getChangedByDisplay($history->changedBy, $history->change_source),
                    'reason' => $history->reason,
                    'ip_address' => $history->ip_address,
                    'created_at' => $history->created_at?->toIso8601String(),
                    'created_at_formatted' => $history->created_at?->format('M d, Y h:i A'),
                ];
            }
        }

        return $formattedHistory;
    }

    /**
     * Get display name for change type.
     */
    protected function getChangeTypeDisplay(?string $changeType): string
    {
        if ($changeType === null) {
            return 'Unknown';
        }

        return match ($changeType) {
            'create' => 'Created',
            'update' => 'Updated',
            'delete' => 'Deleted',
            default => ucfirst($changeType),
        };
    }

    /**
     * Get display name for change source.
     */
    protected function getChangeSourceDisplay(?string $changeSource): string
    {
        if ($changeSource === null) {
            return 'Unknown Source';
        }

        return match ($changeSource) {
            'user_profile' => 'User Profile Update',
            'position_update' => 'Position Bulk Update',
            'import' => 'Data Import',
            'console' => 'System Console',
            default => ucfirst(str_replace('_', ' ', $changeSource)),
        };
    }

    /**
     * Get display name for who made the change.
     * Format: "Admin Name" for user_profile, "Admin Name - Position Update" for position updates.
     */
    protected function getChangedByDisplay(?User $changedBy, ?string $changeSource): string
    {
        if (!$changedBy) {
            return 'System';
        }

        $adminName = trim($changedBy->first_name . ' ' . $changedBy->last_name);

        if (empty($adminName)) {
            $adminName = 'Admin #' . $changedBy->id;
        }

        if ($changedBy->is_super_admin) {
            $adminName = 'Super Admin - ' . $adminName;
        }

        if ($changeSource === 'position_update') {
            return $adminName . ' - Position Update';
        }

        if ($changeSource === 'import') {
            return $adminName . ' - Data Import';
        }

        if ($changeSource === 'console') {
            return 'System Process';
        }

        return $adminName;
    }

    /**
     * Build human-readable change description.
     */
    protected function buildChangeDescription(
        string $category,
        string $field,
        $oldValue,
        $newValue,
        string $changeType
    ): string {
        $categoryName = $this->categoryDisplayNames[$category] ?? ucfirst($category);
        $fieldName = $this->beautifyFieldName($field);

        $formattedOldValue = $this->formatFieldValue($field, $oldValue);
        $formattedNewValue = $this->formatFieldValue($field, $newValue);

        if ($changeType === 'create') {
            return "{$categoryName}: {$fieldName} set to {$formattedNewValue}";
        }

        if ($changeType === 'delete') {
            return "{$categoryName}: {$fieldName} was {$formattedOldValue} (deleted)";
        }

        return "{$categoryName}: {$fieldName} changed from {$formattedOldValue} to {$formattedNewValue}";
    }

    /**
     * Convert snake_case field name to human readable.
     */
    protected function beautifyFieldName(string $field): string
    {
        // Handle specific field mappings
        $fieldMappings = [
            'commission_effective_date' => 'Effective Date',
            'effective_end_date' => 'End Date',
            'upfront_effective_date' => 'Effective Date',
            'upfront_pay_amount' => 'Upfront Amount',
            'upfront_sale_type' => 'Sale Type',
            'withheld_amount' => 'Withholding Amount',
            'withheld_type' => 'Withholding Type',
            'withheld_effective_date' => 'Effective Date',
            'direct_overrides_amount' => 'Direct Override Amount',
            'direct_overrides_type' => 'Direct Override Type',
            'indirect_overrides_amount' => 'Indirect Override Amount',
            'indirect_overrides_type' => 'Indirect Override Type',
            'office_overrides_amount' => 'Office Override Amount',
            'office_overrides_type' => 'Office Override Type',
            'redline_amount_type' => 'Redline Amount Type',
            'self_gen_user' => 'Self Gen',
            'pay_type' => 'Pay Type',
            'pay_rate' => 'Pay Rate',
            'pay_rate_type' => 'Pay Rate Type',
            'pto_hours' => 'PTO Hours',
            'expected_weekly_hours' => 'Expected Weekly Hours',
            'overtime_rate' => 'Overtime Rate',
            'amount_par_paycheque' => 'Amount Per Paycheck',
            'cost_center_id' => 'Cost Center',
            'sub_position_id' => 'Sub Position',
            'position_id' => 'Position',
            'product_id' => 'Product',
            'manager_id' => 'Manager',
            'team_id' => 'Team',
            'state_id' => 'State',
            'office_id' => 'Office',
            'department_id' => 'Department',
            'is_manager' => 'Is Manager',
            'self_gen_accounts' => 'Self Gen Accounts',
        ];

        if (isset($fieldMappings[$field])) {
            return $fieldMappings[$field];
        }

        // Remove old_ prefix for display
        $displayField = preg_replace('/^old_/', '', $field);

        // Convert snake_case to Title Case
        return ucwords(str_replace('_', ' ', $displayField));
    }

    /**
     * Format field value for display.
     */
    protected function formatFieldValue(string $field, $value)
    {
        if ($value === null || $value === '') {
            return 'N/A';
        }

        // Format dates
        if (str_contains($field, '_date') || str_contains($field, 'created_at') || str_contains($field, 'updated_at')) {
            if (is_string($value)) {
                try {
                    return date('M d, Y', strtotime($value));
                } catch (\Exception $e) {
                    return $value;
                }
            }
        }

        // Format amounts with percentage or dollar
        if (str_contains($field, 'amount') || str_contains($field, 'rate') || $field === 'commission' || $field === 'redline') {
            if (is_numeric($value)) {
                // Check if it's a percentage type
                $typeField = str_replace(['amount', 'rate'], 'type', $field);
                return number_format((float) $value, 2);
            }
        }

        // Format boolean values
        if (str_contains($field, 'is_') || $field === 'self_gen_user' || $field === 'self_gen_accounts') {
            return $value ? 'Yes' : 'No';
        }

        // Format type fields
        if (str_contains($field, '_type')) {
            return match ($value) {
                'percentage', '%' => 'Percentage',
                'flat', '$' => 'Flat Amount',
                'hourly' => 'Hourly',
                'salary' => 'Salary',
                default => ucfirst((string) $value),
            };
        }

        return $value;
    }

    /**
     * Get history for a specific category only.
     * API: GET /api/v2/combine_employment_compensation_history_tracking/{user_id}/category/{category}
     */
    public function byCategory(Request $request, int $user_id, string $category): JsonResponse
    {
        if (!isset($this->categoryModels[$category])) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid category. Available categories: ' . implode(', ', array_keys($this->categoryModels)),
            ], 400);
        }

        $request->merge(['filter' => $category]);

        return $this->index($request, $user_id);
    }

    /**
     * Get combined history in legacy format (grouped by month) for frontend compatibility.
     * API: POST /api/v2/combine_employment_compensation_history_tracking/legacy
     *
     * This reads from SOURCE tables directly (like the old API) because:
     * - Source tables already store old/new values (e.g., old_commission, commission)
     * - Our audit tables only track UPDATE/DELETE events (when someone edits/deletes existing history)
     */
    public function legacyFormat(Request $request): JsonResponse
    {
        $userId = (int) $request->input('user_id');
        $filter = $request->input('filter');
        $productId = $request->input('product_id') ? (int) $request->input('product_id') : null;
        $updaterId = $request->input('updater_id') ? (int) $request->input('updater_id') : null;
        $effectiveDate = $request->input('effective_date');
        $sortBy = $request->input('sort_by');
        $sortType = $request->input('sort_type', 'desc');
        $futureOnly = (bool) $request->input('future_only', false);

        $historyArray = [];

        // Handle each filter type by reading from SOURCE tables (like old API)
        if (empty($filter) || $filter === 'Commissions') {
            $historyArray = array_merge($historyArray, $this->getCommissionHistory($userId, $productId, $updaterId, $effectiveDate, $futureOnly));
        }

        if (empty($filter) || $filter === 'Redlines') {
            $historyArray = array_merge($historyArray, $this->getRedlineHistory($userId, $productId, $updaterId, $effectiveDate, $futureOnly));
        }

        if (empty($filter) || $filter === 'Upfronts') {
            $historyArray = array_merge($historyArray, $this->getUpfrontHistory($userId, $productId, $updaterId, $effectiveDate, $futureOnly));
        }

        if (empty($filter) || $filter === 'Withholdings') {
            $historyArray = array_merge($historyArray, $this->getWithheldHistory($userId, $productId, $updaterId, $effectiveDate, $futureOnly));
        }

        if (empty($filter) || $filter === 'Overrides') {
            $historyArray = array_merge($historyArray, $this->getOverrideHistory($userId, $productId, $updaterId, $effectiveDate, $futureOnly));
        }

        if (empty($filter) || $filter === 'Organizations') {
            $historyArray = array_merge($historyArray, $this->getOrganizationHistory($userId, $productId, $updaterId, $effectiveDate, $futureOnly));
        }

        // When no filter, include Position and Self Gen from UserOrganizationHistory
        if (empty($filter)) {
            $historyArray = array_merge($historyArray, $this->getPositionAndSelfGenHistory($userId, $productId, $updaterId, $effectiveDate, $futureOnly));
        }

        if (empty($filter) || $filter === 'Transfers') {
            $historyArray = array_merge($historyArray, $this->getTransferHistory($userId, $productId, $updaterId, $effectiveDate, $futureOnly));
        }

        if (empty($filter) || $filter === 'Wages') {
            $historyArray = array_merge($historyArray, $this->getWagesHistory($userId, $productId, $updaterId, $effectiveDate, $futureOnly));
        }

        if (empty($filter) || $filter === 'Deductions') {
            $historyArray = array_merge($historyArray, $this->getDeductionHistory($userId, $productId, $updaterId, $effectiveDate, $futureOnly));
        }

        if (empty($filter) || $filter === 'Self Gen Commissions') {
            $historyArray = array_merge($historyArray, $this->getSelfGenCommissionHistory($userId, $productId, $updaterId, $effectiveDate, $futureOnly));
        }

        // Handle Personal, Banking, Tax, Employment filters
        if ($filter === 'Personal') {
            $historyArray = $this->getPersonalInfoHistory($userId, $updaterId, $effectiveDate);
        }

        if ($filter === 'Banking') {
            $historyArray = $this->getBankingInfoHistory($userId, $updaterId, $effectiveDate);
        }

        if ($filter === 'Tax') {
            $historyArray = $this->getTaxInfoHistory($userId, $updaterId, $effectiveDate);
        }

        if ($filter === 'Employment') {
            $historyArray = $this->getEmploymentStatusHistory($userId, $updaterId, $effectiveDate);
        }

        // Sort by effective_date descending
        usort($historyArray, function ($a, $b) use ($sortType) {
            $dateA = strtotime($a['effective_date'] ?? '1970-01-01');
            $dateB = strtotime($b['effective_date'] ?? '1970-01-01');
            return $sortType === 'asc' ? $dateA - $dateB : $dateB - $dateA;
        });

        // Group by month
        $groupedData = [];
        foreach ($historyArray as $item) {
            $monthKey = date('F Y', strtotime($item['effective_date'] ?? now()));
            if (!isset($groupedData[$monthKey])) {
                $groupedData[$monthKey] = [];
            }
            $groupedData[$monthKey][] = $item;
        }

        return response()->json([
            'ApiName' => 'combine_employment_compensation_history_tracking',
            'status' => true,
            'message' => 'Successfully',
            'data' => $groupedData,
        ], 200);
    }

    /**
     * Get commission history from SOURCE table (matches OLD API).
     */
    protected function getCommissionHistory(int $userId, ?int $productId, ?int $updaterId, ?string $effectiveDate, bool $futureOnly): array
    {
        $query = \App\Models\UserCommissionHistory::with('product:id,name', 'updater', 'subposition', 'position')
            ->where('user_id', $userId);

        if ($productId) {
            $query->where('product_id', $productId);
        }
        if ($futureOnly) {
            $query->where('commission_effective_date', '>', date('Y-m-d'));
        }
        if ($updaterId) {
            $query->where('updater_id', $updaterId);
        }
        if ($effectiveDate) {
            $query->where('commission_effective_date', $effectiveDate);
        }

        $records = $query->get();
        $history = [];

        foreach ($records as $commission_history) {
            if (($commission_history->old_commission != null && $commission_history->old_commission != '') || ($commission_history->commission != null && $commission_history->commission != '')) {
                // Get commission type display value (with custom field support)
                $commissionType = $this->getCommissionTypeDisplay($commission_history->commission_type, $commission_history->custom_sales_field_id);
                $oldCommissionType = $this->getCommissionTypeDisplay($commission_history->old_commission_type, $commission_history->old_custom_sales_field_id ?? null);

                $changes = 'Commissions changed from '.(!empty($commission_history->old_commission) ? $commission_history->old_commission : 0)."{$oldCommissionType} to {$commission_history->commission}{$commissionType} for {$commission_history->position->position_name}";

                $history[] = [
                    'id' => $commission_history->id,
                    'effective_date' => $commission_history->commission_effective_date,
                    'type' => 'Commission',
                    'product' => $commission_history->product->name,
                    'product_id' => $commission_history->product->id,
                    'updated_on' => $commission_history->updated_at,
                    'description' => $changes,
                    'updater' => $commission_history->updater,
                ];
            }
        }

        return $history;
    }

    /**
     * Get redline history from source table.
     */
    protected function getRedlineHistory(int $userId, ?int $productId, ?int $updaterId, ?string $effectiveDate, bool $futureOnly): array
    {
        $query = \App\Models\UserRedlines::with(['product:id,name', 'updater', 'position'])
            ->where('user_id', $userId);

        if ($futureOnly) {
            $query->where('start_date', '>', date('Y-m-d'));
        }
        if ($updaterId) {
            $query->where('updater_id', $updaterId);
        }
        if ($effectiveDate) {
            $query->where('start_date', $effectiveDate);
        }

        $records = $query->get();
        $history = [];

        foreach ($records as $record) {
            if (($record->old_redline !== null && $record->old_redline !== '') ||
                ($record->redline !== null && $record->redline !== '')) {

                $positionName = $record->position->position_name ?? 'Unknown Position';
                $type = $record->redline_amount_type === 'Fixed' ? 'Fixed Redline' : 'Location Redline';
                $oldValue = $record->old_redline ?? 0;
                $newValue = $record->redline ?? 0;

                $description = "{$type} changed from {$oldValue} {$record->old_redline_type} to {$newValue} {$record->redline_type} for {$positionName}";

                $history[] = [
                    'id' => $record->id,
                    'effective_date' => $record->start_date,
                    'type' => $type,
                    'product' => $record->product->name ?? null,
                    'product_id' => $record->product->id ?? null,
                    'updated_on' => $record->updated_at?->toDateTimeString(),
                    'description' => $description,
                    'updater' => $record->updater,
                ];
            }
        }

        return $history;
    }

    /**
     * Get upfront history from SOURCE table (matches OLD API).
     */
    protected function getUpfrontHistory(int $userId, ?int $productId, ?int $updaterId, ?string $effectiveDate, bool $futureOnly): array
    {
        $query = \App\Models\UserUpfrontHistory::with('product:id,name', 'updater', 'subposition', 'position', 'schema')
            ->where('user_id', $userId);

        if ($productId) {
            $query->where('product_id', $productId);
        }
        if ($futureOnly) {
            $query->where('upfront_effective_date', '>', date('Y-m-d'));
        }
        if ($updaterId) {
            $query->where('updater_id', $updaterId);
        }
        if ($effectiveDate) {
            $query->where('upfront_effective_date', $effectiveDate);
        }

        $records = $query->get();
        $history = [];

        foreach ($records as $upfront_history) {
            if (($upfront_history->old_upfront_pay_amount != null && $upfront_history->old_upfront_pay_amount != '') || ($upfront_history->upfront_pay_amount != null && $upfront_history->upfront_pay_amount != '')) {
                $position_display_name = $upfront_history->position_display_name;

                // Get upfront sale type display value (with custom field support)
                $upfrontSaleType = $this->getTypeDisplayForAudit($upfront_history->upfront_sale_type, $upfront_history->custom_sales_field_id ?? null);
                $oldUpfrontSaleType = $this->getTypeDisplayForAudit($upfront_history->old_upfront_sale_type, null); // old doesn't have custom field id stored separately

                $changes = 'Upfront changed from '.(!empty($upfront_history->old_upfront_pay_amount) ? $upfront_history->old_upfront_pay_amount : 0)."{$oldUpfrontSaleType} to {$upfront_history->upfront_pay_amount}{$upfrontSaleType} for {$position_display_name} on milestone {$upfront_history->schema->name}";

                $history[] = [
                    'id' => $upfront_history->id,
                    'effective_date' => $upfront_history->upfront_effective_date,
                    'type' => 'Upfront',
                    'product' => $upfront_history->product->name,
                    'product_id' => $upfront_history->product->id,
                    'updated_on' => $upfront_history->updated_at,
                    'description' => $changes,
                    'updater' => $upfront_history->updater,
                ];
            }
        }

        return $history;
    }

    /**
     * Get withheld history from source table.
     */
    protected function getWithheldHistory(int $userId, ?int $productId, ?int $updaterId, ?string $effectiveDate, bool $futureOnly): array
    {
        $query = \App\Models\UserWithheldHistory::with(['product:id,name', 'updater', 'position'])
            ->where('user_id', $userId);

        if ($productId) {
            $query->where('product_id', $productId);
        }
        if ($futureOnly) {
            $query->where('withheld_effective_date', '>', date('Y-m-d'));
        }
        if ($updaterId) {
            $query->where('updater_id', $updaterId);
        }
        if ($effectiveDate) {
            $query->where('withheld_effective_date', $effectiveDate);
        }

        $records = $query->get();
        $history = [];

        foreach ($records as $record) {
            if (($record->old_withheld_amount !== null && $record->old_withheld_amount !== '') ||
                ($record->withheld_amount !== null && $record->withheld_amount !== '')) {

                $positionName = $record->position->position_name ?? 'Unknown Position';
                $oldValue = $record->old_withheld_amount ?? 0;
                $newValue = $record->withheld_amount ?? 0;

                $description = "Withholding changed from {$oldValue} to {$newValue} for {$positionName}";

                $history[] = [
                    'id' => $record->id,
                    'effective_date' => $record->withheld_effective_date,
                    'type' => 'Withholding',
                    'product' => $record->product->name ?? null,
                    'product_id' => $record->product->id ?? null,
                    'updated_on' => $record->updated_at?->toDateTimeString(),
                    'description' => $description,
                    'updater' => $record->updater,
                ];
            }
        }

        return $history;
    }

    /**
     * Get override history from SOURCE table (matches OLD API).
     */
    protected function getOverrideHistory(int $userId, ?int $productId, ?int $updaterId, ?string $effectiveDate, bool $futureOnly): array
    {
        $query = \App\Models\UserOverrideHistory::with('product:id,name', 'updater')
            ->where('user_id', $userId);

        if ($futureOnly) {
            $query->where('override_effective_date', '>', date('Y-m-d'));
        }

        $records = $query->get();
        $history = [];

        foreach ($records as $override_history) {
            // Skip records without a valid product
            if (!$override_history->product) {
                continue;
            }

            // Get override type display values (with custom field support)
            // Each override type has its own custom sales field ID column
            $directType = $this->getTypeDisplayForAudit($override_history->direct_overrides_type, $override_history->direct_custom_sales_field_id ?? null);
            $oldDirectType = $this->getTypeDisplayForAudit($override_history->old_direct_overrides_type, null);
            $indirectType = $this->getTypeDisplayForAudit($override_history->indirect_overrides_type, $override_history->indirect_custom_sales_field_id ?? null);
            $oldIndirectType = $this->getTypeDisplayForAudit($override_history->old_indirect_overrides_type, null);
            $officeType = $this->getTypeDisplayForAudit($override_history->office_overrides_type, $override_history->office_custom_sales_field_id ?? null);
            $oldOfficeType = $this->getTypeDisplayForAudit($override_history->old_office_overrides_type, null);

            // Direct Override
            if ($override_history->old_direct_overrides_amount.' '.$override_history->old_direct_overrides_type != $override_history->direct_overrides_amount.' '.$override_history->direct_overrides_type) {
                $changes = 'Direct Override changed from '.(!empty($override_history->old_direct_overrides_amount) ? $override_history->old_direct_overrides_amount : 0)."{$oldDirectType} to {$override_history->direct_overrides_amount}{$directType}";
                $history[] = [
                    'id' => $override_history->id,
                    'effective_date' => $override_history->override_effective_date,
                    'type' => 'Direct Override',
                    'product' => $override_history->product->name,
                    'product_id' => $override_history->product->id,
                    'updated_on' => $override_history->updated_at,
                    'description' => $changes,
                    'updater' => $override_history->updater,
                ];
            }

            // Indirect Override
            if ($override_history->old_indirect_overrides_amount.' '.$override_history->old_indirect_overrides_type != $override_history->indirect_overrides_amount.' '.$override_history->indirect_overrides_type) {
                $changes = 'Indirect Override changed from '.(!empty($override_history->old_indirect_overrides_amount) ? $override_history->old_indirect_overrides_amount : 0)."{$oldIndirectType} to {$override_history->indirect_overrides_amount}{$indirectType}";
                $history[] = [
                    'id' => $override_history->id,
                    'effective_date' => $override_history->override_effective_date,
                    'type' => 'Indirect Override',
                    'product' => $override_history->product->name,
                    'product_id' => $override_history->product->id,
                    'updated_on' => $override_history->updated_at,
                    'description' => $changes,
                    'updater' => $override_history->updater,
                ];
            }

            // Office Override
            if ($override_history->old_office_overrides_amount.' '.$override_history->old_office_overrides_type != $override_history->office_overrides_amount.' '.$override_history->office_overrides_type) {
                $changes = 'Office Override changed from '.(!empty($override_history->old_office_overrides_amount) ? $override_history->old_office_overrides_amount : 0)."{$oldOfficeType} to {$override_history->office_overrides_amount}{$officeType}";
                $history[] = [
                    'id' => $override_history->id,
                    'effective_date' => $override_history->override_effective_date,
                    'type' => 'Office Override',
                    'product' => $override_history->product->name,
                    'product_id' => $override_history->product->id,
                    'updated_on' => $override_history->updated_at,
                    'description' => $changes,
                    'updater' => $override_history->updater,
                ];
            }

            // Office Stack Override
            if ($override_history->old_office_stack_overrides_amount != $override_history->office_stack_overrides_amount) {
                $changes = 'Office Stack Override changed from '.(!empty($override_history->old_office_stack_overrides_amount) ? $override_history->old_office_stack_overrides_amount : 0)."percent to {$override_history->office_stack_overrides_amount} percent";
                $history[] = [
                    'id' => $override_history->id,
                    'effective_date' => $override_history->override_effective_date,
                    'type' => 'Office Stack Override',
                    'product' => $override_history->product->name,
                    'product_id' => $override_history->product->id,
                    'updated_on' => $override_history->updated_at,
                    'description' => $changes,
                    'updater' => $override_history->updater,
                ];
            }
        }

        return $history;
    }

    /**
     * OLD legacyFormat method - reads from audit tables (kept for reference)
     */
    public function legacyFormatFromAuditTables(Request $request): JsonResponse
    {
        $userId = $request->input('user_id');
        $filter = $request->input('filter');
        $productId = $request->input('product_id');
        $updaterId = $request->input('updater_id');
        $effectiveDate = $request->input('effective_date');
        $sortBy = $request->input('sort_by');
        $sortType = $request->input('sort_type', 'desc');

        // Map frontend filter names to our category names
        $filterMapping = [
            'Commissions' => 'commission',
            'Redlines' => 'redlines',
            'Upfronts' => 'upfront',
            'Withholdings' => 'withheld',
            'Overrides' => 'override',
            'Organizations' => 'organization',
            'Transfers' => 'transfer',
            'Wages' => 'wages',
            'Deductions' => 'deduction',
            'Personal' => null,
            'Banking' => null,
            'Tax' => null,
            'Employment' => null,
        ];

        if (isset($filterMapping[$filter]) && $filterMapping[$filter] === null) {
            return response()->json([]);
        }

        $category = $filterMapping[$filter] ?? null;

        if (!$category || !isset($this->categoryModels[$category])) {
            return response()->json([]);
        }

        $modelClass = $this->categoryModels[$category];

        $query = $modelClass::with(['changedBy', 'sourceRecord.updater'])
            ->where('user_id', $userId);

        if ($updaterId) {
            $query->where('changed_by', $updaterId);
        }

        if ($effectiveDate) {
            $query->whereDate('created_at', $effectiveDate);
        }

        if ($sortBy === 'sort_effective_date') {
            $query->orderBy('created_at', $sortType);
        } elseif ($sortBy === 'sort_date') {
            $query->orderBy('created_at', $sortType);
        } elseif ($sortBy === 'sort_user') {
            $query->orderBy('changed_by', $sortType);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $records = $query->get();

        // Group by month (format: "January 2025")
        $groupedData = [];

        // Define primary fields for each category (the main value to show for CREATE/DELETE)
        $primaryFields = [
            'commission' => 'commission',
            'redlines' => 'redline',
            'upfront' => 'upfront_pay_amount',
            'withheld' => 'withheld_amount',
            'override' => 'direct_overrides_amount',
            'organization' => 'manager_id',
            'transfer' => 'office_id',
            'wages' => 'pay_rate',
            'deduction' => 'amount_par_paycheque',
            'selfgen' => 'commission',
        ];

        foreach ($records as $history) {
            $monthKey = $history->created_at?->format('F Y') ?? 'Unknown';

            if (!isset($groupedData[$monthKey])) {
                $groupedData[$monthKey] = [];
            }

            $changedFields = $history->changed_fields ?? [];
            $changeType = $history->change_type;

            // For CREATE and DELETE: Show ONE summary entry only
            if ($changeType === 'create' || $changeType === 'delete') {
                $primaryField = $primaryFields[$category] ?? ($changedFields[0] ?? 'value');
                $oldValue = $history->old_values[$primaryField] ?? null;
                $newValue = $history->new_values[$primaryField] ?? null;

                // Build a summary description
                $description = $this->buildLegacySummaryDescription(
                    $category,
                    $history->new_values ?? $history->old_values ?? [],
                    $changeType,
                    $history->change_source
                );

                $groupedData[$monthKey][] = [
                    'id' => $history->id,
                    'effective_date' => $history->created_at?->toDateString(),
                    'description' => $description,
                    'updated_on' => $history->created_at?->toDateTimeString(),
                    'type' => $this->categoryDisplayNames[$category] ?? ucfirst($category),
                    'change_type' => $changeType,
                    'change_source' => $history->change_source,
                    'reason' => $history->reason,
                    'updater' => $history->changedBy ? [
                        'id' => $history->changedBy->id,
                        'first_name' => $history->changedBy->first_name,
                        'last_name' => $history->changedBy->last_name,
                        'image' => $history->changedBy->image,
                        'position_id' => $history->changedBy->position_id,
                        'sub_position_id' => $history->changedBy->sub_position_id,
                        'is_super_admin' => $history->changedBy->is_super_admin,
                        'is_manager' => $history->changedBy->is_manager,
                    ] : null,
                    'current_status' => false,
                ];

                continue; // Skip to next record
            }

            // For UPDATE: Show one entry per changed field
            foreach ($changedFields as $field) {
                $oldValue = $history->old_values[$field] ?? null;
                $newValue = $history->new_values[$field] ?? null;

                // Build description matching existing format
                $description = $this->buildLegacyDescription(
                    $category,
                    $field,
                    $oldValue,
                    $newValue,
                    $history->change_type,
                    $history->change_source
                );

                $groupedData[$monthKey][] = [
                    'id' => $history->id,
                    'effective_date' => $history->created_at?->toDateString(),
                    'description' => $description,
                    'updated_on' => $history->created_at?->toDateTimeString(),
                    'type' => $this->categoryDisplayNames[$category] ?? ucfirst($category),
                    'change_type' => $history->change_type,
                    'change_source' => $history->change_source,
                    'reason' => $history->reason,
                    'updater' => $history->changedBy ? [
                        'id' => $history->changedBy->id,
                        'first_name' => $history->changedBy->first_name,
                        'last_name' => $history->changedBy->last_name,
                        'image' => $history->changedBy->image,
                        'position_id' => $history->changedBy->position_id,
                        'sub_position_id' => $history->changedBy->sub_position_id,
                        'is_super_admin' => $history->changedBy->is_super_admin,
                        'is_manager' => $history->changedBy->is_manager,
                    ] : null,
                    'current_status' => $history->change_type === 'create',
                ];
            }
        }

        return response()->json([
            'ApiName' => 'combine_employment_compensation_history_tracking',
            'status' => true,
            'message' => 'Successfully',
            'data' => $groupedData,
        ], 200);
    }

    /**
     * Build legacy description format with HTML highlighting.
     */
    protected function buildLegacyDescription(
        string $category,
        string $field,
        $oldValue,
        $newValue,
        string $changeType,
        ?string $changeSource
    ): string {
        $categoryName = $this->categoryDisplayNames[$category] ?? ucfirst($category);
        $fieldName = $this->beautifyFieldName($field);

        $formattedOldValue = $this->formatFieldValue($field, $oldValue);
        $formattedNewValue = $this->formatFieldValue($field, $newValue);

        // Add source indicator
        $sourceLabel = '';
        if ($changeSource === 'position_update') {
            $sourceLabel = ' <span class="badge bg-warning text-dark">Position Update</span>';
        }

        if ($changeType === 'create') {
            return "<strong>{$categoryName}:</strong> {$fieldName} set to <span class='text-success fw-bold'>{$formattedNewValue}</span>{$sourceLabel}";
        }

        if ($changeType === 'delete') {
            return "<strong>{$categoryName}:</strong> {$fieldName} <span class='text-danger fw-bold'>{$formattedOldValue}</span> (deleted){$sourceLabel}";
        }

        return "<strong>{$categoryName}:</strong> {$fieldName} changed from <span class='text-danger'>{$formattedOldValue}</span> to <span class='text-success fw-bold'>{$formattedNewValue}</span>{$sourceLabel}";
    }

    /**
     * Build summary description for CREATE/DELETE events (one entry per record).
     */
    protected function buildLegacySummaryDescription(
        string $category,
        array $values,
        string $changeType,
        ?string $changeSource
    ): string {
        $categoryName = $this->categoryDisplayNames[$category] ?? ucfirst($category);

        // Add source indicator
        $sourceLabel = '';
        if ($changeSource === 'position_update') {
            $sourceLabel = ' <span class="badge bg-warning text-dark">Position Update</span>';
        }

        // Build summary based on category
        $summary = match ($category) {
            'commission' => $this->buildCommissionSummary($values, $changeType),
            'redlines' => $this->buildRedlineSummary($values, $changeType),
            'upfront' => $this->buildUpfrontSummary($values, $changeType),
            'withheld' => $this->buildWithheldSummary($values, $changeType),
            'override' => $this->buildOverrideSummary($values, $changeType),
            'wages' => $this->buildWagesSummary($values, $changeType),
            'deduction' => $this->buildDeductionSummary($values, $changeType),
            'organization' => $this->buildOrganizationSummary($values, $changeType),
            'transfer' => $this->buildTransferSummary($values, $changeType),
            'selfgen' => $this->buildSelfGenSummary($values, $changeType),
            default => $this->buildGenericSummary($categoryName, $values, $changeType),
        };

        return $summary . $sourceLabel;
    }

    protected function buildCommissionSummary(array $values, string $changeType): string
    {
        $commission = $values['commission'] ?? 'N/A';
        $type = $values['commission_type'] ?? '%';
        $formatted = number_format((float)$commission, 2) . ' ' . $this->formatFieldValue('commission_type', $type);

        if ($changeType === 'create') {
            return "<strong>Commission:</strong> New commission set to <span class='text-success fw-bold'>{$formatted}</span>";
        }
        return "<strong>Commission:</strong> Commission <span class='text-danger fw-bold'>{$formatted}</span> was deleted";
    }

    protected function buildRedlineSummary(array $values, string $changeType): string
    {
        $redline = $values['redline'] ?? 'N/A';
        $type = $values['redline_type'] ?? '%';
        $formatted = number_format((float)$redline, 2) . ' ' . $this->formatFieldValue('redline_type', $type);

        if ($changeType === 'create') {
            return "<strong>Redline:</strong> New redline set to <span class='text-success fw-bold'>{$formatted}</span>";
        }
        return "<strong>Redline:</strong> Redline <span class='text-danger fw-bold'>{$formatted}</span> was deleted";
    }

    protected function buildUpfrontSummary(array $values, string $changeType): string
    {
        $amount = $values['upfront_pay_amount'] ?? 'N/A';
        $type = $values['upfront_sale_type'] ?? '';
        $formatted = number_format((float)$amount, 2) . ' ' . ucfirst($type);

        if ($changeType === 'create') {
            return "<strong>Upfront:</strong> New upfront set to <span class='text-success fw-bold'>{$formatted}</span>";
        }
        return "<strong>Upfront:</strong> Upfront <span class='text-danger fw-bold'>{$formatted}</span> was deleted";
    }

    protected function buildWithheldSummary(array $values, string $changeType): string
    {
        $amount = $values['withheld_amount'] ?? 'N/A';
        $type = $values['withheld_type'] ?? '%';
        $formatted = number_format((float)$amount, 2) . ' ' . $this->formatFieldValue('withheld_type', $type);

        if ($changeType === 'create') {
            return "<strong>Withholding:</strong> New withholding set to <span class='text-success fw-bold'>{$formatted}</span>";
        }
        return "<strong>Withholding:</strong> Withholding <span class='text-danger fw-bold'>{$formatted}</span> was deleted";
    }

    protected function buildOverrideSummary(array $values, string $changeType): string
    {
        $direct = $values['direct_overrides_amount'] ?? 0;
        $indirect = $values['indirect_overrides_amount'] ?? 0;
        $office = $values['office_overrides_amount'] ?? 0;

        $parts = [];
        if ($direct) $parts[] = "Direct: " . number_format((float)$direct, 2);
        if ($indirect) $parts[] = "Indirect: " . number_format((float)$indirect, 2);
        if ($office) $parts[] = "Office: " . number_format((float)$office, 2);

        $formatted = implode(', ', $parts) ?: 'N/A';

        if ($changeType === 'create') {
            return "<strong>Override:</strong> New override set (<span class='text-success fw-bold'>{$formatted}</span>)";
        }
        return "<strong>Override:</strong> Override (<span class='text-danger fw-bold'>{$formatted}</span>) was deleted";
    }

    protected function buildWagesSummary(array $values, string $changeType): string
    {
        $rate = $values['pay_rate'] ?? 'N/A';
        $type = $values['pay_type'] ?? '';
        $formatted = '$' . number_format((float)$rate, 2) . ' ' . ucfirst($type);

        if ($changeType === 'create') {
            return "<strong>Wages:</strong> New wages set to <span class='text-success fw-bold'>{$formatted}</span>";
        }
        return "<strong>Wages:</strong> Wages <span class='text-danger fw-bold'>{$formatted}</span> was deleted";
    }

    protected function buildDeductionSummary(array $values, string $changeType): string
    {
        $amount = $values['amount_par_paycheque'] ?? 'N/A';
        $formatted = '$' . number_format((float)$amount, 2) . '/paycheck';

        if ($changeType === 'create') {
            return "<strong>Deduction:</strong> New deduction set to <span class='text-success fw-bold'>{$formatted}</span>";
        }
        return "<strong>Deduction:</strong> Deduction <span class='text-danger fw-bold'>{$formatted}</span> was deleted";
    }

    protected function buildOrganizationSummary(array $values, string $changeType): string
    {
        $managerId = $values['manager_id'] ?? null;
        $teamId = $values['team_id'] ?? null;

        if ($changeType === 'create') {
            return "<strong>Organization:</strong> New organization assignment <span class='text-success fw-bold'>created</span>";
        }
        return "<strong>Organization:</strong> Organization assignment was <span class='text-danger fw-bold'>deleted</span>";
    }

    protected function buildTransferSummary(array $values, string $changeType): string
    {
        if ($changeType === 'create') {
            return "<strong>Transfer:</strong> New transfer record <span class='text-success fw-bold'>created</span>";
        }
        return "<strong>Transfer:</strong> Transfer record was <span class='text-danger fw-bold'>deleted</span>";
    }

    protected function buildSelfGenSummary(array $values, string $changeType): string
    {
        $commission = $values['commission'] ?? 'N/A';
        $type = $values['commission_type'] ?? '%';
        $formatted = number_format((float)$commission, 2) . ' ' . $this->formatFieldValue('commission_type', $type);

        if ($changeType === 'create') {
            return "<strong>Self-Gen Commission:</strong> New commission set to <span class='text-success fw-bold'>{$formatted}</span>";
        }
        return "<strong>Self-Gen Commission:</strong> Commission <span class='text-danger fw-bold'>{$formatted}</span> was deleted";
    }

    protected function buildGenericSummary(string $categoryName, array $values, string $changeType): string
    {
        if ($changeType === 'create') {
            return "<strong>{$categoryName}:</strong> New record <span class='text-success fw-bold'>created</span>";
        }
        return "<strong>{$categoryName}:</strong> Record was <span class='text-danger fw-bold'>deleted</span>";
    }

    /**
     * Get audit trail statistics for a user.
     * API: GET /api/v2/combine_employment_compensation_history_tracking/{user_id}/stats
     */
    public function stats(int $user_id): JsonResponse
    {
        $user = User::find($user_id);
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $stats = [];

        foreach ($this->categoryModels as $category => $modelClass) {
            $categoryStats = [
                'total' => $modelClass::where('user_id', $user_id)->count(),
                'creates' => $modelClass::where('user_id', $user_id)->where('change_type', 'create')->count(),
                'updates' => $modelClass::where('user_id', $user_id)->where('change_type', 'update')->count(),
                'deletes' => $modelClass::where('user_id', $user_id)->where('change_type', 'delete')->count(),
                'by_user_profile' => $modelClass::where('user_id', $user_id)->where('change_source', 'user_profile')->count(),
                'by_position_update' => $modelClass::where('user_id', $user_id)->where('change_source', 'position_update')->count(),
                'last_change' => $modelClass::where('user_id', $user_id)->orderBy('created_at', 'desc')->first()?->created_at?->toIso8601String(),
            ];

            $stats[$category] = $categoryStats;
        }

        return response()->json([
            'status' => true,
            'message' => 'Statistics retrieved successfully.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->first_name . ' ' . $user->last_name,
                ],
                'statistics' => $stats,
            ],
        ]);
    }

    /**
     * Get Personal Info history (matches OLD API - one entry per field).
     */
    protected function getPersonalInfoHistory(int $userId, ?int $updaterId, ?string $effectiveDate): array
    {
        $query = \App\Models\UserPersonalInfoHistory::with('changedBy')
            ->where('user_id', $userId);

        if ($updaterId) {
            $query->where('changed_by', $updaterId);
        }

        if ($effectiveDate) {
            $query->where('effective_date', $effectiveDate);
        }

        $records = $query->get();
        $history = [];

        foreach ($records as $record) {
            if (!empty($record->changed_fields)) {
                foreach ($record->changed_fields as $field) {
                    $oldValue = $record->old_values[$field] ?? 'N/A';
                    $newValue = $record->new_values[$field] ?? 'N/A';
                    $fieldName = ucwords(str_replace('_', ' ', $field));
                    $changes = "Personal Info: {$fieldName} changed from {$oldValue} to {$newValue}";
                    $history[] = [
                        'id' => $record->id,
                        'effective_date' => $record->effective_date ?? $record->created_at?->format('Y-m-d'),
                        'type' => 'Personal Information',
                        'product' => null,
                        'product_id' => null,
                        'updated_on' => $record->updated_at,
                        'description' => $changes,
                        'updater' => $record->changedBy,
                    ];
                }
            }
        }

        return $history;
    }

    /**
     * Get Banking Info history (matches OLD API - one entry per field).
     */
    protected function getBankingInfoHistory(int $userId, ?int $updaterId, ?string $effectiveDate): array
    {
        $query = \App\Models\UserBankHistory::with('changedBy')
            ->where('user_id', $userId);

        if ($updaterId) {
            $query->where('changed_by', $updaterId);
        }

        if ($effectiveDate) {
            $query->where('effective_date', $effectiveDate);
        }

        $records = $query->get();
        $history = [];

        foreach ($records as $record) {
            if (!empty($record->changed_fields)) {
                foreach ($record->changed_fields as $field) {
                    $oldValue = $record->getOldValue($field) ?? 'N/A';
                    $newValue = $record->getNewValue($field) ?? 'N/A';
                    // Mask sensitive banking info
                    if (in_array($field, ['account_no', 'routing_no', 'confirm_account_no'])) {
                        if ($oldValue !== 'N/A' && strlen($oldValue) > 4) {
                            $oldValue = str_repeat('*', strlen($oldValue) - 4) . substr($oldValue, -4);
                        }
                        if ($newValue !== 'N/A' && strlen($newValue) > 4) {
                            $newValue = str_repeat('*', strlen($newValue) - 4) . substr($newValue, -4);
                        }
                    }
                    $fieldName = ucwords(str_replace('_', ' ', $field));
                    $changes = "Banking Info: {$fieldName} changed from {$oldValue} to {$newValue}";
                    $history[] = [
                        'id' => $record->id,
                        'effective_date' => $record->effective_date ?? $record->created_at?->format('Y-m-d'),
                        'type' => 'Banking Information',
                        'product' => null,
                        'product_id' => null,
                        'updated_on' => $record->updated_at,
                        'description' => $changes,
                        'updater' => $record->changedBy,
                    ];
                }
            }
        }

        return $history;
    }

    /**
     * Get Tax Info history
     */
    protected function getTaxInfoHistory(int $userId, ?int $updaterId, ?string $effectiveDate): array
    {
        $query = \App\Models\UserTaxHistory::with('changedBy')
            ->where('user_id', $userId);

        if ($updaterId) {
            $query->where('changed_by', $updaterId);
        }

        if ($effectiveDate) {
            $query->whereDate('created_at', $effectiveDate);
        }

        $records = $query->get();
        $history = [];

        foreach ($records as $record) {
            if (!empty($record->changed_fields)) {
                foreach ($record->changed_fields as $field) {
                    $oldValue = $record->getOldValue($field) ?? 'N/A';
                    $newValue = $record->getNewValue($field) ?? 'N/A';
                    // Mask sensitive tax info (SSN, EIN)
                    if ($field === 'social_sequrity_no') {
                        if ($oldValue !== 'N/A' && strlen($oldValue) > 4) {
                            $oldValue = '***-**-' . substr($oldValue, -4);
                        }
                        if ($newValue !== 'N/A' && strlen($newValue) > 4) {
                            $newValue = '***-**-' . substr($newValue, -4);
                        }
                    }
                    if ($field === 'business_ein') {
                        if ($oldValue !== 'N/A' && strlen($oldValue) > 4) {
                            $oldValue = '**-***' . substr($oldValue, -4);
                        }
                        if ($newValue !== 'N/A' && strlen($newValue) > 4) {
                            $newValue = '**-***' . substr($newValue, -4);
                        }
                    }
                    $fieldName = ucwords(str_replace('_', ' ', $field));
                    $changes = "Tax Info: {$fieldName} changed from {$oldValue} to {$newValue}";
                    $history[] = [
                        'id' => $record->id,
                        'effective_date' => $record->effective_date ?? $record->created_at?->format('Y-m-d'),
                        'type' => 'Tax Information',
                        'product' => null,
                        'product_id' => null,
                        'updated_on' => $record->updated_at,
                        'description' => $changes,
                        'updater' => $record->changedBy,
                    ];
                }
            }
        }

        return $history;
    }

    /**
     * Get Employment Status history (matches OLD API - one entry per field).
     */
    protected function getEmploymentStatusHistory(int $userId, ?int $updaterId, ?string $effectiveDate): array
    {
        $query = \App\Models\UserEmploymentStatusHistory::with('changedBy')
            ->where('user_id', $userId);

        if ($updaterId) {
            $query->where('changed_by', $updaterId);
        }

        if ($effectiveDate) {
            $query->where('effective_date', $effectiveDate);
        }

        $records = $query->get();
        $history = [];

        $statusMapping = [
            1 => 'Active', 2 => 'Inactive', 3 => 'Stop Payroll',
            4 => 'Delete', 5 => 'Reset Password', 6 => 'Disable Login', 7 => 'Terminate',
        ];
        $fieldValueMappings = [
            'stop_payroll' => [0 => 'Start Payroll', 1 => 'Stop Payroll'],
            'disable_login' => [0 => 'Grant Access', 1 => 'Suspend Access'],
            'terminate' => [0 => 'Not Terminated', 1 => 'Terminate with Effective Date'],
            'contract_ended' => [0 => 'Active Contract', 1 => 'Contract Ended'],
            'dismiss' => [0 => 'Not Dismissed', 1 => 'Dismissed'],
            'rehire' => [0 => 'Not Rehired', 1 => 'Rehired'],
        ];

        foreach ($records as $record) {
            if (!empty($record->changed_fields)) {
                foreach ($record->changed_fields as $field) {
                    $oldValue = $record->getOldValue($field);
                    $newValue = $record->getNewValue($field);

                    if ($field === 'status_id') {
                        $oldValue = $statusMapping[$oldValue] ?? $oldValue ?? 'N/A';
                        $newValue = $statusMapping[$newValue] ?? $newValue ?? 'N/A';
                        $fieldName = 'Status';
                    } elseif (isset($fieldValueMappings[$field])) {
                        $oldValue = $fieldValueMappings[$field][$oldValue] ?? ($oldValue ?? 'N/A');
                        $newValue = $fieldValueMappings[$field][$newValue] ?? ($newValue ?? 'N/A');
                        $fieldName = ucwords(str_replace('_', ' ', $field));
                    } elseif ($field === 'end_date') {
                        $oldValue = $oldValue ? date('M d, Y', strtotime($oldValue)) : 'N/A';
                        $newValue = $newValue ? date('M d, Y', strtotime($newValue)) : 'N/A';
                        $fieldName = 'End Date';
                    } else {
                        $oldValue = $oldValue ?? 'N/A';
                        $newValue = $newValue ?? 'N/A';
                        $fieldName = ucwords(str_replace('_', ' ', $field));
                    }

                    $effectiveDate = $record->effective_date ?? $record->created_at?->format('Y-m-d');
                    $formattedDate = $effectiveDate ? date('M d, Y', strtotime($effectiveDate)) : 'N/A';
                    $changes = "Employment Status: {$fieldName} changed from {$oldValue} to {$newValue} (Effective: {$formattedDate})";
                    $history[] = [
                        'id' => $record->id,
                        'effective_date' => $effectiveDate,
                        'type' => 'Employment Status',
                        'product' => null,
                        'product_id' => null,
                        'updated_on' => $record->updated_at,
                        'description' => $changes,
                        'updater' => $record->changedBy,
                    ];
                }
            }
        }

        return $history;
    }

    /**
     * Get Position and Self Gen history from UserOrganizationHistory (only when no filter specified).
     */
    protected function getPositionAndSelfGenHistory(int $userId, ?int $productId, ?int $updaterId, ?string $effectiveDate, bool $futureOnly): array
    {
        $history = [];

        $oraganization_history = \App\Models\UserOrganizationHistory::with('product:id,name', 'updater', 'oldManager', 'manager', 'oldTeam', 'team', 'position', 'oldPosition', 'subPositionId', 'oldSubPositionId')->where('user_id', $userId);
        if ($futureOnly) {
            $oraganization_history = $oraganization_history->where('effective_date', '>', date('Y-m-d'));
        }
        $oraganization_history = $oraganization_history->orderBy('effective_date')->get();

        $oldSelf = 0;
        foreach ($oraganization_history as $key => $org_history) {
            // Self Gen logic
            $go = 0;
            if ($key == 0 && !empty($org_history->self_gen_accounts)) {
                $go = 1;
            } elseif ($key != 0) {
                if ($oldSelf != $org_history->self_gen_accounts) {
                    $go = 1;
                }
            }

            if ($go) {
                $changes = 'Self Gen changed from '.(!empty($org_history->old_self_gen_accounts) ? 'YES' : 'NO').' to '.(!empty($org_history->self_gen_accounts) ? 'YES' : 'NO');
                $history[] = [
                    'id' => $org_history->id,
                    'effective_date' => isset($org_history->effective_date) ? $org_history->effective_date : null,
                    'type' => 'Self Gen',
                    'product' => $org_history->product->name,
                    'product_id' => $org_history->product->id,
                    'updated_on' => $org_history->updated_at,
                    'description' => $changes,
                    'updater' => isset($org_history->updater) ? $org_history->updater : null,
                ];
            }
            $oldSelf = $org_history->self_gen_accounts;

            // Position logic
            if (!empty($org_history->old_sub_position_id) || !empty($org_history->sub_position_id)) {
                $changes = 'Position changed from '.(!empty($org_history->oldSubPositionId->position_name) ? $org_history->oldSubPositionId->position_name : '')." to {$org_history->subPositionId->position_name}";
                $history[] = [
                    'id' => $org_history->id,
                    'effective_date' => isset($org_history->effective_date) ? $org_history->effective_date : null,
                    'type' => 'Position',
                    'product' => $org_history->product->name,
                    'product_id' => $org_history->product->id,
                    'updated_on' => $org_history->updated_at,
                    'description' => $changes,
                    'updater' => isset($org_history->updater) ? $org_history->updater : null,
                ];
            }
        }

        return $history;
    }

    /**
     * Get organization history from SOURCE tables (matches OLD API).
     * NOTE: Self Gen and Position are NOT included when filter is specifically "Organizations".
     * They are only included when filter is empty or explicitly "Self Gen"/"Position".
     * This includes UserIsManagerHistory, UserManagerHistory, and AdditionalLocations.
     */
    protected function getOrganizationHistory(int $userId, ?int $productId, ?int $updaterId, ?string $effectiveDate, bool $futureOnly): array
    {
        $history = [];

        // 1. UserIsManagerHistory
        $isManagers = \App\Models\UserIsManagerHistory::with('updater')->where('user_id', $userId);
        if ($futureOnly) {
            $isManagers = $isManagers->where('effective_date', '>', date('Y-m-d'));
        }
        $isManagers = $isManagers->get();

        if ($isManagers) {
            foreach ($isManagers as $isManager) {
                $changes = 'is manager changed from '.(!empty($isManager->old_is_manager) ? 'YES' : 'NO').' to '.(!empty($isManager->is_manager) ? 'YES' : 'NO');
                $history[] = [
                    'id' => $isManager->id,
                    'effective_date' => isset($isManager->effective_date) ? $isManager->effective_date : null,
                    'type' => 'is manager',
                    'product' => null,
                    'product_id' => null,
                    'updated_on' => $isManager->updated_at,
                    'description' => $changes,
                    'updater' => isset($isManager->updater) ? $isManager->updater : null,
                ];
            }
        }

        // 2. UserManagerHistory - for manager and team changes
        $managers = \App\Models\UserManagerHistory::with('updater', 'manager', 'oldManager', 'team', 'oldTeam')->where('user_id', $userId);
        if ($futureOnly) {
            $managers = $managers->where('effective_date', '>', date('Y-m-d'));
        }
        $managers = $managers->get();

        if ($managers) {
            foreach ($managers as $manager) {
                $changes = 'Manager changed from '.(!empty($manager->oldManager->first_name) ? $manager->oldManager->first_name.' '.$manager->oldManager->last_name : '').' to '.(!empty($manager->manager->first_name) ? $manager->manager->first_name.' '.$manager->manager->last_name : '');
                $history[] = [
                    'id' => $manager->id,
                    'effective_date' => isset($manager->effective_date) ? $manager->effective_date : null,
                    'type' => 'Manager',
                    'product' => null,
                    'product_id' => null,
                    'updated_on' => $manager->updated_at,
                    'description' => $changes,
                    'updater' => isset($manager->updater) ? $manager->updater : null,
                ];

                if ($manager->team_id) {
                    $changes = 'Team changed from '.(!empty($manager->oldTeam->team_name) ? $manager->oldTeam->team_name.' '.$manager->oldTeam->last_name : '').' to '.(!empty($manager->team->first_name) ? $manager->team->first_name.' '.$manager->team->last_name : '');
                    $history[] = [
                        'id' => $manager->id,
                        'effective_date' => isset($manager->effective_date) ? $manager->effective_date : null,
                        'type' => 'Team',
                        'product' => null,
                        'product_id' => null,
                        'updated_on' => $manager->updated_at,
                        'description' => $changes,
                        'updater' => isset($manager->updater) ? $manager->updater : null,
                    ];
                }
            }
        }

        // 3. AdditionalLocations
        $additional_locations = \App\Models\AdditionalLocations::with('state', 'office', 'updater')->where('user_id', $userId)->withTrashed();
        if ($futureOnly) {
            $additional_locations = $additional_locations->where('effective_date', '>', date('Y-m-d'));
        }
        $additional_locations = $additional_locations->get();

        foreach ($additional_locations as $locations) {
            $state = isset($locations->state->name) ? $locations->state->name : '';
            $office = isset($locations->office->office_name) ? $locations->office->office_name : '';
            if (empty($locations->deleted_at)) {
                $changes = 'Additional Location changed from - '." to {$state} | {$office}";
                $history[] = [
                    'id' => $locations->id,
                    'effective_date' => isset($locations->effective_date) ? $locations->effective_date : null,
                    'type' => 'Additional Location',
                    'product' => null,
                    'product_id' => null,
                    'updated_on' => $locations->updated_at,
                    'description' => $changes,
                    'updater' => isset($locations->updater) ? $locations->updater : null,
                ];
            } elseif (!empty($locations->archived_at)) {
                $changes = "Additional Location Archived from {$state} | {$office} ";
                $history[] = [
                    'id' => $locations->id,
                    'effective_date' => isset($locations->effective_date) ? $locations->effective_date : null,
                    'type' => 'Additional Location',
                    'product' => null,
                    'product_id' => null,
                    'updated_on' => $locations->updated_at,
                    'description' => $changes,
                    'updater' => isset($locations->updater) ? $locations->updater : null,
                ];
            } else {
                $changes = "Additional Location Deleted from {$state} | {$office} ";
                $history[] = [
                    'id' => $locations->id,
                    'effective_date' => isset($locations->effective_date) ? $locations->effective_date : null,
                    'type' => 'Additional Location',
                    'product' => null,
                    'product_id' => null,
                    'updated_on' => $locations->updated_at,
                    'description' => $changes,
                    'updater' => isset($locations->updater) ? $locations->updater : null,
                ];
            }
        }

        return $history;
    }

    /**
     * Get transfer history from SOURCE table (matches OLD API).
     */
    protected function getTransferHistory(int $userId, ?int $productId, ?int $updaterId, ?string $effectiveDate, bool $futureOnly): array
    {
        $query = \App\Models\UserTransferHistory::with('user', 'department', 'oldDepartment', 'updater', 'position', 'oldPosition', 'subposition', 'oldSubPosition', 'office', 'oldOffice', 'state', 'oldState')
            ->where('user_id', $userId)
            ->orderBy('transfer_effective_date', 'DESC');

        if ($futureOnly) {
            $query->where('transfer_effective_date', '>', date('Y-m-d'));
        }

        $records = $query->get();
        $history = [];

        foreach ($records as $res) {
            $oldOffice = $res->oldOffice->office_name ?? 'NA';
            $oldState = $res->oldState->name ?? 'NA';
            $newOffice = $res->office->office_name ?? 'NA';
            $newState = $res->state->name ?? 'NA';

            $changes = "Transfer from {$oldOffice} | {$oldState} to {$newOffice} | {$newState}";

            $history[] = [
                'id' => $res->id,
                'effective_date' => $res->transfer_effective_date,
                'type' => 'Transfers',
                'product' => null,
                'product_id' => null,
                'updated_on' => $res->updated_at,
                'description' => $changes,
                'updater' => isset($res->updater) ? $res->updater : null,
            ];
        }

        return $history;
    }

    /**
     * Get wages history from SOURCE table (matches OLD API).
     */
    protected function getWagesHistory(int $userId, ?int $productId, ?int $updaterId, ?string $effectiveDate, bool $futureOnly): array
    {
        $query = \App\Models\UserWagesHistory::with('updater')->where('user_id', $userId);

        if ($futureOnly) {
            $query->where('effective_date', '>', date('Y-m-d'));
        }

        $records = $query->get();
        $history = [];

        foreach ($records as $wages) {
            $fields = [
                'pay_type' => 'Wages pay type',
                'pay_rate' => 'Wages pay rate',
                'expected_weekly_hours' => 'Wages expected weekly hours',
                'overtime_rate' => 'Wages overtime rate',
                'pto_hours' => 'Wages pto hours',
                'unused_pto_expires' => 'Wages unused pto expires',
            ];

            foreach ($fields as $field => $label) {
                $oldField = "old_{$field}";
                $oldValue = !empty($wages->$oldField) ? $wages->$oldField : 0;
                $newValue = $wages->$field;
                $changes = "$label Change from {$oldValue} to {$newValue}";

                $history[] = [
                    'id' => $wages->id,
                    'effective_date' => in_array($field, ['pto_hours', 'unused_pto_expires']) ? $wages->pto_hours_effective_date : $wages->effective_date,
                    'type' => $label,
                    'product' => null,
                    'product_id' => null,
                    'updated_on' => $wages->updated_at,
                    'description' => $changes,
                    'updater' => $wages->updater,
                ];
            }

            // Special case: pay_rate with rate_type
            $oldPayRate = !empty($wages->old_pay_rate) ? $wages->old_pay_rate : 0;
            $changes = "Wages pay rate Change from {$oldPayRate} {$wages->old_pay_rate_type} to {$wages->pay_rate} {$wages->pay_rate_type}";
            $history[] = [
                'id' => $wages->id,
                'effective_date' => $wages->effective_date,
                'type' => 'Wages pay rate',
                'product' => null,
                'product_id' => null,
                'updated_on' => $wages->updated_at,
                'description' => $changes,
                'updater' => $wages->updater,
            ];
        }

        return $history;
    }

    /**
     * Get deduction history from SOURCE table (matches OLD API).
     */
    protected function getDeductionHistory(int $userId, ?int $productId, ?int $updaterId, ?string $effectiveDate, bool $futureOnly): array
    {
        $query = \App\Models\UserDeductionHistory::with('updater', 'costcenter')
            ->where('user_id', $userId);

        if ($futureOnly) {
            $query->where('effective_date', '>', date('Y-m-d'));
        }

        $records = $query->get();
        $user = \App\Models\User::select('id', 'sub_position_id')->where('id', $userId)->first();
        $costCenterIds = \App\Models\PositionCommissionDeduction::where('position_id', $user->sub_position_id)->pluck('cost_center_id')->toArray();
        $history = [];

        foreach ($records as $deduction_history) {
            $isDelete = in_array($deduction_history->cost_center_id, $costCenterIds) ? 0 : 1;
            if ($deduction_history->old_amount_par_paycheque != $deduction_history->amount_par_paycheque) {
                $costCenterType = isset($deduction_history->costcenter->name) ? $deduction_history->costcenter->name : null;
                $oldAmount = !empty($deduction_history->old_amount_par_paycheque) ? $deduction_history->old_amount_par_paycheque : 0;

                $changes = $isDelete == 1 ? "{$costCenterType} deleted from {$oldAmount}" : "{$costCenterType} changed from {$oldAmount} to {$deduction_history->amount_par_paycheque}";

                $history[] = [
                    'id' => $deduction_history->id,
                    'effective_date' => $deduction_history->effective_date,
                    'type' => $costCenterType,
                    'product' => null,
                    'product_id' => null,
                    'updated_on' => $deduction_history->updated_at,
                    'description' => $changes,
                    'updater' => $deduction_history->updater,
                ];
            }
        }

        return $history;
    }

    /**
     * Get self gen commission history from SOURCE table (matches OLD API).
     */
    protected function getSelfGenCommissionHistory(int $userId, ?int $productId, ?int $updaterId, ?string $effectiveDate, bool $futureOnly): array
    {
        $query = \App\Models\UserSelfGenCommmissionHistory::with('updater', 'subposition', 'position')
            ->where('user_id', $userId);

        if ($futureOnly) {
            $query->where('commission_effective_date', '>', date('Y-m-d'));
        }

        $records = $query->get();
        $history = [];

        foreach ($records as $self_gen_history) {
            if ($self_gen_history->old_commission != null || $self_gen_history->commission != null) {
                $history[] = [
                    'id' => $self_gen_history->id,
                    'effective_date' => $self_gen_history->commission_effective_date,
                    'type' => 'Self Gen Commission',
                    'position_id' => $self_gen_history->position_id,
                    'sub_position_id' => isset($self_gen_history->sub_position_id) ? $self_gen_history->sub_position_id : null,
                    'sub_position_name' => isset($self_gen_history->subposition->position_name) ? $self_gen_history->subposition->position_name : null,
                    'old_value' => $self_gen_history->old_commission,
                    'new_value' => $self_gen_history->commission,
                    'position_role' => isset($self_gen_history->subposition->position_name) ? $self_gen_history->subposition->position_name : null,
                    'old_amount' => $self_gen_history->old_commission,
                    'new_amount' => $self_gen_history->commission,
                    'updated_on' => $self_gen_history->updated_at,
                    'updater' => $self_gen_history->updater,
                    'percentage' => get_growth_percentage($self_gen_history->old_commission, $self_gen_history->commission),
                ];
            }
        }

        return $history;
    }

    /**
     * Get display value for commission type (with custom field support).
     * 
     * @param string|null $commissionType The commission type from database
     * @param int|null $customSalesFieldId The custom sales field ID if set
     * @return string The display value for the commission type
     */
    protected function getCommissionTypeDisplay(?string $commissionType, ?int $customSalesFieldId): string
    {
        // If custom sales field is set, get the field name
        if ($customSalesFieldId) {
            $customField = Crmcustomfields::find($customSalesFieldId);
            if ($customField) {
                return ' per ' . $customField->name;
            }
        }

        // Standard commission type display
        return match ($commissionType) {
            'per kw' => ' per kw',
            'per sale' => ' per sale',
            'custom field' => ' (custom field)',
            default => ' %',
        };
    }

    /**
     * Get display value for any type (commission, upfront, override) with custom field support.
     * This is a more generic version that handles all type fields.
     * 
     * @param string|null $type The type value from database (e.g., 'per kw', 'per sale', 'custom field', '%')
     * @param int|null $customSalesFieldId The custom sales field ID if set
     * @return string The display value for the type
     */
    protected function getTypeDisplayForAudit(?string $type, ?int $customSalesFieldId): string
    {
        // If custom sales field is set, get the field name
        if ($customSalesFieldId) {
            $customField = Crmcustomfields::find($customSalesFieldId);
            if ($customField) {
                return ' per ' . $customField->name;
            }
        }

        // If type is 'custom field' but we don't have the ID, show generic
        if ($type === 'custom field') {
            return ' (custom field)';
        }

        // Standard type display - handle both with and without leading space
        $normalizedType = ltrim($type ?? '', ' ');
        
        return match ($normalizedType) {
            'per kw' => ' per kw',
            'per sale' => ' per sale',
            '%' => ' %',
            '' => ' %',
            default => ' ' . $normalizedType,
        };
    }
}

