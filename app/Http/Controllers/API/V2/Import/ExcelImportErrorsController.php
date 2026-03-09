<?php

namespace App\Http\Controllers\API\V2\Import;

use App\Http\Controllers\API\V2\Sales\BaseController;
use App\Models\ExcelImportHistory;
use App\Models\LegacyApiRawDataHistory;
use Illuminate\Http\Request;

class ExcelImportErrorsController extends BaseController
{
    /**
     * Get error records for a specific Excel import
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getImportErrors(Request $request)
    {
        $this->checkValidations($request->all(), [
            'excel_import_id' => 'required|integer|exists:excel_import_history,id'
        ]);

        $excelImportId = (int) $request->input('excel_import_id');

        // Get the import history record
        $importHistory = ExcelImportHistory::find($excelImportId);
        
        if (!$importHistory) {
            return response()->json([
                'ApiName' => 'get_excel_import_errors',
                'status' => false,
                'message' => 'Excel import history not found.',
                'data' => []
            ], 404);
        }

        // Get error records from LegacyApiRawDataHistory (main table)
        $errorRecords = LegacyApiRawDataHistory::where('excel_import_id', $excelImportId)
            ->whereNotNull('import_status_description')
            ->select('id', 'pid', 'excel_import_id', 'import_status_reason', 'import_status_description', 'created_at')
            ->orderBy('id', 'ASC')
            ->get();

        // CRITICAL FIX: Fallback to log table if main table is empty
        // Records may have been deleted by the scheduled cleanup job (app:log-legacy-api-data)
        // but they're preserved in the log table for historical reporting
        if ($errorRecords->isEmpty()) {
            $errorRecords = \App\Models\LegacyApiRawDataHistoryLog::where('excel_import_id', $excelImportId)
                ->whereNotNull('import_status_description')
                ->where('action_type', 'dump') // These were deleted by cleanup job
                ->select('id', 'pid', 'excel_import_id', 'import_status_reason', 'import_status_description', 'changed_at as created_at')
                ->orderBy('id', 'ASC')
                ->get();

            if ($errorRecords->isNotEmpty()) {
                \Log::info('ExcelImportErrorsController: Retrieved error records from log table (fallback)', [
                    'excel_import_id' => $excelImportId,
                    'count' => $errorRecords->count(),
                ]);
            }
        }

        $response = [];
        foreach ($errorRecords as $record) {
            // Parse row number from import_status_description
            // Format: [Approved Date]: [Row: 2] Invalid date format...
            // OR JSON format: ["[Row: 2] Invalid date format..."]
            $rowNumber = null;
            $description = $record->import_status_description;
            
            // Handle JSON-encoded descriptions (from log table)
            if (is_string($description) && str_starts_with($description, '[') && str_ends_with($description, ']')) {
                $decoded = json_decode($description, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && count($decoded) > 0) {
                    $description = implode(' | ', $decoded);
                }
            }
            
            if ($description) {
                // Extract row number from [Row: X] pattern
                if (preg_match('/\[Row:\s*(\d+)\]/', $description, $matches)) {
                    $rowNumber = (int) $matches[1];
                }
            }

            $response[] = [
                'id' => $record->id,
                'pid' => $record->pid,
                'excel_import_id' => $record->excel_import_id,
                'import_status_reason' => $record->import_status_reason,
                'import_status_description' => $description,
                'rowNumber' => $rowNumber,
                'created_at' => $record->created_at,
            ];
        }

        // FALLBACK: If no individual error records found, check if the import has global errors
        // This handles catastrophic failures where records were marked as failed without individual descriptions
        if (empty($response) && $importHistory->status === 2 && !empty($importHistory->errors)) {
            $globalErrors = is_string($importHistory->errors)
                ? json_decode($importHistory->errors, true)
                : $importHistory->errors;
            
            if (is_array($globalErrors) && !empty($globalErrors)) {
                // Get all error records (import_to_sales = 2) even without description
                $errorRecordsWithoutDescription = LegacyApiRawDataHistory::where('excel_import_id', $excelImportId)
                    ->where('import_to_sales', '2')
                    ->select('id', 'pid', 'excel_import_id', 'row_number', 'created_at')
                    ->orderBy('id', 'ASC')
                    ->get();

                // Extract the global error message
                $globalErrorMessage = null;
                foreach ($globalErrors as $errorItem) {
                    if (is_string($errorItem)) {
                        // Handle JSON-encoded error strings
                        $decoded = json_decode($errorItem, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $globalErrorMessage = implode(' | ', $decoded['errors'] ?? [$decoded['message'] ?? 'Unknown error']);
                        } else {
                            $globalErrorMessage = $errorItem;
                        }
                    } elseif (is_array($errorItem)) {
                        $globalErrorMessage = implode(' | ', $errorItem['errors'] ?? [$errorItem['message'] ?? 'Unknown error']);
                    }
                    break; // Use first error
                }

                foreach ($errorRecordsWithoutDescription as $record) {
                    $response[] = [
                        'id' => $record->id,
                        'pid' => $record->pid,
                        'excel_import_id' => $record->excel_import_id,
                        'import_status_reason' => 'Import Process Error',
                        'import_status_description' => $globalErrorMessage ?? 'Import failed due to a system error. Please re-upload your file.',
                        'rowNumber' => $record->row_number,
                        'created_at' => $record->created_at,
                    ];
                }
            }
        }

        return response()->json([
            'ApiName' => 'get_excel_import_errors',
            'status' => true,
            'message' => 'Error records retrieved successfully.',
            'data' => [
                'excel_import_id' => $excelImportId,
                'total_errors' => count($response),
                'error_records' => $response
            ]
        ]);
    }
}

