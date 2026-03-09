<?php

namespace App\Imports\Sales;

use App\Imports\Sales\DTO\ImportFieldMeta;
use App\Models\ProductCode;
use App\Services\Sales\FallbackFieldResolverService;
use App\Services\Sales\RawDataHistoryService;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

abstract class AbstractSalesImport implements ToModel, WithHeadingRow
{
    public $errors = [];

    public $ids = [];

    protected int $rowNum = 0;

    public $totalRecords = 0;

    public $errorRecords = 0;

    public $salesErrorReport = [];

    public $salesSuccessReport = [];
    public $salesSkippedReport = [];
    public $headerError = [];

    public $symbolsArray = ['+', '='];

    public $flexibleIds = [];

    public $usersEmails = [];

    public bool $skipErrorRows = false;
    public int $skippedRecords = 0;


    protected const COMPANY_TYPE = null;

    protected RawDataHistoryService $rawDataHistoryService;

    protected FallbackFieldResolverService $fieldResolverService;

    protected array $fieldMap = [];

    protected int $templateId;

    private bool $abortImport = false;

    /**
     * Override in subclass to specify which fields to validate against Excel formulas (=, +, etc.)
     */
    protected array $fieldsToValidateForSpecialChars = [];

    public function __construct()
    {
        // Use custom formatter to trim headers but preserve case
        HeadingRowFormatter::extend('trim_preserve_case', function($value) {
            return is_string($value) ? trim($value) : $value;
        });
        HeadingRowFormatter::default('trim_preserve_case');
        
        $this->rawDataHistoryService = app(RawDataHistoryService::class);
        $this->fieldResolverService = app(FallbackFieldResolverService::class);
    }

    abstract protected function getTemplateId(): int;

    protected function getCompanyType(): ?string
    {
        return static::COMPANY_TYPE;
    }

    public function setTemplateId(int $templateId): void
    {
        $this->templateId = $templateId;
    }

    public function model(array $row)
    {
        if ($this->abortImport) {
            return null;
        }

        $this->totalRecords++;
        $this->rowNum++;

        if ($this->rowNum === 1) {
            $this->validateHeaderRow($row);

            if (! empty($this->errors)) {
                $this->errorRecords++;
                $this->message = 'Import Failed due to invalid headers.';
                $this->abortImport = true;

                return null;
            }
        }

        $this->stripUnmappedColumns($row);
        
        $dateErrors = $this->normalizeDateFields($row);
        $this->normalizePriceFields($row);

        $this->validateSpecialSymbols($row, $this->rowNum);
        $this->sanitizeRow($row);
        $validationErrors = $this->validateRow($row, $this->rowNum);
        
        // Merge all errors together
        $errors = array_merge($dateErrors, $validationErrors);
        
        if (!empty($errors)) {
            if ($this->skipErrorRows) {
                if (!$this->validateOnly) {
                    $this->processAndStoreRawDataWithErrors($row, $errors);
                }
                $this->registerSkipped($errors, $row, $this->rowNum);
                return null;
            }
            $this->registerErrors($errors);

            return null;
        }

        $this->registerSuccess($row, $this->totalRecords);

        if (! $this->validateOnly) {
            $this->processAndStoreRawData($row);
        }

        return null;
    }

    protected function validateHeaderRow(array $row): void
    {
        if ($this->rowNum !== 1) {
            return;
        }

        $rawHeaders = array_keys($row);
        $actualHeaders = $this->trimTrailingEmptyHeaders($rawHeaders);

        $expectedHeaders = array_keys($this->fieldMap);
        $normalizedExpectedHeaders = array_map([$this, 'normalizeHeader'], $expectedHeaders);
        $expectedMap = array_combine($normalizedExpectedHeaders, $expectedHeaders);
        if ($expectedMap === false) {
            throw new \RuntimeException('Header count mismatch: normalized vs expected.');
        }

        $validActualHeaders = array_values(array_filter($actualHeaders, fn ($h) => ! $this->isEmptyHeaderValue($h)));
        $normalizedActualHeaders = array_map([$this, 'normalizeHeader'], $validActualHeaders);

        $mandatoryMissing = [];
        foreach ($this->fieldMap as $expectedHeader => $meta) {
            if (is_array($meta)) {
                // Multiple fields per column - check if any are mandatory
                $hasMandatory = false;
                foreach ($meta as $fieldMeta) {
                    if ($fieldMeta->mandatory) {
                        $hasMandatory = true;
                        break;
                    }
                }
                if ($hasMandatory) {
                    $normalizedExpected = $this->normalizeHeader($expectedHeader);
                    if (! in_array($normalizedExpected, $normalizedActualHeaders, true)) {
                        $mandatoryMissing[] = $expectedHeader;
                    }
                }
            } else {
                // Single field per column
                if ($meta->mandatory) {
                    $normalizedExpected = $this->normalizeHeader($expectedHeader);
                    if (! in_array($normalizedExpected, $normalizedActualHeaders, true)) {
                        $mandatoryMissing[] = $expectedHeader;
                    }
                }
            }
        }

        $emptyInternalHeaders = [];
        foreach ($actualHeaders as $i => $rawHeader) {
            if ($this->isEmptyHeaderValue($rawHeader)) {
                $emptyInternalHeaders[] = $this->excelColumnLetter($i);
            }
        }

        if ($mandatoryMissing || $emptyInternalHeaders) {
            foreach ($mandatoryMissing as $header) {
                $this->errors[] = [
                    'row' => 1,
                    'field' => $header,
                    'message' => 'Header is mandatory and missing from your import file.',
                ];
            }

            foreach ($emptyInternalHeaders as $colLetter) {
                $this->errors[] = [
                    'row' => 1,
                    'field' => "[empty header at column {$colLetter}]",
                    'message' => 'Header is empty. Please name this column or remove it.',
                ];
            }
        }

        $this->headers = $actualHeaders;
    }

    private function stripUnmappedColumns(array &$row): void
    {
        if (empty($this->fieldMap)) {
            return;
        }

        $allowed = [];
        foreach (array_keys($this->fieldMap) as $excelHeader) {
            $allowed[$this->normalizeHeader($excelHeader)] = true;
        }

        foreach (array_keys($row) as $col) {
            $norm = $this->normalizeHeader((string) $col);
            if (isset($allowed[$norm])) {
                continue;
            }
            if (str_starts_with((string) $col, 'trigger_date_')) {
                continue;
            }
            unset($row[$col]);
        }
    }

    private function isEmptyHeaderValue($header): bool
    {
        return $header === null
            || ! is_string($header)
            || trim($header) === ''
            || is_numeric($header);
    }

    private function trimTrailingEmptyHeaders(array $headers): array
    {
        while (! empty($headers) && $this->isEmptyHeaderValue(end($headers))) {
            array_pop($headers);
        }

        return $headers;
    }

    private function excelColumnLetter(int $zeroBasedIndex): string
    {
        $n = $zeroBasedIndex + 1;
        $letters = '';
        while ($n > 0) {
            $rem = ($n - 1) % 26;
            $letters = chr(65 + $rem).$letters;
            $n = intdiv($n - 1, 26);
        }

        return $letters;
    }

    private function normalizeHeader(string $header): string
    {
        $normalizedHeader = mb_strtolower(trim($header));
        $normalizedHeader = preg_replace('/\s+/u', ' ', $normalizedHeader);

        return $normalizedHeader;
    }

    protected function loadTemplateFields($details, int $templateId): void
    {
        foreach ($details as $detail) {
            $field = $detail->field;

            if (! $field) {
                \Log::warning("Missing field relation for template detail ID {$detail->id} ({$detail->excel_field}) — field_id is null");

                continue;
            }

            $excelHeader = $detail->excel_field;
            if ($excelHeader === null || trim((string) $excelHeader) === '') {
                continue;
            }

            if (array_key_exists($excelHeader, $this->fieldMap)) {
                // If it's already an array, add to it; otherwise, convert to array
                if (! is_array($this->fieldMap[$excelHeader])) {
                    $this->fieldMap[$excelHeader] = [$this->fieldMap[$excelHeader]];
                }

                // Add the new field to the array
                $this->fieldMap[$excelHeader][] = new ImportFieldMeta(
                    name: $field->name ?? null,
                    type: $field->field_type ?? 'text',
                    mandatory: (bool) ($field->is_mandatory ?? false),
                    fromDb: ! is_null($field?->name),
                    isCustom: (bool) ($field->is_custom ?? false)
                );

                \Log::info("Multiple fields mapped to Excel column '{$excelHeader}': ".
                    implode(', ', array_map(fn ($m) => $m->name ?? 'unnamed', $this->fieldMap[$excelHeader])));
            } else {
                $this->fieldMap[$excelHeader] = new ImportFieldMeta(
                    name: $field->name ?? null,
                    type: $field->field_type ?? 'text',
                    mandatory: (bool) ($field->is_mandatory ?? false),
                    fromDb: ! is_null($field?->name),
                    isCustom: (bool) ($field->is_custom ?? false)
                );
            }
        }
    }

    protected function getAllResolvedFieldMapping(): array
    {
        $mapping = [];

        foreach ($this->fieldMap as $excelField => $meta) {
            if (is_array($meta)) {
                // Multiple fields mapped to same Excel column
                foreach ($meta as $fieldMeta) {
                    if ($fieldMeta->name) {
                        $mapping[$fieldMeta->name] = $excelField;
                    }
                }
            } else {
                // Single field mapped to Excel column
                if ($meta->name) {
                    $mapping[$meta->name] = $excelField;
                }
            }
        }

        return $mapping;
    }

    protected function processAndStoreRawData(array $row): void
    {
        $row['trigger_date'] = $this->extractTriggerDatesFromTemplate($row);
        
        // Extract custom field values (number/text types) for Custom Sales Fields feature
        // Only extract when feature is enabled to avoid impacting normal import flow
        $row['custom_field_values'] = $this->isCustomFieldsFeatureEnabled() 
            ? $this->extractCustomFieldValuesFromTemplate($row) 
            : [];

        // The rawDataHistoryService->prepare() expects Excel headers as keys, not field names
        $fieldMapping = $this->getAllResolvedFieldMapping();
        $data = $this->rawDataHistoryService->prepare($row, $fieldMapping);

        [$data['product_id'], $data['product_code']] = $this->resolveProductIdAndCode(
            $data['product_id'] ?? null,
            $data['customer_signoff'] ?? null
        );

        try {
            if (class_exists(\Illuminate\Support\Facades\Log::class)) {
                \Illuminate\Support\Facades\Log::info('[FLEXIBLE_ID] Starting flexible ID processing', [
                    'row_number' => $this->rowNum,
                    'company_type' => $this->getCompanyType(),
                    'template_id' => $this->getTemplateId(),
                    'data_keys' => array_keys($data),
                ]);
            }
        } catch (\Exception $e) {
            // Silently fail if Log facade is not available (e.g., in unit tests)
        }

        // 🚀 ENHANCED: Now handles flexible IDs with priority-based user resolution
        $this->replaceEmailsWithUserIdsOnPrepared($data);
        
        // ✅ AUTO-CORRECTION: Remove dismissed/terminated users from sale (sale proceeds without them)
        $this->removeDismissedUsersFromSale($data);
        
        if (! empty($row['trigger_date']) && is_array($row['trigger_date'])) {
            $data['trigger_date'] = json_encode($row['trigger_date'], JSON_UNESCAPED_UNICODE);
        } else {
            $data['trigger_date'] = null;
        }

        // Extract non-date custom field values for Custom Sales Fields feature
        // These will be saved to Crmsaleinfo.custom_field_values during sales processing
        // Note: LegacyApiRawDataHistory model has 'array' cast, so pass array directly (no json_encode)
        // Only process when feature is enabled to avoid impacting normal import flow
        if ($this->isCustomFieldsFeatureEnabled() && ! empty($row['custom_field_values']) && is_array($row['custom_field_values'])) {
            $data['custom_field_values'] = $row['custom_field_values'];
        }

        $data['template_id'] = $this->getTemplateId();
        $data['data_source_type'] = 'excel';
        
        // Store which fields were mapped in the template so we know which ones to update (even if null)
        $data['mapped_fields'] = array_keys($fieldMapping);
        
        // Store actual CSV row number for accurate error reporting
        $data['row_number'] = $this->rowNum;

        // Auto-calculate epc (Gross Revenue) if not provided (all company types)
        $this->autoCalculateEpcIfNeeded($data);

        $saved = $this->rawDataHistoryService->save($data);
        $this->ids[] = $saved->id;
        $this->newRecords++;
    }

    /**
     * Store problematic record with error details in LegacyApiRawDataHistory
     * 
     * @param array $row
     * @param array $errors
     * @return void
     */
    protected function processAndStoreRawDataWithErrors(array $row, array $errors): void
    {
        $row['trigger_date'] = $this->extractTriggerDatesFromTemplate($row);

        // The rawDataHistoryService->prepare() expects Excel headers as keys, not field names
        $data = $this->rawDataHistoryService->prepare($row, $this->getAllResolvedFieldMapping());

        [$data['product_id'], $data['product_code']] = $this->resolveProductIdAndCode(
            $data['product_id'] ?? null,
            $data['customer_signoff'] ?? null
        );

        // Process flexible IDs
        $this->replaceEmailsWithUserIdsOnPrepared($data);
        
        // ✅ AUTO-CORRECTION: Remove dismissed/terminated users from sale (sale proceeds without them)
        $this->removeDismissedUsersFromSale($data);
        
        if (!empty($row['trigger_date']) && is_array($row['trigger_date'])) {
            $data['trigger_date'] = json_encode($row['trigger_date'], JSON_UNESCAPED_UNICODE);
        } else {
            $data['trigger_date'] = null;
        }

        $data['template_id'] = $this->getTemplateId();
        $data['data_source_type'] = 'excel';
        
        // Store actual CSV row number for accurate error reporting
        $data['row_number'] = $this->rowNum;
        
        // Prepare error description
        $errorMessages = [];
        foreach ($errors as $error) {
            $errorMessages[] = "[{$error['field']}]: {$error['message']}";
        }
        
        // Set error status and description
        $data['import_to_sales'] = '2'; // Mark as error
        $data['import_status_reason'] = 'Validation Error';
        $data['import_status_description'] = implode(' | ', $errorMessages);

        // Auto-calculate epc (Gross Revenue) if not provided (all company types)
        $this->autoCalculateEpcIfNeeded($data);

        $saved = $this->rawDataHistoryService->save($data);
        $this->ids[] = $saved->id; // Add to ids array so it gets excel_import_id later
        
        \Log::info("[EXCEL_IMPORT] Stored problematic record with errors", [
            'row_number' => $this->rowNum,
            'record_id' => $saved->id,
            'pid' => $data['pid'] ?? 'N/A',
            'error_count' => count($errors)
        ]);
    }


    /**
     * Get list of core/standard date fields that should NEVER be treated as custom trigger dates.
     * These fields should be populated directly into their database columns, not via trigger_date JSON.
     *
     * @return array<string>
     */
    protected function getCoreDateFields(): array
    {
        return [
            'm1_date',
            'm2_date',
            'm3_date',
            'm4_date',
            'm5_date',
            'initial_service_date',
            'customer_signoff',
            'date_cancelled',
            'scheduled_install',
            'install_complete_date',
            'return_sales_date',
            'last_service_date',
        ];
    }

    /**
     * Check if a field name is a core/standard date field that should be populated directly.
     *
     * @param string|null $fieldName
     * @return bool
     */
    protected function isCoreDateField(?string $fieldName): bool
    {
        if ($fieldName === null) {
            return false;
        }

        return in_array($fieldName, $this->getCoreDateFields(), true);
    }

    protected function extractTriggerDatesFromTemplate(array $row): array
    {
        $triggerDates = [];
        $debugFieldMap = [];

        // Build normalized lookup map: normalized header => actual CSV header
        // This allows case-insensitive and whitespace-tolerant matching
        $normalizedRowKeys = [];
        foreach (array_keys($row) as $csvHeader) {
            $normalizedRowKeys[$this->normalizeHeader((string) $csvHeader)] = $csvHeader;
        }

        foreach ($this->fieldMap as $excelHeader => $meta) {
            // Find actual CSV header using normalized matching
            $normalizedTemplate = $this->normalizeHeader($excelHeader);
            $actualCsvHeader = $normalizedRowKeys[$normalizedTemplate] ?? null;

            if (is_array($meta)) {
                // Multiple fields mapped to same column - check each one
                foreach ($meta as $fieldMeta) {
                    $fieldName = $fieldMeta->name ?? null;
                    $isDate = ($fieldMeta->type ?? null) === 'date';
                    $isCustom = ($fieldMeta->isCustom ?? false);
                    $isCoreField = $this->isCoreDateField($fieldName);

                    // Only extract to trigger_date if it's a date field, marked as custom, AND NOT a core field
                    if ($isDate && $isCustom && !$isCoreField) {
                        $rawValue = $actualCsvHeader !== null ? ($row[$actualCsvHeader] ?? null) : null;
                        $dateValue = $this->toYmd($rawValue);
                        $triggerDates[] = [
                            'field_name' => $fieldName, // Store field name for matching with milestone triggers
                            'date' => $dateValue,
                        ];
                        $debugFieldMap[] = [
                            'excel_header' => $excelHeader,
                            'actual_csv_header' => $actualCsvHeader,
                            'field_name' => $fieldName,
                            'raw_value' => $rawValue,
                            'parsed_date' => $dateValue,
                            'index' => count($triggerDates) - 1,
                        ];
                } elseif ($isDate && $isCustom && $isCoreField) {
                    // Log warning about incorrect configuration
                    try {
                        if (class_exists(\Illuminate\Support\Facades\Log::class)) {
                            \Illuminate\Support\Facades\Log::warning('[IMPORT] Core date field incorrectly marked as custom', [
                                'field_name' => $fieldName,
                                'excel_header' => $excelHeader,
                                'actual_csv_header' => $actualCsvHeader,
                                'row_number' => $this->rowNum,
                                'message' => "Field '{$fieldName}' is a core/standard field but is marked as custom. It will be populated directly, not via trigger_date.",
                            ]);
                        }
                    } catch (\Exception $e) {
                        // Silently fail if Log facade is not available (e.g., in unit tests)
                    }
                }
                }
            } else {
                // Single field mapped to column
                $fieldName = $meta->name ?? null;
                $isDate = ($meta->type ?? null) === 'date';
                $isCustom = ($meta->isCustom ?? false);
                $isCoreField = $this->isCoreDateField($fieldName);

                // Only extract to trigger_date if it's a date field, marked as custom, AND NOT a core field
                if ($isDate && $isCustom && !$isCoreField) {
                    $rawValue = $actualCsvHeader !== null ? ($row[$actualCsvHeader] ?? null) : null;
                    $dateValue = $this->toYmd($rawValue);
                    $triggerDates[] = [
                        'field_name' => $fieldName, // Store field name for matching with milestone triggers
                        'date' => $dateValue,
                    ];
                    $debugFieldMap[] = [
                        'excel_header' => $excelHeader,
                        'actual_csv_header' => $actualCsvHeader,
                        'field_name' => $fieldName,
                        'raw_value' => $rawValue,
                        'parsed_date' => $dateValue,
                        'index' => count($triggerDates) - 1,
                    ];
                } elseif ($isDate && $isCustom && $isCoreField) {
                    // Log warning about incorrect configuration
                    try {
                        if (class_exists(\Illuminate\Support\Facades\Log::class)) {
                            \Illuminate\Support\Facades\Log::warning('[IMPORT] Core date field incorrectly marked as custom', [
                                'field_name' => $fieldName,
                                'excel_header' => $excelHeader,
                                'actual_csv_header' => $actualCsvHeader,
                                'row_number' => $this->rowNum,
                                'message' => "Field '{$fieldName}' is a core/standard field but is marked as custom. It will be populated directly, not via trigger_date.",
                            ]);
                        }
                    } catch (\Exception $e) {
                        // Silently fail if Log facade is not available (e.g., in unit tests)
                    }
                }
            }
        }

        if (isset($this->maxTriggerCount) && is_int($this->maxTriggerCount) && $this->maxTriggerCount > 0) {
            $triggerDates = array_slice($triggerDates, 0, $this->maxTriggerCount);
        }

        // Log the extracted trigger dates for debugging
        try {
            \Log::info('[MILESTONE_DEBUG] extractTriggerDatesFromTemplate', [
                'row_number' => $this->rowNum,
                'pid' => $row['pid'] ?? $row['PID'] ?? 'N/A',
                'template_id' => $this->templateId ?? 'N/A',
                'trigger_dates_count' => count($triggerDates),
                'trigger_dates' => $triggerDates,
                'field_mapping_details' => $debugFieldMap,
            ]);
        } catch (\Exception $e) {
            // Silently fail
        }

        return $triggerDates;
    }

    /**
     * Check if the Custom Sales Fields feature is enabled.
     * 
     * This uses the CustomSalesFieldHelper to check the feature flag,
     * ensuring we only process custom fields when the feature is active.
     * 
     * @return bool
     */
    protected function isCustomFieldsFeatureEnabled(): bool
    {
        return \App\Helpers\CustomSalesFieldHelper::isFeatureEnabled();
    }

    /**
     * Extract non-date custom field values from Excel row using template mapping.
     * 
     * This extracts number and text type custom fields that are used for
     * Custom Sales Fields feature (commission/override calculations).
     * Date type custom fields are handled separately via extractTriggerDatesFromTemplate().
     * 
     * @param array $row The Excel row data
     * @return array Array of [field_id => value] for custom fields
     */
    protected function extractCustomFieldValuesFromTemplate(array $row): array
    {
        $customFieldValues = [];

        // Build normalized lookup map: normalized header => actual CSV header
        $normalizedRowKeys = [];
        foreach (array_keys($row) as $csvHeader) {
            $normalizedRowKeys[$this->normalizeHeader((string) $csvHeader)] = $csvHeader;
        }

        foreach ($this->fieldMap as $excelHeader => $meta) {
            // Find actual CSV header using normalized matching
            $normalizedTemplate = $this->normalizeHeader($excelHeader);
            $actualCsvHeader = $normalizedRowKeys[$normalizedTemplate] ?? null;

            $processField = function ($fieldMeta) use ($row, $actualCsvHeader, &$customFieldValues) {
                $fieldName = $fieldMeta->name ?? null;
                $fieldType = $fieldMeta->type ?? 'text';
                $isCustom = $fieldMeta->isCustom ?? false;

                // Only process non-date custom fields (number, text)
                // Date custom fields are handled by extractTriggerDatesFromTemplate
                if (!$isCustom || $fieldType === 'date') {
                    return;
                }

                // Field name format is 'custom_field_X' where X is the field ID
                if (!$fieldName || !preg_match('/^custom_field_(\d+)$/', $fieldName, $matches)) {
                    return;
                }

                $fieldId = (int) $matches[1];
                $rawValue = $actualCsvHeader !== null ? ($row[$actualCsvHeader] ?? null) : null;

                // Skip empty values
                if ($rawValue === null || $rawValue === '') {
                    return;
                }

                // Convert value based on type
                if ($fieldType === 'number') {
                    // Clean and convert to numeric
                    $cleanValue = preg_replace('/[^0-9.-]/', '', (string) $rawValue);
                    $customFieldValues[$fieldId] = is_numeric($cleanValue) ? (float) $cleanValue : null;
                } else {
                    // Text type - store as string
                    $customFieldValues[$fieldId] = (string) $rawValue;
                }
            };

            if (is_array($meta)) {
                // Multiple fields mapped to same column - process each one
                foreach ($meta as $fieldMeta) {
                    $processField($fieldMeta);
                }
            } else {
                // Single field mapped to column
                $processField($meta);
            }
        }

        return $customFieldValues;
    }

    private function toYmd($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            // Validate that it's an actual valid date
            $dt = \DateTime::createFromFormat('Y-m-d', $value);
            if ($dt && $dt->format('Y-m-d') === $value) {
                return $value;
            }

            return null;
        }

        if (is_numeric($value)) {
            $ts = ((int) $value - 25569) * 86400;
            if ($ts <= 0) {
                return null;
            }

            return gmdate('Y-m-d', $ts);
        }

        return null;
    }

    private function getDateHeadersFromTemplate(): array
    {
        $headers = [];
        foreach ($this->fieldMap as $excelField => $meta) {
            if (is_array($meta)) {
                // Multiple fields mapped to same column - check if any are dates
                foreach ($meta as $fieldMeta) {
                    if (($fieldMeta->type ?? null) === 'date') {
                        $headers[] = $excelField;
                        break; // Only add the column once
                    }
                }
            } else {
                // Single field mapped to column
                if (($meta->type ?? null) === 'date') {
                    $headers[] = $excelField;
                }
            }
        }

        return $headers;
    }

    private function normalizeDateFields(array &$row): array
    {
        $errors = [];
        $dateHeaders = $this->getDateHeadersFromTemplate();

        foreach ($dateHeaders as $h) {
            if (array_key_exists($h, $row)) {
                $originalValue = $row[$h];
                $normalizedValue = $this->toYmd($originalValue);

                if ($originalValue !== null && $originalValue !== '' && $normalizedValue === null) {
                    $errors[] = [
                        'row' => $this->rowNum,
                        'field' => $h,
                        'message' => "[Row: {$this->rowNum}] Invalid date format. Please use yyyy-mm-dd format and try again. Received: '{$originalValue}'"
                    ];
                } else {
                    $row[$h] = $normalizedValue;
                }
            }
        }
        
        return $errors;
    }

    /**
     * Normalize price/currency fields by removing currency symbols.
     * Accepts values with or without currency symbols: $3940 or 3940
     */
    private function normalizePriceFields(array &$row): void
    {
        foreach ($this->fieldMap as $excelHeader => $meta) {
            $metaArray = is_array($meta) ? $meta : [$meta];

            foreach ($metaArray as $fieldMeta) {
                $dbFieldName = $fieldMeta->name;

                if (in_array($dbFieldName, $this->fieldsToValidateForSpecialChars, true)) {
                    if (array_key_exists($excelHeader, $row)) {
                        $value = $row[$excelHeader];

                        if ($value === null || $value === '') {
                            continue;
                        }

                        $normalized = $this->normalizePrice($value);
                        $row[$excelHeader] = $normalized;
                    }
                    break;
                }
            }
        }
    }

    /**
     * Normalize a price value by removing currency symbols and formatting.
     * Examples: $3940 → 3940, $3,940.50 → 3940.50, 3940 → 3940
     */
    private function normalizePrice($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $string = trim((string) $value);

        // Remove currency symbols: $, €, £, ¥, etc.
        $string = preg_replace('/[\$€£¥₹₽¢]/', '', $string);

        // Remove spaces
        $string = str_replace(' ', '', $string);

        // Remove thousand separators (commas)
        $string = str_replace(',', '', $string);

        // Keep only digits, decimal point, and minus sign
        $string = preg_replace('/[^\d.\-]/', '', $string);

        return $string;
    }

    /**
     * 🚀 ENHANCED: Priority-based user resolution with conflict prevention and dependency hierarchy.
     * Processing Order: closer1 → setter1 → closer2 → setter2
     * Conflict Prevention: Same user cannot occupy multiple positions of same role type
     * Dependency System: Position 2 requires Position 1 to be filled first
     */
    protected function replaceEmailsWithUserIdsOnPrepared(array &$data): void
    {
        // Define the role types to process
        $roleTypes = ['closer1', 'closer2', 'setter1', 'setter2'];

        foreach ($roleTypes as $role) {
            $this->resolveUserIdFromFlexiOrEmail($data, $role);
        }
    }

    /**
     * Resolve user ID from flexible ID or email for a given role.
     * Reduces code duplication across closer1, closer2, setter1, setter2 fields.
     *
     * @param  array  $data  Data array to modify (passed by reference)
     * @param  string  $role  Role prefix (e.g., 'closer1', 'setter2')
     */
    private function resolveUserIdFromFlexiOrEmail(array &$data, string $role): void
    {
        $flexiIdKey = "{$role}_flexi_id";
        $userIdKey = "{$role}_id";
        $flexiableIdKey = "{$role}_flexiable_id"; // Legacy field name (typo preserved for compatibility)

        // Skip if neither flexi ID nor user ID field exists
        if (! array_key_exists($flexiIdKey, $data) && ! array_key_exists($userIdKey, $data)) {
            return;
        }

        // Try to match by flexible ID first
        if (array_key_exists($flexiIdKey, $data)) {
            // Normalize flexi ID: trim whitespace and convert to lowercase for matching
            $normalizedFlexiId = strtolower(trim($data[$flexiIdKey]));
            $userId = $this->flexibleIds[$normalizedFlexiId] ?? null;

            // Preserve original email value BEFORE overwriting $data[$userIdKey]
            $originalEmailOrId = $data[$userIdKey] ?? null;

            $data[$userIdKey] = $userId;
            $data[$flexiableIdKey] = $data[$flexiIdKey]; // Store original flexi ID
            unset($data[$flexiIdKey]);

            // Fallback to email matching if flexi ID didn't resolve and we have an original email value
            if (! $userId && $originalEmailOrId) {
                $data[$userIdKey] = $this->usersEmails[strtolower($originalEmailOrId)] ?? null;
            }
        } else {
            // Only email/user_id field exists - try to match by email
            if (array_key_exists($userIdKey, $data)) {
                $data[$userIdKey] = $this->usersEmails[strtolower($data[$userIdKey])] ?? null;
            }
        }
    }

    /**
     * ✅ AUTO-CORRECTION: Remove dismissed/terminated users from sale assignment.
     * Instead of blocking the sale, we remove ineligible users and allow the sale to proceed.
     * 
     * @param array $data Prepared data with user IDs (passed by reference)
     * @return void
     */
    protected function removeDismissedUsersFromSale(array &$data): void
    {
        $saleDate = $data['customer_signoff'] ?? null;
        
        if (!$saleDate) {
            // If no sale date, cannot validate user status
            return;
        }

        $roleTypes = [
            'closer1_id' => 'Closer 1',
            'closer2_id' => 'Closer 2',
            'setter1_id' => 'Setter 1',
            'setter2_id' => 'Setter 2'
        ];

        foreach ($roleTypes as $roleKey => $roleName) {
            $userId = $data[$roleKey] ?? null;
            
            if (!$userId) {
                continue; // Skip if no user assigned
            }

            $shouldRemove = false;
            $reason = '';

            // Check if user was dismissed at or before the sale date
            $dismissed = checkDismissFlag($userId, $saleDate);
            if ($dismissed && $dismissed->dismiss == 1) {
                $shouldRemove = true;
                $reason = "User was dismissed on {$dismissed->effective_date}";
            }

            // Check if user was terminated at or before the sale date
            if (!$shouldRemove) {
                $terminated = checkTerminateFlag($userId, $saleDate);
                if ($terminated && $terminated->is_terminate == 1) {
                    $shouldRemove = true;
                    $reason = "User was terminated on {$terminated->terminate_effective_date}";
                }
            }

            // Remove the user from the sale if they were dismissed/terminated
            if ($shouldRemove) {
                $data[$roleKey] = null;
                
                \Log::info('[EXCEL_IMPORT] Removed ineligible user from sale', [
                    'row_number' => $this->rowNum,
                    'role' => $roleName,
                    'user_id' => $userId,
                    'sale_date' => $saleDate,
                    'reason' => $reason,
                    'action' => 'User removed, sale will be created without this rep'
                ]);
            }
        }
    }

    protected function resolveProductIdAndCode(?string $productCode, ?string $customerSignoff): array
    {
        $productCode = strtolower(str_replace(' ', '', $productCode ?? ''));

        if (empty($productCode)) {
            return [null, null];
        }

        $product = ProductCode::withTrashed()->where('product_code', $productCode)->first();
        if (! $product) {
            return [null, null];
        }

        return [$product->product_id, $product->product_code];
    }

    protected function sanitizeRow(array &$row): void
    {
        foreach ($row as $column => $cellValue) {
            if ($cellValue === null) {
                continue;
            }

            if (is_string($cellValue)) {
                $trimmedValue = trim($cellValue);

                if ($trimmedValue === '-' || preg_match('/^-+$/', $trimmedValue)) {
                    $row[$column] = '';

                    continue;
                }

                $row[$column] = $trimmedValue;

                continue;
            }

            $row[$column] = $cellValue;
        }
    }

    protected function validateSpecialSymbols(array $row, int $rowIndex): void
    {
        foreach ($this->fieldsToValidateForSpecialChars as $header) {
            if (! empty($row[$header])) {
                $found = check_symbols_in_data($row[$header], $this->symbolsArray);
                if (! empty($found)) {
                    $this->addValidationError($header, $rowIndex, "Invalid symbol '{$found}'. Please remove formulas.");
                }
            }
        }
    }

    protected function validateRow(array $row, int $rowIndex): array
    {
        $errors = [];
        $rowTag = "[Row: {$rowIndex}]";

        foreach ($this->fieldMap as $excelField => $meta) {
            $value = trim($row[$excelField] ?? '');

            if (is_array($meta)) {
                foreach ($meta as $fieldMeta) {
                    if ($fieldMeta->mandatory && $value === '') {
                        // 🛡️ NEW: Check flexible ID alternative before marking as error
                        if (! $this->hasFlexibleIdAlternative($fieldMeta->name, $row)) {
                            $errors[] = [
                                'row' => $rowIndex,
                                'field' => $excelField,
                                'message' => "{$rowTag} Field '{$fieldMeta->name}' is mandatory and cannot be empty.",
                            ];
                        }

                        continue;
                    }

                    if ($value === '') {
                        continue;
                    }

                    $error = $this->validateFieldValue($value, $fieldMeta, $excelField);
                    if ($error !== null) {
                        $errors[] = [
                            'row' => $rowIndex,
                            'field' => $excelField,
                            'message' => "{$rowTag} {$error}",
                        ];
                    }
                }
            } else {
                if ($meta->mandatory && $value === '') {
                    // 🛡️ NEW: Check flexible ID alternative before marking as error
                    if (! $this->hasFlexibleIdAlternative($meta->name, $row)) {
                        $errors[] = [
                            'row' => $rowIndex,
                            'field' => $excelField,
                            'message' => "{$rowTag} Field is mandatory and cannot be empty.",
                        ];
                    }

                    continue;
                }

                if ($value === '') {
                    continue;
                }

                $error = $this->validateFieldValue($value, $meta, $excelField);
                if ($error !== null) {
                    $errors[] = [
                        'row' => $rowIndex,
                        'field' => $excelField,
                        'message' => "{$rowTag} {$error}",
                    ];
                }
            }
        }

        return $errors;
    }

    /**
     * 🛡️ FLEXIBLE ID VALIDATION: Check if a mandatory field has a flexible ID alternative with value
     */
    protected function hasFlexibleIdAlternative($fieldName, array $row): bool
    {
        // Define field mappings: main field => flexible ID field
        $flexiIdMappings = [
            'closer1_id' => 'closer1_flexi_id',
            'closer2_id' => 'closer2_flexi_id',
            'setter1_id' => 'setter1_flexi_id',
            'setter2_id' => 'setter2_flexi_id',
        ];

        // Check if this field has a flexible ID alternative
        if (! isset($flexiIdMappings[$fieldName])) {
            return false; // No flexible ID alternative exists
        }

        $flexiIdFieldName = $flexiIdMappings[$fieldName];

        // 🔧 BUG FIX: Find the Excel column name for the flexible ID field
        // The $row array uses Excel column names as keys, not field names
        $flexiIdExcelColumn = null;
        foreach ($this->fieldMap as $excelColumn => $meta) {
            if (is_array($meta)) {
                // Multiple fields mapped to same column
                foreach ($meta as $fieldMeta) {
                    if ($fieldMeta->name === $flexiIdFieldName) {
                        $flexiIdExcelColumn = $excelColumn;
                        break 2; // Break out of both loops
                    }
                }
            } else {
                // Single field mapped to column
                if ($meta->name === $flexiIdFieldName) {
                    $flexiIdExcelColumn = $excelColumn;
                    break;
                }
            }
        }

        if ($flexiIdExcelColumn === null) {
            \Log::warning('[FLEXIBLE_ID] Excel column not found for flexible ID field', [
                'row_number' => $this->rowNum,
                'field_name' => $fieldName,
                'flexi_field_name' => $flexiIdFieldName,
                'available_columns' => array_keys($row),
            ]);

            return false; // Excel column not found in template
        }

        $flexiIdValue = trim($row[$flexiIdExcelColumn] ?? '');
        $hasAlternative = ! empty($flexiIdValue);

        if ($hasAlternative) {
            \Log::info("[FLEXIBLE_ID] Validation bypass: {$fieldName} has flexible ID alternative", [
                'row_number' => $this->rowNum,
                'field_name' => $fieldName,
                'flexi_field_name' => $flexiIdFieldName,
                'flexi_excel_column' => $flexiIdExcelColumn,
                'flexi_value' => $flexiIdValue,
            ]);
        } else {
            \Log::info('[FLEXIBLE_ID] No flexible ID alternative found', [
                'row_number' => $this->rowNum,
                'field_name' => $fieldName,
                'flexi_field_name' => $flexiIdFieldName,
                'flexi_excel_column' => $flexiIdExcelColumn,
                'flexi_value_empty' => true,
            ]);
        }

        return $hasAlternative;
    }

    /**
     * Auto-calculate missing field for Mortgage company type
     * 
     * For Mortgage, if any 2 of 3 fields are present, calculate the third:
     * - epc = gross_account_value × net_epc
     * - gross_account_value = epc / net_epc
     * - net_epc = epc / gross_account_value
     * 
     * Note: net_epc is stored as decimal (0.0275 = 2.75%), not percentage
     * 
     * @param array $data Reference to data array to be saved
     * @return void
     */
    protected function autoCalculateMortgageFields(array &$data): void
    {
        $epc = $data['epc'] ?? null;
        $grossAccountValue = $data['gross_account_value'] ?? null;
        $netEpc = $data['net_epc'] ?? null;

        // Check which fields have valid values (not null, not empty, not zero)
        $hasEpc = !empty($epc) && is_numeric($epc) && (float)$epc > 0;
        $hasGav = !empty($grossAccountValue) && is_numeric($grossAccountValue) && (float)$grossAccountValue > 0;
        $hasNetEpc = !empty($netEpc) && is_numeric($netEpc) && (float)$netEpc > 0;

        // Count how many fields are present
        $presentFieldsCount = ($hasEpc ? 1 : 0) + ($hasGav ? 1 : 0) + ($hasNetEpc ? 1 : 0);

        // Only calculate if exactly 2 fields are present
        if ($presentFieldsCount != 2) {
            \Log::debug('[MORTGAGE_CALC] Skipped - need exactly 2 fields present', [
                'pid' => $data['pid'] ?? 'N/A',
                'present_fields_count' => $presentFieldsCount,
                'has_epc' => $hasEpc,
                'has_gav' => $hasGav,
                'has_net_epc' => $hasNetEpc,
                'row_number' => $this->rowNum,
            ]);
            return;
        }

        // Determine which field is missing and calculate it
        if (!$hasEpc && $hasGav && $hasNetEpc) {
            // Calculate epc = gross_account_value × net_epc
            // Note: net_epc is already in decimal format (0.0275 = 2.75%)
            $calculatedEpc = (float)$grossAccountValue * (float)$netEpc;
            $data['epc'] = round($calculatedEpc, 4);

            \Log::info('[MORTGAGE_CALC] Calculated epc', [
                'pid' => $data['pid'] ?? 'N/A',
                'gross_account_value' => $grossAccountValue,
                'net_epc' => $netEpc,
                'calculated_epc' => $data['epc'],
                'formula' => 'epc = GAV × net_epc',
                'row_number' => $this->rowNum,
            ]);
        } elseif (!$hasGav && $hasEpc && $hasNetEpc) {
            // Calculate gross_account_value = epc / net_epc
            // Note: net_epc is already in decimal format (0.0275 = 2.75%)
            $calculatedGav = (float)$epc / (float)$netEpc;
            $data['gross_account_value'] = round($calculatedGav, 4);

            \Log::info('[MORTGAGE_CALC] Calculated gross_account_value', [
                'pid' => $data['pid'] ?? 'N/A',
                'epc' => $epc,
                'net_epc' => $netEpc,
                'calculated_gav' => $data['gross_account_value'],
                'formula' => 'GAV = epc / net_epc',
                'row_number' => $this->rowNum,
            ]);
        } elseif (!$hasNetEpc && $hasEpc && $hasGav) {
            // Calculate net_epc = epc / gross_account_value
            // Note: Result is in decimal format (0.0275 = 2.75%)
            $calculatedNetEpc = (float)$epc / (float)$grossAccountValue;
            $data['net_epc'] = round($calculatedNetEpc, 6);

            \Log::info('[MORTGAGE_CALC] Calculated net_epc', [
                'pid' => $data['pid'] ?? 'N/A',
                'epc' => $epc,
                'gross_account_value' => $grossAccountValue,
                'calculated_net_epc' => $data['net_epc'],
                'formula' => 'net_epc = epc / GAV',
                'row_number' => $this->rowNum,
            ]);
        }
    }

    /**
     * Auto-calculate epc (Gross Revenue) if not provided
     * 
     * Formulas by company type:
     * - Mortgage: Uses new 3-field calculation logic (see autoCalculateMortgageFields)
     * - Turf/Pest: epc = gross_account_value × net_epc
     * - Solar/Others: epc = kw × net_epc (if kw exists)
     * 
     * Only calculates if:
     * - epc is empty or 0
     * - AND required calculation fields exist and > 0
     * 
     * @param array $data Reference to data array to be saved
     * @return void
     */
    protected function autoCalculateEpcIfNeeded(array &$data): void
    {
        $companyType = $this->getCompanyType();

        // For Mortgage, use the new 3-field calculation logic
        if ($companyType === 'Mortgage') {
            $this->autoCalculateMortgageFields($data);
            return;
        }

        // For other company types, use existing logic
        $epc = $data['epc'] ?? null;
        
        // Check if epc is empty or zero
        $epcIsEmpty = empty($epc) || (is_numeric($epc) && (float)$epc == 0);
        
        if (!$epcIsEmpty) {
            // epc already has a value, don't override
            \Log::debug('[AUTO_CALCULATE_EPC] Skipped - epc already has value', [
                'pid' => $data['pid'] ?? 'N/A',
                'company_type' => $companyType,
                'epc' => $epc,
                'row_number' => $this->rowNum,
            ]);
            return;
        }

        // Determine calculation method based on company type
        $calculationBase = null;
        $baseFieldName = null;
        
        // Turf, Pest use gross_account_value
        if (in_array($companyType, ['Turf', 'Pest', 'Fiber'])) {
            $calculationBase = $data['gross_account_value'] ?? null;
            $baseFieldName = 'gross_account_value';
        } 
        // Solar and others use kw
        else {
            $calculationBase = $data['kw'] ?? null;
            $baseFieldName = 'kw';
            
            // Fallback to gross_account_value if kw is not available
            if (empty($calculationBase) || !is_numeric($calculationBase) || (float)$calculationBase <= 0) {
                $calculationBase = $data['gross_account_value'] ?? null;
                $baseFieldName = 'gross_account_value';
            }
        }
        
        $netEpc = $data['net_epc'] ?? null;

        // Validate calculation inputs
        $hasCalculationBase = !empty($calculationBase) && is_numeric($calculationBase) && (float)$calculationBase > 0;
        $hasNetEpc = !empty($netEpc) && is_numeric($netEpc) && (float)$netEpc > 0;

        if ($hasCalculationBase && $hasNetEpc) {
            // Calculate epc = calculationBase × net_epc
            $calculatedEpc = (float)$calculationBase * (float)$netEpc;
            $data['epc'] = round($calculatedEpc, 4);

            \Log::info('[AUTO_CALCULATE_EPC] Calculated epc', [
                'pid' => $data['pid'] ?? 'N/A',
                'company_type' => $companyType,
                'base_field' => $baseFieldName,
                'base_value' => $calculationBase,
                'net_epc' => $netEpc,
                'calculated_epc' => $data['epc'],
                'row_number' => $this->rowNum,
            ]);
        } else {
            \Log::debug('[AUTO_CALCULATE_EPC] Skipped calculation - missing required fields', [
                'pid' => $data['pid'] ?? 'N/A',
                'company_type' => $companyType,
                'base_field' => $baseFieldName,
                'has_calculation_base' => $hasCalculationBase,
                'has_net_epc' => $hasNetEpc,
                'calculation_base' => $calculationBase,
                'net_epc' => $netEpc,
                'row_number' => $this->rowNum,
            ]);
        }
    }

    /**
     * Helper method to validate a single field value
     */
    private function validateFieldValue(string $value, $meta, string $excelField): ?string
    {
        return match ($meta->type) {
            'email' => ! filter_var($value, FILTER_VALIDATE_EMAIL)
                ? 'Invalid email.' : null,

            'number' => ! is_numeric($value)
                ? 'Must be a number.' : null,

            // 'date' validation is handled in normalizeDateFields() with detailed format instructions
            'date' => null,

            'boolean' => ! in_array(strtolower($value), ['true', 'false', '0', '1'], true)
                ? 'Must be boolean.' : null,

            'uuid' => ! preg_match('/^[0-9a-fA-F-]{36}$/', $value)
                ? 'Invalid UUID.' : null,

            default => null,
        };
    }

    protected function isValidDate($value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $d = \DateTime::createFromFormat('Y-m-d', $value);

            return $d && $d->format('Y-m-d') === $value;
        }

        if (is_numeric($value)) {
            $timestamp = ((int) $value - 25569) * 86400;
            $date = gmdate('Y-m-d', $timestamp);

            return (bool) strtotime($date);
        }

        foreach (['m/d/Y', 'd/m/Y', 'd.m.Y', 'm-d-Y'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $value);
            if ($d && $d->format($fmt) === $value) {
                return true;
            }
        }

        return false;
    }

    protected function addValidationError(string $field, int $rowIndex, string $message): void
    {
        $this->errors[] = [
            'row' => $rowIndex,
            'field' => $field,
            'message' => "[Row: {$rowIndex}] {$message}",
        ];
    }

    protected function registerErrors(array $errors): void
    {
        foreach ($errors as $error) {
            $this->errors[] = $error;
        }

        $this->errorRecords++;
        $this->message = 'Import Failed';
    }

    protected function registerSuccess($row, int $index): void
    {
        $this->salesSuccessReport[$row['pid'] ?? $index][] = 'Success!!';
    }

    protected function registerSkipped(array $errors, array $row, int $rowIndex): void
    {
        $this->skippedRecords++;
        $this->salesSkippedReport[] = [
            'row' => $rowIndex,
            'errors' => $errors,
        ];
    }
}
