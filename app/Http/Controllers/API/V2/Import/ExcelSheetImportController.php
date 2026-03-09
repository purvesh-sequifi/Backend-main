<?php

namespace App\Http\Controllers\API\V2\Import;

use App\Core\Traits\ReconTraits\ReconRoutineTraits;
use App\Core\Traits\SaleTraits\EditSaleTrait;
use App\Core\Traits\SaleTraits\SubroutineTrait;
use App\Exports\Sale\SampleSaleExport;
use App\Http\Controllers\API\V2\Sales\BaseController;
use App\Imports\Sales\FiberSalesImport;
use App\Imports\Sales\MortgageSalesImport;
use App\Imports\Sales\PestSalesImport;
use App\Imports\Sales\RoofingSalesImport;
use App\Imports\Sales\SolarSalesImport;
use App\Imports\Sales\TurfSalesImport;
use App\Jobs\Sales\ExcelSalesProcessJob;
use App\Models\CompanyProfile;
use App\Models\ExcelImportHistory;
use App\Models\FiberSalesImportTemplate;
use App\Models\LegacyApiRawDataHistory;
use App\Models\MortgageSalesImportTemplate;
use App\Models\PestSalesImportTemplate;
use App\Models\RoofingSalesImportTemplate;
use App\Models\SolarSalesImportTemplate;
use App\Models\TurfSalesImportTemplate;
use App\Models\User;
use App\Models\UserFlexibleId;
use App\Models\UsersAdditionalEmail;
use App\Services\JobNotificationService;
use App\Services\Csv\CsvRowStreamer;
use App\Traits\EmailNotificationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

class ExcelSheetImportController extends BaseController
{
    use EditSaleTrait, EmailNotificationTrait, ReconRoutineTraits, SubroutineTrait;

    private ?CompanyProfile $cachedCompanyProfile = null;

    private function getCompanyProfile(): CompanyProfile
    {
        return Cache::remember('company_profile', 3600,
            fn () => CompanyProfile::first()
        );
    }

    public function downloadSaleSample(Request $request)
    {
        $templateId = (int) $request->input('template_id');

        $companyProfile = $this->getCompanyProfile();
        $triggerDate = getTriggerDatesForSample();

        return Excel::download(
            new SampleSaleExport($triggerDate, $companyProfile, $templateId),
            'sample.csv',
            ExcelWriter::CSV
        );
    }

    public function salesImportValidation(Request $request): JsonResponse
    {
        $uploadedFilename = $request->file('file')?->getClientOriginalName();
        $this->checkValidations($request->all(), [
            'file' => [
                'required',
                'file',
                'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel,application/x-csv,text/comma-separated-values',
            ],
        ]);

        if (($request->validate_only ?? 1) != 0) {
            $request['validate_only'] = 1;
        }

        $companyProfile = $this->getCompanyProfile();
        $companyType = $companyProfile->company_type;

        $templateId = (int) ($request->input('template_id') ?? 1);

        $templateModelClass = $this->resolveTemplateModelByCompanyType($companyType);
        if (! $templateModelClass || ! $templateModelClass::where('id', $templateId)->exists()) {
            return response()->json([
                'ApiName' => 'import_api',
                'status' => false,
                'message' => 'Selected template not found for this company.',
                'error' => [],
                'fileName' => $uploadedFilename,
                'failed_all' => 1,
            ], 404);
        }

        $uploadedFile = $request->file('file'); /** @var \Illuminate\Http\UploadedFile $uploadedFile */
        $csvRealPath = $uploadedFile->getRealPath();

        $streamer = new CsvRowStreamer($csvRealPath);

        $rowCount = $streamer->getRowCount();
        if ($rowCount > 10000) {
            return response()->json([
                'ApiName' => 'import_api',
                'status' => false,
                'message' => 'File contains too many rows. Maximum allowed is 10,000 rows. Your file has '.$rowCount.' rows.',
                'error' => [
                    [
                        'row' => 0,
                        'field' => 'file_size',
                        'message' => 'File contains too many rows. Maximum allowed is 10,000 rows. Your file has '.$rowCount.' rows.',
                    ],
                ],
                'fileName' => $uploadedFilename,
                'failed_all' => 1,
            ], 400);
        }

        $importSales = match (true) {
            $companyType === CompanyProfile::FIBER_COMPANY_TYPE => new FiberSalesImport,
            $companyType === CompanyProfile::SOLAR_COMPANY_TYPE => new SolarSalesImport,
            $companyType === CompanyProfile::TURF_COMPANY_TYPE => new TurfSalesImport,
            $companyType === CompanyProfile::ROOFING_COMPANY_TYPE => new RoofingSalesImport,
            $companyType === CompanyProfile::MORTGAGE_COMPANY_TYPE => new MortgageSalesImport,
            in_array($companyType, CompanyProfile::PEST_COMPANY_TYPE, true) => new PestSalesImport,
            default => throw new \RuntimeException("Unsupported company type: {$companyType}"),
        };

        $importSales->setTemplateId($templateId);
        $importSales->initTemplateFields();

        $importSales->validateOnly      = (int) $request->validate_only;
        $importSales->skipErrorRows     = filter_var($request->skip_error_rows ?? false, FILTER_VALIDATE_BOOLEAN);
        $importSales->headerError       = [];
        $importSales->newRecords        = 0;
        $importSales->updatedRecords    = 0;
        $importSales->errorRecords      = 0;
        $importSales->totalRecords      = 0;
        $importSales->salesErrorReport  = [];
        $importSales->salesSuccessReport= [];
        $importSales->salesSkippedReport= [];
        $importSales->skippedRecords    = 0;
        $importSales->maxTriggerCount   = count(getTriggerDatesForSample());
        $importSales->flexibleIds       = $this->getAllFlexibleIds();
        $importSales->usersEmails       = $this->getAllUsersEmail();
        $importSales->errors            = [];
        $importSales->ids               = [];
        $importSales->message           = '';

        $streamer->stream(function(array $row, int $rowIndex) use ($importSales) {
            $importSales->model($row);
        });

        if (!empty($importSales->errors) && !$importSales->skipErrorRows) {
            return response()->json([
                'ApiName' => 'import_api',
                'status' => false,
                'message' => $importSales->message ?? 'Import failed due to validation errors.',
                'error' => $importSales->errors,
                'fileName' => $uploadedFilename,
                'failed_all' => 1,
            ], 400);
        }

        // WHEN CSV HAVE NO DATA TO IMPORT
        if (! $importSales->totalRecords) {
            return response()->json([
                'ApiName' => 'import_api',
                'status' => false,
                'message' => "Apologies, The uploaded CSV file doesn't have any data to import or the given file in invalid!!",
                'error' => $importSales->errors,
                'fileName' => $uploadedFilename,
                'failed_all' => 2,
            ], 400);
        }

        // Handle skip error rows mode
        if ($importSales->skipErrorRows) {
            $validRecords = $importSales->totalRecords - $importSales->skippedRecords;

            if ($validRecords === 0) {
                return response()->json([
                    'ApiName' => 'import_api',
                    'status' => false,
                    'message' => 'All rows contain errors. No valid data to import.',
                    'error' => [],
                    'skipped_rows' => $importSales->skippedRecords,
                    'skipped_report' => $importSales->salesSkippedReport,
                    'fileName' => $uploadedFilename,
                    'failed_all' => 1
                ], 400);
            }

            $response = [
                'ApiName' => 'import_api',
                'status' => true,
                'message' => $importSales->message ?? 'Import successful with skipped rows.',
                'error' => [],
                'skipped_rows' => $importSales->skippedRecords,
                'skipped_report' => $importSales->salesSkippedReport,
                'fileName' => $uploadedFilename,
                'failed_all' => 0
            ];

            if ($request['validate_only'] == 0) {
                $response['data'] = [
                    'new_records' => $importSales->newRecords,
                    'updated_records' => $importSales->updatedRecords,
                    'error_records' => $importSales->skippedRecords,
                    'skipped_records' => $importSales->skippedRecords,
                    'total_records' => $importSales->totalRecords,
                    'ids' => $importSales->ids,
                    'salesErrorReport' => $importSales->salesErrorReport,
                    'salesSuccessReport' => $importSales->salesSuccessReport,
                    'salesSkippedReport' => $importSales->salesSkippedReport
                ];
            }

            return response()->json($response, 200);
        }

        // WHEN CSV HAVE DATA & THE NUMBER OF ERROR IS NOT SAME AS TOTAL NUMBER OF IMPORTED RECORDS
        if ($importSales->totalRecords && $importSales->totalRecords != $importSales->errorRecords) {
            $status = true;
            $statusCode = 200;
            if ($importSales->errorRecords) {
                $status = false;
                $statusCode = 400;
            }

            $response = [
                'ApiName' => 'import_api',
                'status' => $status,
                'message' => $importSales->message,
                'error' => $importSales->errors,
                'fileName' => $uploadedFilename,
                'failed_all' => 0,
            ];
            if ($request['validate_only'] == 0) {
                $response['data'] = [
                    'new_records' => $importSales->newRecords,
                    'updated_records' => $importSales->updatedRecords,
                    'error_records' => $importSales->errorRecords,
                    'total_records' => $importSales->totalRecords,
                    'ids' => $importSales->ids,
                    'salesErrorReport' => $importSales->salesErrorReport,
                    'salesSuccessReport' => $importSales->salesSuccessReport,
                ];
            }

            return response()->json($response, $statusCode);
        } else {
            // WHEN CSV DOESN'T HAVE DATA OR THE NUMBER OF ERROR IS SAME AS TOTAL NUMBER OF IMPORTED RECORDS
            return response()->json([
                'ApiName' => 'import_api',
                'status' => false,
                'message' => $importSales->message,
                'error' => $importSales->errors,
                'fileName' => $uploadedFilename,
                'failed_all' => 1,
            ], 400);
        }
    }

    private function getAllFlexibleIds()
    {
        // Normalize keys: trim whitespace and convert to lowercase for consistent matching
        $flexibleIds = UserFlexibleId::whereNotNull('flexible_id_value')
            ->with('user:id,first_name,last_name,email') // Eager load for error messages
            ->orderBy('id', 'asc') // Consistent ordering for deterministic behavior
            ->get();

        $result = [];
        $firstOccurrences = []; // Store full objects to avoid N+1 queries in duplicate detection
        $duplicates = [];

        foreach ($flexibleIds as $item) {
            $normalizedKey = strtolower(trim($item->flexible_id_value));

            // Detect collisions (case-insensitive duplicates)
            if (isset($result[$normalizedKey])) {
                // Use stored object reference to access eager-loaded user data (no extra query)
                $firstItem = $firstOccurrences[$normalizedKey];

                $duplicates[] = [
                    'normalized' => $normalizedKey,
                    'first_occurrence' => [
                        'original' => $firstItem->flexible_id_value,
                        'user_id' => $firstItem->user_id,
                        'user_name' => $firstItem->user ? "{$firstItem->user->first_name} {$firstItem->user->last_name}" : 'Unknown',
                    ],
                    'duplicate_occurrence' => [
                        'original' => $item->flexible_id_value,
                        'user_id' => $item->user_id,
                        'user_name' => $item->user ? "{$item->user->first_name} {$item->user->last_name}" : 'Unknown',
                    ],
                    'resolution' => 'Kept first occurrence (lowest ID)',
                ];
                // Skip this duplicate - keep the first occurrence (deterministic)
                continue;
            }

            $result[$normalizedKey] = $item->user_id;
            $firstOccurrences[$normalizedKey] = $item; // Store full object for potential duplicate logging
        }

        // Log warning if duplicates found (shouldn't happen with current validation)
        // This indicates a data integrity issue that needs immediate attention
        if (!empty($duplicates)) {
            \Log::error('[FLEXIBLE_ID] CRITICAL: Duplicate normalized flexible IDs detected during import', [
                'count' => count($duplicates),
                'duplicates' => $duplicates,
                'recommendation' => 'Manually resolve duplicate flexible IDs in user_flexible_ids table immediately',
                'impact' => 'Import may match wrong users, causing incorrect commission attribution',
            ]);

            // Store duplicates for potential user notification
            session()->put('flexiid_duplicates_warning', [
                'count' => count($duplicates),
                'message' => 'Warning: Duplicate flexible IDs detected. Some imports may match unexpected users. Contact support.',
            ]);
        }

        return $result;
    }

    private function getAllUsersEmail()
    {
        return array_merge(User::selectRaw('LOWER(email) as email, id')->whereNotNull('email')->pluck('id', 'email')->toArray(), UsersAdditionalEmail::selectRaw('LOWER(email) as email, user_id')->whereNotNull('email')->pluck('user_id', 'email')->toArray());
    }

    public function salesImport(Request $request): JsonResponse
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        $uploadedFilename = $request->file('file')?->getClientOriginalName();

        // Validate required fields including template_id
        $this->checkValidations($request->all(), [
            'file' => 'required|mimes:csv,txt',
            'template_id' => 'required|integer|min:1',
        ]);

        $templateId = (int) $request->input('template_id');

        $request['validate_only'] = 0;
        $request['skip_error_rows'] = filter_var($request->input('skip_error_rows', false), FILTER_VALIDATE_BOOLEAN);
        $importSales = $this->salesImportValidation($request);
        $importSales = $importSales->getOriginalContent();
        if ($importSales['failed_all']) {
            return response()->json($importSales, 400);
        }
        $importSale = $importSales['data'];

        // STORE FILE ON S3 PRIVATE BUCKET
        $originalFileName = str_replace(' ', '_', $request->file('file')->getClientOriginalName());
        $fileName = config('app.domain_name').'/'.'excel_uploads/'.time().'_'.$originalFileName;
        $s3response = s3_upload($fileName, $request->file('file'), true);

        $user = Auth::user();
        $excel = [
            'user_id' => $user->id,
            'uploaded_file' => $fileName,
            'new_records' => 0,
            'updated_records' => 0,
            'error_records' => $importSale['error_records'] ?? 0,
            'total_records' => $importSale['total_records'],
            'template_id' => $templateId,
            'created_at' => now()->setTimezone('UTC'),
            'updated_at' => now()->setTimezone('UTC'),
        ];

        $skipMode = filter_var($request->input('skip_error_rows', false), FILTER_VALIDATE_BOOLEAN);
        $validRecords = $skipMode
            ? ($importSale['total_records'] - ($importSale['skipped_records'] ?? 0))
            : ($importSale['total_records'] - $importSale['error_records']);

        // WHEN CSV HAVE DATA & THERE ARE VALID RECORDS TO IMPORT
        if ($importSale['total_records'] && $validRecords > 0) {
            $excel['status'] = 1;
            $excel = ExcelImportHistory::create($excel);

            // CRITICAL: Check if records were properly linked before dispatching job
            if (empty($importSale['ids'])) {
                // NO RECORDS TO LINK - This is a critical error
                $errorDetails = [
                    'error_type' => 'linking_error',
                    'message' => 'Import failed: No records were linked to this import. Records may have been created in a previous validation step. Please re-upload your file.',
                    'technical_details' => 'The validation step did not return any record IDs to link to this import.',
                    'timestamp' => now()->toIso8601String(),
                ];

                $excel->update([
                    'status' => 2, // Failed
                    'errors' => json_encode($errorDetails),
                ]);

                \Log::error('Excel Import Linking Error', [
                    'excel_id' => $excel->id,
                    'user_id' => $user->id,
                    'total_records' => $importSale['total_records'],
                    'error' => $errorDetails,
                ]);

                return response()->json([
                    'ApiName' => 'import_api',
                    'status' => false,
                    'fileName' => $uploadedFilename,
                    'message' => $errorDetails['message'],
                    'error' => $errorDetails,
                    'failed_all' => 1,
                ], 400);
            }

            // Update excel_import_id for all records (both valid and error records)
            // 🔧 FIX: Use DB transaction to ensure linking completes before dispatch
            DB::beginTransaction();
            try {
                $linkedCount = LegacyApiRawDataHistory::whereIn('id', $importSale['ids'])->update(['excel_import_id' => $excel->id]);

                // VERIFICATION: Ensure records were actually linked
                if ($linkedCount === 0) {
                    DB::rollBack();

                    $errorDetails = [
                        'error_type' => 'linking_error',
                        'message' => 'Import failed: Records could not be linked to this import. The records may have been processed already. Please re-upload your file.',
                        'technical_details' => "Attempted to link {$importSale['total_records']} records but 0 were updated in the database.",
                        'attempted_ids_count' => count($importSale['ids']),
                        'timestamp' => now()->toIso8601String(),
                    ];

                    $excel->update([
                        'status' => 2, // Failed
                        'errors' => json_encode($errorDetails),
                    ]);

                    \Log::error('Excel Import Linking Verification Failed', [
                        'excel_id' => $excel->id,
                        'user_id' => $user->id,
                        'attempted_ids_count' => count($importSale['ids']),
                        'linked_count' => $linkedCount,
                        'error' => $errorDetails,
                    ]);

                    return response()->json([
                        'ApiName' => 'import_api',
                        'status' => false,
                        'fileName' => $uploadedFilename,
                        'message' => $errorDetails['message'],
                        'error' => $errorDetails,
                        'failed_all' => 1,
                    ], 400);
                }

                // 🔧 CRITICAL: Commit transaction to ensure linking is persisted BEFORE dispatching job
                DB::commit();

                \Log::info('Excel Import Records Linked Successfully', [
                    'excel_id' => $excel->id,
                    'linked_count' => $linkedCount,
                    'total_records' => $importSale['total_records'],
                    'user_id' => $user->id,
                ]);

            } catch (\Throwable $e) {
                DB::rollBack();

                $errorDetails = [
                    'error_type' => 'linking_exception',
                    'message' => 'Import failed: An error occurred while linking records. Please try again.',
                    'technical_details' => $e->getMessage(),
                    'timestamp' => now()->toIso8601String(),
                ];

                $excel->update([
                    'status' => 2,
                    'errors' => json_encode($errorDetails),
                ]);

                \Log::error('Excel Import Linking Exception', [
                    'excel_id' => $excel->id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                return response()->json([
                    'ApiName' => 'import_api',
                    'status' => false,
                    'fileName' => $uploadedFilename,
                    'message' => $errorDetails['message'],
                    'error' => $errorDetails,
                    'failed_all' => 1,
                ], 400);
            }

            // 🔧 CRITICAL FIX: Dispatch job FIRST before any risky operations (email/view)
            // This ensures job is always dispatched even if email fails
            $importInitiatedAt = now()->toIso8601String();
            $importUniqueKey = 'excel_sales_' . ($excel->id ?? 'unknown') . '_' . time();
            dispatch(new ExcelSalesProcessJob($user, $excel, $importUniqueKey, $importInitiatedAt))->delay(now()->addSeconds(2));

            // Emit ONE "queued" notification immediately after dispatch so the UI can show feedback without spam.
            // The job/controller will update this SAME card using the stable uniqueKey.
            try {
                app(JobNotificationService::class)->notify(
                    (int) $user->id,
                    'sales_excel_import',
                    'Sales import',
                    'started',
                    0,
                    'Sales import queued. We are processing your CSV in the background.',
                    $importUniqueKey,
                    $importInitiatedAt,
                    null,
                    [
                        'excel_id' => $excel->id ?? null,
                        'phase' => 'queued',
                    ]
                );
            } catch (\Throwable) {
                // best-effort only
            }

            \Log::info('Excel Import Job Dispatched', [
                'excel_id' => $excel->id,
                'user_id' => $user->id,
                'total_records' => $importSale['total_records'],
                'delay_seconds' => 2,
            ]);

            // NOW send confirmation email (if this fails, job already dispatched)
            try {
                $emailData = [
                    'errorReports' => $importSale['salesErrorReport'] ?? [],
                    'successReports' => $importSale['salesSuccessReport'] ?? [],
                    'skippedReports' => $importSale['salesSkippedReport'] ?? [],
                    'user' => $user,
                    'valid' => true,
                    'skipMode' => $skipMode
                ];

                $data = [
                    'email' => $user->email,
                    'subject' => 'Sale CSV Import Confirmation',
                    'template' => view('mail.saleImportFailed', $emailData)
                ];
                $this->sendEmailNotification($data);
            } catch (\Throwable $e) {
                // Log but don't fail - job already dispatched!
                \Log::warning('Email notification failed but import proceeding', [
                    'excel_id' => $excel->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $responseMessage = 'Sales import started. We are processing your CSV in the background. You will receive an email when it is completed.';

            if ($skipMode && isset($importSale['skipped_records']) && $importSale['skipped_records'] > 0) {
                $responseMessage .= " Note: {$importSale['skipped_records']} row(s) were skipped due to errors.";
            }

            return response()->json([
                'ApiName' => 'import_api',
                'status' => true,
                'fileName' => $uploadedFilename,
                'message' => $responseMessage,
                'error' => $importSales['error'] ?? [],
                'skipped_rows' => $importSale['skipped_records'] ?? 0,
                'failed_all' => 0
            ]);
        } else {
            // WHEN CSV DOESN'T HAVE DATA OR NO VALID RECORDS TO IMPORT
            $errorDetails = [
                'error_type' => 'validation_error',
                'message' => $importSales['message'] ?? 'Import failed: No valid records found in the uploaded file.',
                'technical_details' => 'The uploaded file failed validation. No valid records were found to import.',
                'validation_errors' => $importSales['error'] ?? [],
                'total_records' => $importSale['total_records'] ?? 0,
                'error_records' => $importSale['error_records'] ?? 0,
                'timestamp' => now()->toIso8601String(),
            ];

            $excel['status'] = 2;
            $excel['errors'] = json_encode($errorDetails);
            ExcelImportHistory::create($excel);

            return response()->json([
                'ApiName' => 'import_api',
                'status' => false,
                'fileName' => $uploadedFilename,
                'message' => $importSales['message'],
                'error' => $importSales['error'],
                'failed_all' => 1,
            ], 400);
        }
    }

    public function excelImportHistory(Request $request): JsonResponse
    {
        $processing = ExcelImportHistory::where('status', 1)->exists() ? 1 : 0;
        $companyType = optional(CompanyProfile::first())->company_type;

        $templateModel = $this->resolveTemplateModelByCompanyType($companyType);

        $templatesIndex = $templateModel
            ? $templateModel::withTrashed()->get(['id', 'name', 'deleted_at'])->keyBy('id')
            : collect();

        $importList = ExcelImportHistory::with('users')
            ->select('*') // Select all base table columns first
            ->addSelect([
                \DB::raw('ROUND(GREATEST(0, LEAST(100, ((new_records + updated_records + error_records) / NULLIF(total_records, 0)) * 100)), 2) AS progress_percentage'),
            ])
            ->orderBy('id', 'DESC')
            ->paginate($request->perpage ? (int) $request->perpage : 10);

        $response = [];
        $stuckImportThresholdHours = 24; // Consider imports stuck after 24 hours

        foreach ($importList as $row) {
            $tempUrl = ! empty($row->uploaded_file) ? s3_getTempUrl($row->uploaded_file) : null;
            $date = date('Y-m-d', strtotime($row->created_at));
            $time = date('H:i:s', strtotime($row->created_at));
            $createdIso = $date.'T'.$time.'.000000Z';
            $filename = $row->uploaded_file ? basename($row->uploaded_file) : null;

            $tpl = $templatesIndex->get($row->template_id);

            // AUTO-FIX: Detect and fix stuck imports (status=1, progress=0, older than threshold)
            $isStuckImport = false;
            if ($row->status == 1 &&
                (float) $row->progress_percentage == 0 &&
                now()->diffInHours($row->created_at) >= $stuckImportThresholdHours &&
                empty($row->errors)) {

                $isStuckImport = true;

                // Check if records were linked
                $linkedCount = LegacyApiRawDataHistory::where('excel_import_id', $row->id)
                    ->where('data_source_type', 'excel')
                    ->count();

                $errorType = $linkedCount === 0 ? 'no_records_found' : 'processing_stalled';
                $errorMessage = $linkedCount === 0
                    ? 'Import failed: No records were found to process. Please re-upload your file.'
                    : "Import processing stalled. Found {$linkedCount} records but processing never completed.";

                $errorDetails = [
                    'error_type' => $errorType,
                    'message' => $errorMessage,
                    'technical_details' => "Import was stuck in processing state for " . now()->diffInHours($row->created_at) . " hours. Auto-detected and marked as failed.",
                    'linked_records_count' => $linkedCount,
                    'auto_fixed' => true,
                    'fixed_at' => now()->toIso8601String(),
                    'timestamp' => $row->created_at,
                ];

                // Update the database
                try {
                    ExcelImportHistory::where('id', $row->id)->update([
                        'status' => 2, // Failed
                        'errors' => json_encode($errorDetails),
                    ]);

                    // Update the current row object for response
                    $row->status = 2;
                    $row->errors = json_encode($errorDetails);

                    \Log::info('Auto-fixed stuck Excel import', [
                        'excel_id' => $row->id,
                        'age_hours' => now()->diffInHours($row->created_at),
                        'linked_count' => $linkedCount,
                    ]);
                } catch (\Throwable $e) {
                    \Log::error('Failed to auto-fix stuck import', [
                        'excel_id' => $row->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Decode errors JSON field if it exists
            $errors = null;
            if (!empty($row->errors)) {
                try {
                    $errors = is_string($row->errors) ? json_decode($row->errors, true) : $row->errors;
                } catch (\Throwable $e) {
                    $errors = null;
                }
            }

            // Phase message for same-row progress: "File uploading" 0-100% then "Sale processing" 0-100% then "Completed"
            $currentPhase = $row->current_phase ?? null;
            $phaseProgress = isset($row->phase_progress) ? (float) $row->phase_progress : null;
            $rowPct = (float) $row->progress_percentage;
            $phaseMessage = ExcelImportHistory::resolvePhaseMessage((int) $row->status, $currentPhase);
            $displayProgress = (float) ExcelImportHistory::resolveDisplayProgress(
                status: (int) $row->status,
                currentPhase: $currentPhase,
                phaseProgress: $phaseProgress,
                rowPct: $rowPct,
                saleProcessingFallbackToRowPct: false,
            );

            // Integer percentage so Import History and Notifications show the same value (e.g. 25%).
            $displayProgress = (int) round((float) max(0, min(100, $displayProgress)));

            $response[] = [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'user_name' => $this->formatUserName($row->users),
                'filename' => $filename,
                'uploaded_file' => $row->uploaded_file,
                'new_records' => $row->new_records,
                'updated_records' => $row->updated_records,
                'error_records' => $row->error_records,
                'total_records' => $row->total_records,
                'status' => $row->status,
                'current_phase' => $currentPhase,
                'phase_progress' => $phaseProgress,
                'phase_message' => $phaseMessage,
                'progress_percentage' => (float) $row->progress_percentage,
                'display_progress' => $displayProgress,
                'template_id' => $row->template_id,
                'template_name' => $tpl->name ?? null,
                'template_deleted' => $tpl?->deleted_at !== null,
                'created_at' => $createdIso,
                'uploaded_file_s3' => $tempUrl,
                'errors' => $errors, // Include error details for frontend display
                'users' => $row->users,
                // PIDs tracking for debugging
                'updated_pids' => $row->updated_pids ?? [],
                'new_pids' => $row->new_pids ?? [],
                'error_pids' => $row->error_pids ?? [],
            ];
        }

        $paginated = $importList->toArray();
        $paginated['data'] = $response;

        return response()->json([
            'ApiName' => 'get_excel_import_list',
            'status' => true,
            'processing' => $processing,
            'data' => $paginated,
        ]);
    }

    private function resolveTemplateModelByCompanyType(?string $companyType): ?string
    {
        if ($companyType === null) {
            return null;
        }

        return match (true) {
            $companyType === CompanyProfile::FIBER_COMPANY_TYPE => FiberSalesImportTemplate::class,
            $companyType === CompanyProfile::SOLAR_COMPANY_TYPE => SolarSalesImportTemplate::class,
            $companyType === CompanyProfile::TURF_COMPANY_TYPE => TurfSalesImportTemplate::class,
            $companyType === CompanyProfile::ROOFING_COMPANY_TYPE => RoofingSalesImportTemplate::class,
            $companyType === CompanyProfile::MORTGAGE_COMPANY_TYPE => MortgageSalesImportTemplate::class,
            in_array($companyType, CompanyProfile::PEST_COMPANY_TYPE, true) => PestSalesImportTemplate::class,

            default => null,
        };
    }

    private function formatUserName($user): ?string
    {
        if (! $user) {
            return null;
        }

        $first = $user->first_name ?? null;
        $last = $user->last_name ?? null;

        if ($first || $last) {
            return trim($first.' '.$last);
        }

        return null;
    }
}

