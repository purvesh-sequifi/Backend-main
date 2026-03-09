<?php

namespace App\Http\Controllers\API\V2\MilestoneSchema;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\FiberSalesImportField;
use App\Models\MilestoneProductAuditLog;
use App\Models\MilestoneSchema;
use App\Models\MilestoneSchemaTrigger;
use App\Models\MortgageSalesImportField;
use App\Models\PestSalesImportField;
use App\Models\PositionProduct;
use App\Models\ProductMilestoneHistories;
use App\Models\RoofingSalesImportField;
use App\Models\SchemaTriggerDate;
use App\Models\SolarSalesImportField;
use App\Models\TurfSalesImportField;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MilestoneSchemaController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = MilestoneSchema::with(['products' => function ($q) {
                $q->whereDate('effective_date', '<=', date('Y-m-d'));
            }, 'products.productList'])->withCount('milestone_trigger');
            if ($request->filled('noofpayment')) {
                $query->having('milestone_trigger_count', $request->input('noofpayment'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('product')) {
                $product = $request->input('product');
                $query->whereHas('products', function ($q) use ($product) {
                    $q->whereDate('effective_date', '<=', date('Y-m-d'))->where('product_milestone_histories.product_id', $product);
                });
            }
            if ($request->filled('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('schema_name', 'LIKE', '%'.$request->input('search').'%')
                        ->orWhere('schema_description', 'LIKE', '%'.$request->input('search').'%');
                });
            }
            $perPage = (int) $request->input('per_page', 10);
            $milestones = $query->orderBy('id', 'DESC')->paginate($perPage);

            $milestones->getCollection()->transform(function ($milestoneSchema) {
                return [
                    'id' => $milestoneSchema->id,
                    'prefix' => $milestoneSchema->prefix,
                    'schema_name' => $milestoneSchema->schema_name,
                    'schema_description' => $milestoneSchema->schema_description,
                    'status' => $milestoneSchema->status,
                    'milestone_trigger_count' => $milestoneSchema->milestone_trigger_count,
                    'products' => $milestoneSchema->products->unique('product_id')->map(function ($product) {
                        return [
                            'id' => $product->product_id,
                            'name' => $product?->productList?->name,
                        ];
                    })->values()->toArray(),
                ];
            });

            return response()->json([
                'ApiName' => 'milestones',
                'status' => true,
                'data' => $milestones,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'milestones',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function getAuditLogs(Request $request): JsonResponse
    {
        try {
            $query = MilestoneProductAuditLog::select('milestone_product_audiotlogs.*')->with('users:id,first_name,last_name', 'milestoneSchema')
                ->leftJoin('users', 'users.id', 'user_id')
                ->whereIn('type', [\App\Models\MilestoneSchema::class, \App\Models\MilestoneSchemaTrigger::class]);
            if ($request->has('search') && ! empty($request->search)) {
                $searchTerm = $request->input('search');
                $query->where('description', 'LIKE', '%'.$searchTerm.'%');
            }
            if ($request->has('user_id') && ! empty($request->user_id)) {
                $query->whereIn('user_id', (array) $request->input('user_id'));
            }
            if ($request->has('milestone_schema_id') && ! empty($request->milestone_schema_id)) {
                $query->whereIn('reference_id', (array) $request->input('milestone_schema_id'));
            }
            if ($request->has('date') && ! empty($request->date)) {
                $query->whereDate('milestone_product_audiotlogs.updated_at', $request->input('date'));
            }
            if ($request->has('sort_date') && ! empty($request->sort_date)) {
                $query->orderBy('milestone_product_audiotlogs.updated_at', $request->input('sort_date'));
            }
            if ($request->has('sort_user') && ! empty($request->sort_user)) {
                $query->orderBy('users.first_name', $request->input('sort_user'));
            }
            if (empty($request->input('sort_date') ?? null) && empty($request->input('sort_user') ?? null)) {
                $query->orderBy('id', 'DESC');
            }
            $auditLogs = $query->get();

            $logs = [];
            $group = [];
            $description = [];
            foreach ($auditLogs as $auditLog) {
                $monthYear = Carbon::parse($auditLog->updated_at)->format('F-Y');
                if (! empty($auditLog->description)) {
                    $decode = json_decode($auditLog->description, true);
                    if ($decode) {
                        if ($auditLog->event == 'created') {
                            foreach ($decode as $create) {
                                if ($auditLog->type == \App\Models\MilestoneSchema::class) {
                                    if ($create['status'] == '1') {
                                        $description[$monthYear][$auditLog->group][] = 'Milestone <strong>'.$create['schema_name'].'</strong> is activated.';
                                    } elseif ($create['status'] == '0') {
                                        $description[$monthYear][$auditLog->group][] = 'Milestone <strong>'.$create['schema_name'].'</strong> is deactivated.';
                                    }
                                    $description[$monthYear][$auditLog->group][] = 'Milestone <strong>'.$create['schema_name'].'</strong> created.';
                                } elseif ($auditLog->type == \App\Models\MilestoneSchemaTrigger::class) {
                                    $description[$monthYear][$auditLog->group][] = 'Milestone Schema <strong>'.$create['name'].'</strong> was created with trigger date <strong>'.$create['on_trigger'].'</strong>';
                                }
                            }
                        } else {
                            foreach ($decode as $index => $desc) {
                                if ($auditLog->type == \App\Models\MilestoneSchema::class) {
                                    if ($index != 'updated_at') {
                                        $field = preg_replace('/_/', ' ', $index);
                                        $description[$monthYear][$auditLog->group][] = 'Milestone '.$field.' '.MilestoneProductAuditLog::formatChange($desc, $index);
                                    }
                                } elseif ($auditLog->type == \App\Models\MilestoneSchemaTrigger::class) {
                                    if ($index != 'updated_at') {
                                        $field = preg_replace('/_/', ' ', $index);
                                        $description[$monthYear][$auditLog->group][] = 'Milestone Schema '.$field.' '.MilestoneProductAuditLog::formatChange($desc, $index);
                                    }
                                }
                            }
                        }
                    }
                }

                $group[$monthYear][$auditLog->group] = [
                    'month' => $monthYear,
                    'milestone_schema' => $auditLog->milestoneSchema->schema_name ?? null,
                    'user' => $auditLog->users->first_name.' '.$auditLog->users->last_name,
                    'milestone_term' => isset($auditLog->milestoneSchema) ? $auditLog->milestoneSchema->prefix.'-'.$auditLog->milestoneSchema->schema_name : '',
                    'updated_at' => Carbon::parse($auditLog->updated_at)->format('d-m-y | H:i:s'),
                    'description' => @$description[$monthYear][$auditLog->group] ? $description[$monthYear][$auditLog->group] : [],
                ];

                $logs[$monthYear] = $group[$monthYear];
            }

            $i = 0;
            $response = [];
            foreach ($logs as $key => $log) {
                $response[$i]['month'] = $key;
                $k = 0;
                foreach ($log as $data) {
                    sort($data['description']);
                    $desc = implode('<br>', $data['description']);
                    $data['description'] = $desc;
                    $response[$i]['logs'][$k] = $data;
                    $k++;
                }
                $i++;
            }

            return response()->json([
                'ApiName' => 'milestone-audit-logs',
                'status' => true,
                'data' => $response,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'milestone-audit-logs',
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ], 400);
        }
    }

    public function getUpdateByUsers(Request $request): JsonResponse
    {
        try {
            $users = MilestoneProductAuditLog::select('users.id', 'first_name', 'last_name')
                ->leftJoin('users', 'users.id', 'milestone_product_audiotlogs.user_id')
                ->whereIn('type', [\App\Models\MilestoneSchema::class, \App\Models\MilestoneSchemaTrigger::class])
                ->when($request->filled('milestone_schema_id'), function ($q) {
                    $q->where('reference_id', request()->input('milestone_schema_id'));
                })->distinct('users.id')->get();

            return response()->json([
                'ApiName' => 'update-by-dropdown',
                'status' => true,
                'data' => $users,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'update-by-dropdown',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function storeMilestoneSchemas(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $validator = Validator::make($request->all(), [
                'schema_name' => 'required|string|max:255|unique:milestone_schemas,schema_name,'.$request->id,
                'schema_description' => 'nullable|string',
                'status' => 'nullable|in:1,0',
                'milestonetrigger' => 'required|array|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ApiName' => 'add-edit-milestone-schemas',
                    'status' => false,
                    'error' => $validator->errors(),
                ], 400);
            }

            $milestoneTriggers = $request->milestonetrigger;
            if ($request->id) {
                if ($request->id == 1) { // For default schema, we need to check if it can be updated again
                    $milestoneSchema = MilestoneSchema::find($request->id);
                    $positionProductCount = PositionProduct::where('product_id', 1)->count();
                    if ($milestoneSchema->is_updated_once == 1 || $positionProductCount > 0) {
                        return response()->json([
                            'ApiName' => 'add-edit-milestone-schemas',
                            'status' => false,
                            'message' => 'Default schema can not be updated again.',
                        ], 400);
                    }
                }

                $change = false;
                foreach ($milestoneTriggers as $milestoneTrigger) {
                    if (! @$milestoneTrigger['id']) {
                        if (ProductMilestoneHistories::where(['milestone_schema_id' => $request->id])->first() && $request->id != 1) { // $request->id != 1 added for default schema, it can be updated once
                            return response()->json([
                                'ApiName' => 'add-edit-milestone-schemas',
                                'status' => false,
                                'message' => 'Trigger Date can not be added or changed once milestone is attached to the product.',
                            ], 400);
                        } else {
                            $change = true;
                        }
                    }
                }

                $milestone = MilestoneSchema::updateOrCreate(['id' => $request->id], [
                    'schema_description' => $request->schema_description,
                    'schema_name' => $request->schema_name,
                    'is_updated_once' => 1,
                ]);

                if ($change) {
                    MilestoneSchemaTrigger::where('milestone_schema_id', $milestone->id)->delete();
                    foreach ($milestoneTriggers as $milestoneTrigger) {
                        MilestoneSchemaTrigger::create([
                            'name' => $milestoneTrigger['name'],
                            'on_trigger' => @$milestoneTrigger['on_trigger'] ? $milestoneTrigger['on_trigger'] : null,
                            'milestone_schema_id' => $milestone->id,
                        ]);
                    }
                } else {
                    foreach ($milestoneTriggers as $milestoneTrigger) {
                        MilestoneSchemaTrigger::where('id', $milestoneTrigger['id'])->update([
                            'name' => $milestoneTrigger['name'],
                            'on_trigger' => @$milestoneTrigger['on_trigger'] ? $milestoneTrigger['on_trigger'] : null,
                            'milestone_schema_id' => $milestone->id,
                        ]);
                    }
                }
            } else {
                $milestone = MilestoneSchema::create([
                    'schema_name' => $request->schema_name,
                    'schema_description' => $request->schema_description,
                    'status' => $request->status ? $request->status : 1,
                ]);

                foreach ($milestoneTriggers as $milestoneTrigger) {
                    MilestoneSchemaTrigger::create([
                        'name' => $milestoneTrigger['name'],
                        'on_trigger' => @$milestoneTrigger['on_trigger'] ? $milestoneTrigger['on_trigger'] : null,
                        'milestone_schema_id' => $milestone->id,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'ApiName' => 'add-edit-milestone-schemas',
                'status' => true,
                'message' => 'Success!!',
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'add-edit-milestone-schemas',
                'status' => false,
                'error' => $e->getMessage().' '.$e->getLine(),
            ], 400);
        }
    }

    public function deleteMilestoneSchemas($id): JsonResponse
    {
        $milestoneSchema = MilestoneSchemaTrigger::find($id);
        if ($milestoneSchema) {
            if (ProductMilestoneHistories::where(['milestone_schema_id' => $milestoneSchema->milestone_schema_id])->first()) {
                return response()->json([
                    'ApiName' => 'add-edit-milestone-schemas',
                    'status' => false,
                    'error' => 'Trigger Date can not be deleted once milestone is attached to the product.',
                ], 400);
            }
            $milestoneSchema->delete();

            return response()->json([
                'ApiName' => 'delete-milestone-schemas',
                'status' => true,
                'message' => 'Success!!',
            ]);
        }

        return response()->json([
            'ApiName' => 'delete-milestone-schemas',
            'status' => false,
            'message' => 'Milestone schema not found!!',
        ], 400);
    }

    public function activateDeActive($type, $id): JsonResponse
    {
        try {
            if (! $type || ! $id) {
                return response()->json([
                    'ApiName' => 'activateDeActive',
                    'status' => false,
                    'message' => 'Bad Request!!',
                ], 400);
            }

            $milestoneSchema = MilestoneSchema::find($id);
            if (! $milestoneSchema) {
                return response()->json([
                    'ApiName' => 'activateDeActive',
                    'status' => false,
                    'message' => 'Milestone not found!!',
                ], 400);
            }

            $milestoneSchema->update(['status' => $type == 'active' ? 1 : '0']);

            return response()->json([
                'ApiName' => 'activateDeActive',
                'status' => true,
                'message' => 'Successfully '.($type == 'active' ? 'Activated!!' : 'Deactivated!!'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'activateDeActive',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $milestoneData = MilestoneSchema::with('milestone_trigger')->withCount('products')->find($id);

            $superAdminPosition = DB::table('positions')->where('position_name', 'Super Admin')->orWhere('id', '4')->first();
            $positionProductCount = 0;
            if (! empty($superAdminPosition)) {
                $positionProductCount = DB::table('position_products')
                    ->where('product_id', 1)
                    ->whereNot('position_id', $superAdminPosition->id)
                    ->count();
            } else {
                $positionProductCount = PositionProduct::where('product_id', 1)->count();
            }

            $milestoneData->position_count = $positionProductCount; // For default schema, we need to count the position assigned to default product to update the milestone

            return response()->json([
                'ApiName' => 'milestone-show',
                'status' => true,
                'data' => $milestoneData,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'milestone-show',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function milestoneDropdown(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'nullable|in:all,active,deActive',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ApiName' => 'add-edit-milestone-schemas',
                    'status' => false,
                    'message' => $validator->errors(),
                ], 400);
            }

            $milestoneSchemas = MilestoneSchema::select('id', 'prefix', DB::raw("CONCAT(prefix, '-', schema_name) as schema_name"))
                ->withCount('milestone_trigger as payments_count')
                ->when($request->input('status') == 'active', function ($q) {
                    $q->where(['status' => '1']);
                })->when($request->input('status') == 'deActive', function ($q) {
                    $q->where(['status' => '0']);
                })->orderBy('id', 'DESC')->get();

            return response()->json([
                'ApiName' => 'milestone-dropdown',
                'status' => true,
                'data' => $milestoneSchemas,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'milestone-dropdown',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function paymentDropdown($id): JsonResponse
    {
        try {
            $milestoneSchemaTrigger = MilestoneSchemaTrigger::select(
                'id',
                'name',
                'on_trigger',
                DB::raw("CONCAT(name, ' ', '(',on_trigger,')') AS milestone_info")
            )->when($id, function ($q) use ($id) {
                $q->where('milestone_schema_id', $id);
            })->orderBy('id', 'DESC')->get();

            return response()->json([
                'ApiName' => 'payment-dropdown',
                'status' => true,
                'data' => $milestoneSchemaTrigger,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'payment-dropdown',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function milestoneTriggerDate(Request $request): JsonResponse
    {
        $triggerDate = [];
        $schemaTriggerDates = SchemaTriggerDate::get();
        foreach ($schemaTriggerDates as $schemaTriggerDate) {
            $triggerDate[] = ['name' => ucwords(str_replace('_', ' ', $schemaTriggerDate->name)), 'value' => $schemaTriggerDate->name, 'color_code' => $schemaTriggerDate->color_code];
        }
        if ($request->filled('display_canceled') && $request->display_canceled == 0) {
            //
        } else {
            $triggerDate[] = ['name' => 'Cancel Date', 'value' => 'Cancel Date'];
        }

        return response()->json([
            'ApiName' => 'milestone-trigger-date',
            'status' => true,
            'data' => $triggerDate,
        ]);
    }

    public function createTriggerDate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:schema_trigger_dates,name',
            'color_code' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'ApiName' => 'create-trigger-date',
                'error' => $validator->errors(),
            ], 400);
        }

        SchemaTriggerDate::create(['name' => $request->name, 'color_code' => $request->color_code]);

        $guard = $this->getCompanyTypeOrError();
        if ($guard instanceof \Illuminate\Http\JsonResponse) {
            return $guard;
        }
        [, $models] = $guard;

        $fieldModel = $models['field'];
        $trigger = $fieldModel::where('is_custom', 1)->first();
        if ($trigger) {
            $fieldModel::create([
                'name' => $request->name,
                'label' => $request->name,
                'is_mandatory' => 0,
                'is_custom' => 1,
                'section_name' => $trigger->section_name,
                'field_type' => $trigger->field_type,
            ]);
        }

        $schemaTriggerDates = SchemaTriggerDate::get();
        foreach ($schemaTriggerDates as $schemaTriggerDate) {
            $triggerDate[] = ['name' => $schemaTriggerDate->name, 'value' => $schemaTriggerDate->name];
        }

        return response()->json([
            'status' => true,
            'ApiName' => 'create-trigger-date',
            'data' => $triggerDate,
        ]);
    }

    private function getCompanyTypeOrError()
    {
        $company = $this->getCompanyProfile();
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Company profile not found!!'], 400);
        }
        if (! $company->company_type) {
            return response()->json(['success' => false, 'message' => 'Company type not found!!'], 400);
        }
        $models = $this->modelsByType($company->company_type);
        if (! $models) {
            return response()->json(['success' => false, 'message' => 'Invalid company type!!'], 400);
        }

        return [$company->company_type, $models];
    }

    private function getCompanyProfile(): CompanyProfile
    {
        return Cache::remember(
            'company_profile',
            3600,
            fn () => CompanyProfile::first()
        );
    }

    private function modelsByType(string $companyType): ?array
    {
        $map = [
            CompanyProfile::SOLAR_COMPANY_TYPE => [
                'field' => SolarSalesImportField::class,
            ],
            CompanyProfile::TURF_COMPANY_TYPE => [
                'field' => TurfSalesImportField::class,
            ],
            CompanyProfile::MORTGAGE_COMPANY_TYPE => [
                'field' => MortgageSalesImportField::class,
            ],
            'Pest' => [
                'field' => PestSalesImportField::class,
            ],
            'Fiber' => [
                'field' => FiberSalesImportField::class,
            ],
            CompanyProfile::ROOFING_COMPANY_TYPE => [
                'field' => RoofingSalesImportField::class,
            ],
        ];

        return $map[$companyType] ?? null;
    }

    public function uniqueSchemas(): JsonResponse
    {
        $schemas = [];
        $schemaTriggers = MilestoneSchemaTrigger::select('name')->get();
        foreach ($schemaTriggers as $schemaTrigger) {
            $schemas[] = ['name' => $schemaTrigger->name, 'value' => $schemaTrigger->name];
        }

        return response()->json([
            'ApiName' => 'unique-schemas',
            'status' => true,
            'data' => $schemas,
        ]);
    }
}
