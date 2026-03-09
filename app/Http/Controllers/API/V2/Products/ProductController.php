<?php

namespace App\Http\Controllers\API\V2\Products;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\MilestoneProductAuditLog;
use App\Models\MilestoneSchema;
use App\Models\MilestoneSchemaTrigger;
use App\Models\PositionProduct;
use App\Models\ProductCode;
use App\Models\ProductMilestoneHistories;
use App\Models\Products;
use App\Models\UserOrganizationHistory;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        try {
            DB::enableQueryLog();
            $query = Products::with(['productmilestonehistory', 'positionProduct' => function ($q) {
                $q->whereHas('positionDetails');
            }]);
            if ($request->filled('status')) {
                if ($request->input('status') == '0') {
                    $query->onlyTrashed();
                }
            } else {
                $query->withTrashed();
            }
            if ($request->filled('milestone_schema_id')) {
                $milestone_schema_id = $request->input('milestone_schema_id');
                $query->whereHas('productmilestonehistory', function ($query) use ($milestone_schema_id) {
                    $query->where('milestone_schema_id', $milestone_schema_id);
                });
            }

            if ($request->filled('position')) {
                $position = $request->input('position');
                $query->whereHas('positionProduct', function ($query) use ($position) {
                    $query->where('position_id', $position)->whereHas('positionDetails');
                });
            }
            if ($request->has('search') && ! empty($request->search)) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', '%'.$searchTerm.'%')
                        ->orWhere('product_id', 'LIKE', '%'.$searchTerm.'%')
                        ->orWhere('description', 'LIKE', '%'.$searchTerm.'%');
                });
            }
            $perPage = (int) $request->input('per_page', 10);
            $products = $query->orderBy('id', 'DESC')->paginate($perPage);

            $products->transform(function ($product) {
                $milestone = $product->currentProductMilestoneHistoriesList;
                $schema = isset($milestone->milestoneSchema) ? $milestone->milestoneSchema->prefix.'-'.$milestone->milestoneSchema->schema_name : '';
                $schema .= isset($milestone->milestoneSchema) ? '('.$milestone->milestoneSchema->payments_count_count.')' : '';
                $position = $product->positionProduct->count();
                $positionNames = [];
                if ($position > 0) {
                    $positionData = $product->positionProduct->toArray();
                    foreach ($positionData as $positions) {
                        if (isset($positions['position_details'])) {
                            array_push($positionNames, $positions['position_details']['position_name']);
                        }
                    }
                }

                // Use withTrashed() to include soft-deleted product codes when the product is archived
                $productCodes = $product->deleted_at
                                ? $product->productCodes()->withTrashed()->pluck('product_code')->toArray()
                                : $product->productCodes()->pluck('product_code')->toArray();

                // Remove all spaces from each product code
                $productCodes = array_map(fn ($code) => str_replace(' ', '', $code), $productCodes);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    // 'product_id' => $product->product_id,
                    'product_ids' => $productCodes,
                    // 'product_codes' => $productCodes,
                    'description' => $product->description,
                    'effective_date' => $milestone->effective_date ?? '',
                    'product_redline' => $milestone->product_redline ?? '',
                    'status' => $product->deleted_at == null ? 1 : 0,
                    'position' => $position,
                    'milestone' => $schema ?? '',
                    'milestone_id' => $milestone->milestone_schema_id ?? '',
                    'position_names' => $positionNames,
                    'deleted_at' => $product->deleted_at,
                ];
            });

            return response()->json([
                'ApiName' => 'products',
                'status' => true,
                'data' => $products,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'products',
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ], 400);
        }
    }

    public function storeProducts(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:products,name,'.$request->id,
                // 'product_id' => 'required|string|max:255|regex:/^[A-Za-z0-9_-]+$/|unique:products,product_id,' . $request->id,
                'product_ids' => 'required|array',
                'product_ids.0' => 'required|string|max:255|unique:products,product_id,'.$request->id,
                'product_ids.*' => 'required|string|max:255',
                'description' => 'nullable|string',
                'milestone_schema_id' => 'required|exists:milestone_schemas,id',
                'clawback_exempt_on_ms_trigger_id' => 'required',
                'override_on_ms_trigger_id' => 'required',
                // 'product_redline' => 'required',
                // 'effective_date' => 'required|date',
                'status' => 'nullable|in:1,0',
            ]);

            // If $request->product_ids is not set or is null, assign an empty array instead.
            // This ensures $productIds is always an array and avoids undefined/null errors.
            $productIds = $request->product_ids ?? [];

            $currentProductId = $request->id; // Current product being edited (null if new)

            // Return an error response if product IDs are missing or not a valid array
            if (empty($productIds) || ! is_array($productIds)) {
                return response()->json([
                    'ApiName' => 'add-edit-milestone-schemas',
                    'status' => false,
                    'error' => [
                        'product_ids' => ['The Product IDs field is required.'],
                    ],
                ], 400);
            }

            // Proceed only if product IDs are non-empty and in array format to avoid unnecessary duplicate check
            if (! empty($productIds) && is_array($productIds)) {
                // Check for duplicate codes in the input array itself
                $duplicates = array_diff_assoc($productIds, array_unique($productIds));

                if (! empty($duplicates)) {
                    return response()->json([
                        'ApiName' => 'add-edit-milestone-schemas',
                        'status' => false,
                        'error' => [
                            'product_ids' => ['Duplicate product codes found in your submission.'],
                        ],
                    ], 400);
                }
            }

            // Check for codes that already exist in other products
            foreach ($productIds as $index => $code) {
                $query = ProductCode::where('product_code', $code);

                // Exclude current product's codes when updating
                if ($currentProductId) {
                    $query->whereNotIn('product_id', function ($q) use ($currentProductId) {
                        $q->select('id')->from('products')->where('id', $currentProductId);
                    });
                }

                $exists = $query->exists();

                if ($exists) {
                    return response()->json([
                        'ApiName' => 'add-edit-milestone-schemas',
                        'status' => false,
                        'error' => [
                            'product_ids.'.$index => ['The product code "'.$code.'" is already in use by another product.'],
                        ],
                    ], 400);
                }
            }

            if ($validator->fails()) {
                return response()->json([
                    'ApiName' => 'add-edit-milestone-schemas',
                    'status' => false,
                    'error' => $validator->errors(),
                ], 400);
            }

            if ($request->id) {
                $product = Products::withTrashed()->with('positionProduct')->where('id', $request->id)->first();
                if (! $product) {
                    return response()->json([
                        'ApiName' => 'add-edit-milestone-schemas',
                        'status' => false,
                        'error' => 'Product not found!!',
                    ], 400);
                }
                $product->name = $request->name;
                // $product->product_id = $request->product_id;
                $product->product_id = $request->product_ids[0]; // First product_id stored in products table
                $product->description = $request->description;
                $product->save();

                // Delete existing product codes and add new ones
                $product->productCodes()->delete();

                // Add product IDs to product_codes table
                foreach ($request->product_ids as $product_id) {
                    if (! empty($product_id)) {
                        $product->productCodes()->create([
                            'product_code' => strtolower(str_replace(' ', '', $product_id)),
                        ]);
                    }
                }

                if (! count($product->positionProduct)) {
                    ProductMilestoneHistories::where(['product_id' => $product->id])->delete();
                }

                ProductMilestoneHistories::updateOrCreate([
                    'product_id' => $product->id,
                    'milestone_schema_id' => $request->milestone_schema_id,
                    'effective_date' => $request->effective_date,
                ], [
                    'clawback_exempt_on_ms_trigger_id' => $request->clawback_exempt_on_ms_trigger_id ? $request->clawback_exempt_on_ms_trigger_id : 0,
                    'product_redline' => $request->product_redline ?? null,
                    'override_on_ms_trigger_id' => $request->override_on_ms_trigger_id ? $request->override_on_ms_trigger_id : 0,
                ]);
            } else {
                $product = Products::create([
                    'name' => $request->name,
                    // 'product_id' => $request->product_id,
                    'product_id' => $request->product_ids[0], // First product_id stored in products table
                    'description' => $request->description,
                ]);

                // Add product IDs to product_codes table
                foreach ($request->product_ids as $product_id) {
                    if (! empty($product_id)) {
                        $product->productCodes()->create([
                            'product_code' => strtolower(str_replace(' ', '', $product_id)),
                        ]);
                    }
                }

                ProductMilestoneHistories::updateOrCreate([
                    'product_id' => $product->id,
                    'milestone_schema_id' => $request->milestone_schema_id,
                    'effective_date' => $request->effective_date,
                ], [
                    'clawback_exempt_on_ms_trigger_id' => $request->clawback_exempt_on_ms_trigger_id ? $request->clawback_exempt_on_ms_trigger_id : 0,
                    'product_redline' => $request->product_redline ?? null,
                    'override_on_ms_trigger_id' => $request->override_on_ms_trigger_id ? $request->override_on_ms_trigger_id : 0,
                ]);
            }

            DB::commit();

            return response()->json([
                'ApiName' => 'add-edit-products',
                'status' => true,
                'message' => 'Success!!',
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'add-edit-products',
                'status' => false,
                'error' => $e->getMessage().' '.$e->getLine(),
            ], 400);
        }
    }

    public function getAuditLogs(Request $request): JsonResponse
    {
        try {
            $companyProfile = CompanyProfile::first();
            $query = MilestoneProductAuditLog::with('users:id,first_name,last_name', 'products.productMilestoneHistoriesCurrent')->whereIn('type', [\App\Models\Products::class, \App\Models\ProductMilestoneHistories::class]);
            if ($request->has('search') && ! empty($request->search)) {
                $searchTerm = $request->input('search');
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('description', 'LIKE', '%'.$searchTerm.'%');
                });
            }
            if ($request->has('effective_date') && ! empty($request->effective_date)) {
                $query->whereDate('effective_on_date', $request->input('effective_date'));
            }
            if ($request->has('product') && ! empty($request->product[0])) {
                $product = $request->input('product');
                $query->whereHas('products', function ($query) use ($product) {
                    $query->withTrashed()->whereIn('id', (array) $product);
                });
            }
            if ($request->has('sort_effective_date') && ! empty($request->sort_effective_date)) {
                $sort_effective_date = $request->input('sort_effective_date');
                $query->orderBy('effective_on_date', $sort_effective_date);
            }

            if ($request->has('user_id') && ! empty($request->user_id)) {
                $query->whereIn('user_id', (array) $request->input('user_id'));
            }

            if ($request->has('sort_date') && ! empty($request->sort_date)) {
                $query->orderBy('updated_at', $request->input('sort_date'));
            }

            if ($request->has('sort_user') && ! empty($request->sort_user)) {
                $sort_user = $request->input('sort_user');
                $query->whereHas('users', function ($query) use ($sort_user) {
                    $query->orderBy('users.first_name', $sort_user);
                });
            }
            if (empty($request->input('sort_effective_date') ?? null) && empty($request->input('sort_date') ?? null) && empty($request->input('sort_user') ?? null)) {
                $query->orderBy('id', 'DESC');
            }
            $auditLogs = $query->get();

            $finalSchema = [];
            $allSchemas = MilestoneSchema::all();
            foreach ($allSchemas as $allSchema) {
                $finalSchema[$allSchema->id] = $allSchema?->prefix.'-'.$allSchema?->schema_name;
            }

            $finalTrigger = [];
            $allTriggers = MilestoneSchemaTrigger::all();
            foreach ($allTriggers as $allTrigger) {
                $finalTrigger[$allTrigger->id] = $allTrigger?->name;
            }

            $logs = [];
            $group = [];
            $description = [];
            $effectiveDate = [];
            foreach ($auditLogs as $auditLog) {
                $monthYear = Carbon::parse($auditLog->updated_at)->format('F-Y');
                if (! empty($auditLog->description)) {
                    $decode = json_decode($auditLog->description, true);
                    if ($decode) {
                        $prodName = '';
                        $prodDesc = '';
                        $schemaId = '';
                        $exemptId = '';
                        $overrideId = '';
                        $redline = '';
                        if ($auditLog->event == 'created') {
                            foreach ($decode as $create) {
                                if ($auditLog->type == \App\Models\Products::class) {
                                    $description[$monthYear][$auditLog->group][] = 'New product created: <strong>'.$create['name'].'</strong> (ID: <strong>'.$create['product_id'].'</strong>). Description: <strong>'.$create['description'].'</strong>.';
                                    $prodName = $create['name'];
                                    $prodDesc = $create['description'];
                                } elseif ($auditLog->type == \App\Models\ProductMilestoneHistories::class) {
                                    $schema = '';
                                    if ($create['milestone_schema_id']) {
                                        $schema = @$finalSchema[$create['milestone_schema_id']];
                                    }
                                    $exempt = 'None';
                                    if ($create['clawback_exempt_on_ms_trigger_id']) {
                                        $exempt = @$finalTrigger[$create['clawback_exempt_on_ms_trigger_id']];
                                    }
                                    $override = 'None';
                                    if (isset($create['override_on_ms_trigger_id'])) {
                                        $override = @$finalTrigger[$create['override_on_ms_trigger_id']];
                                    }
                                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                        $desc = 'Milestone schema created: <strong>'.$schema.' ('.Carbon::parse($create['effective_date'])->format('m/d/Y').')</strong>. ClawBack Exempt: <strong>'.$exempt.'</strong>. Override Eligibility: <strong>'.$override.'</strong>';
                                    } else {
                                        $desc = 'Milestone schema created: <strong>'.$schema.' ('.Carbon::parse($create['effective_date'])->format('m/d/Y').')</strong>. RedLine: <strong>'.($create['product_redline'] ?? '').'</strong>. ClawBack Exempt: <strong>'.$exempt.'</strong>. Override Eligibility: <strong>'.$override.'</strong>';
                                    }
                                    $description[$monthYear][$auditLog->group][] = $desc;
                                    $schemaId = $create['milestone_schema_id'];
                                    $exemptId = $create['clawback_exempt_on_ms_trigger_id'];
                                    $overrideId = @$create['override_on_ms_trigger_id'];
                                    if (! in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                        $redline = $create['product_redline'] ?? '';
                                    }
                                }
                            }
                        } elseif ($auditLog->event == 'deleted') {
                            $description[$monthYear][$auditLog->group][] = 'Milestone <strong>'.$auditLog->products?->name.'</strong> is archived.';
                        } elseif ($auditLog->event == 'restored') {
                            $description[$monthYear][$auditLog->group][] = 'Milestone <strong>'.$auditLog->products?->name.'</strong> is activated.';
                        } else {
                            foreach ($decode as $index => $desc) {
                                if ($auditLog->type == \App\Models\Products::class) {
                                    if ($index != 'updated_at') {
                                        $field = ucfirst(preg_replace('/_/', ' ', $index));
                                        $description[$monthYear][$auditLog->group][] = 'Milestone schema '.$field.' '.MilestoneProductAuditLog::productFormatChange($desc, $index, $finalSchema, $finalTrigger);
                                        if ($index == 'name') {
                                            $prodName = $desc['new'];
                                        }
                                        if ($index == 'description') {
                                            $prodDesc = $desc['new'];
                                        }
                                    }
                                } elseif ($auditLog->type == \App\Models\ProductMilestoneHistories::class) {
                                    if ($index != 'updated_at') {
                                        $field = preg_replace('/_/', ' ', $index);
                                        if ($index == 'product_id') {
                                            $field = 'Product';
                                        } elseif ($index == 'milestone_schema_id') {
                                            $field = 'Milestone schema';
                                        } elseif ($index == 'clawback_exempt_on_ms_trigger_id') {
                                            $field = 'ClawBack Exempt';
                                        } elseif ($index == 'override_on_ms_trigger_id') {
                                            $field = 'Override Eligibility';
                                        } elseif ($index == 'product_redline') {
                                            $field = 'Product Redline';
                                        }
                                        $description[$monthYear][$auditLog->group][] = 'Milestone schema '.$field.' '.MilestoneProductAuditLog::productFormatChange($desc, $index, $finalSchema, $finalTrigger);
                                        if ($index == 'milestone_schema_id') {
                                            $schemaId = $desc['new'];
                                        }
                                        if ($index == 'clawback_exempt_on_ms_trigger_id') {
                                            $exemptId = $desc['new'];
                                        }
                                        if ($index == 'override_on_ms_trigger_id') {
                                            $overrideId = $desc['new'];
                                        }
                                        if (! in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                            if ($index == 'product_redline') {
                                                $redline = $desc['new'];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                if ($auditLog->effective_on_date) {
                    $effectiveDate[$monthYear][$auditLog->group][] = isset($auditLog->effective_on_date) ? Carbon::parse($auditLog->effective_on_date)->format('d-m-y') : null;
                }

                $group[$monthYear][$auditLog->group] = [
                    'month' => $monthYear,
                    'effective_date' => @$effectiveDate[$monthYear][$auditLog->group] ? $effectiveDate[$monthYear][$auditLog->group] : [],
                    'product_term' => $auditLog->products->product_id ?? null,
                    'description' => @$description[$monthYear][$auditLog->group] ? $description[$monthYear][$auditLog->group] : [],
                    'user' => $auditLog->users->first_name.' '.$auditLog->users->last_name,
                    'type' => $auditLog->type,
                    'current_name' => $auditLog->products?->name,
                    'current_description' => $auditLog->products?->description,
                    'log_name' => $prodName,
                    'log_description' => $prodDesc,
                    'current_milestone_schema_id' => $auditLog->products?->productMilestoneHistoriesCurrent?->milestone_schema_id,
                    'current_clawback_exempt_on_ms_trigger_id' => $auditLog->products?->productMilestoneHistoriesCurrent?->clawback_exempt_on_ms_trigger_id,
                    'current_override_on_ms_trigger_id' => $auditLog->products?->productMilestoneHistoriesCurrent?->override_on_ms_trigger_id,
                    'current_product_redline' => $auditLog->products?->productMilestoneHistoriesCurrent?->product_redline,
                    'log_milestone_schema_id' => $schemaId,
                    'log_clawback_exempt_on_ms_trigger_id' => $exemptId,
                    'log_override_on_ms_trigger_id' => $overrideId,
                    'log_product_redline' => $redline,
                    'updated_at' => Carbon::parse($auditLog->updated_at)->format('d-m-y | H:i:s'),
                    'deleted_at' => $auditLog->products->deleted_at,
                    'event' => $auditLog->event,
                ];

                $logs[$monthYear] = $group[$monthYear];
            }

            $i = 0;
            $flag = 0;
            $response = [];
            foreach ($logs as $key => $log) {
                $response[$i]['month'] = $key;
                $k = 0;
                foreach ($log as $data) {
                    sort($data['description']);
                    $desc = implode('<br>', $data['description']);

                    $effectiveDate = null;
                    if (@$data['effective_date'] && count($data['effective_date']) != 0) {
                        $effectiveDate = $data['effective_date'][0];
                    }

                    $current = 0;
                    if ($data['deleted_at']) {
                        if ($data['event'] == 'deleted' && ! $flag) {
                            $current = 1;
                            $flag = 1;
                        } else {
                            $current = 0;
                        }
                    } else {
                        $current = 0;
                        if ($data['type'] == \App\Models\Products::class) {
                            if (@$data['current_name'] == $data['log_name'] || @$data['current_description'] == $data['log_description']) {
                                $current = 1;
                            }
                            $effectiveDate = null;
                        }
                        if ($data['type'] == \App\Models\ProductMilestoneHistories::class) {
                            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                if (@$data['current_milestone_schema_id'] == $data['log_milestone_schema_id'] && @$data['current_clawback_exempt_on_ms_trigger_id'] == $data['log_clawback_exempt_on_ms_trigger_id'] && @$data['current_override_on_ms_trigger_id'] == $data['log_override_on_ms_trigger_id']) {
                                    if ($effectiveDate < date('Y-m-d')) {
                                        $current = 1;
                                    }
                                }
                            } else {
                                if (@$data['current_milestone_schema_id'] == $data['log_milestone_schema_id'] && @$data['current_clawback_exempt_on_ms_trigger_id'] == $data['log_clawback_exempt_on_ms_trigger_id'] && @$data['current_override_on_ms_trigger_id'] == $data['log_override_on_ms_trigger_id'] && @$data['current_product_redline'] == $data['log_product_redline']) {
                                    if ($effectiveDate < date('Y-m-d')) {
                                        $current = 1;
                                    }
                                }
                            }
                        }
                    }

                    $newArray = [];
                    $newArray['month'] = $data['month'];
                    $newArray['effective_date'] = $effectiveDate;
                    $newArray['product_term'] = $data['month'];
                    $newArray['description'] = $desc;
                    $newArray['user'] = $data['user'];
                    $newArray['updated_at'] = $data['updated_at'];
                    $newArray['current_status'] = $current;
                    $response[$i]['logs'][$k] = $newArray;
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

    public function show($id): JsonResponse
    {
        try {
            $product = Products::with('currentProductMilestoneHistories')->find($id);
            if (! $product) {
                return response()->json([
                    'ApiName' => 'product-show',
                    'status' => false,
                    'message' => 'Product not found!!',
                ], 400);
            }
            $productCodes = $product->productCodes()->pluck('product_code')->toArray();
            $productCodes = array_map(fn ($code) => str_replace(' ', '', $code), $productCodes);

            $milestone = $product->currentProductMilestoneHistories;
            $data = [];
            $data['id'] = $product->id;
            $data['name'] = $product->name;
            $data['product_ids'] = $productCodes;
            $data['description'] = $product->description;
            $data['status'] = $product->status;
            $data['product_redline'] = isset($milestone->id) ? $milestone->product_redline : $milestone->product_redline;
            $data['milestone_schema_id'] = isset($milestone->id) ? $milestone->milestone_schema_id : $milestone->milestone_schema_id;
            $data['clawback_exempt_on_ms_trigger_id'] = isset($milestone->id) ? $milestone->clawback_exempt_on_ms_trigger_id : $milestone->clawback_exempt_on_ms_trigger_id;
            $data['override_on_ms_trigger_id'] = isset($milestone->id) ? $milestone->override_on_ms_trigger_id : $milestone->override_on_ms_trigger_id;
            $data['effective_date'] = isset($milestone->id) ? $milestone->effective_date : $milestone->effective_date;

            return response()->json([
                'ApiName' => 'product-show',
                'status' => true,
                'message' => 'Success!!',
                'data' => $data,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'product-show',
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
            ], 400);
        }
    }

    public function activateDeActive($type, $id): JsonResponse
    {
        if (! $type || ! $id) {
            return response()->json([
                'ApiName' => 'activateDeActive',
                'status' => false,
                'message' => 'Bad Request!!',
            ], 400);
        }

        $product = Products::withTrashed()->find($id);
        if (! $product) {
            return response()->json([
                'ApiName' => 'activateDeActive',
                'status' => false,
                'message' => 'No records found.',
                'data' => null,
            ], 400);
        }

        if ($product->trashed()) {
            $product->restore();
            // Also restore all related product codes
            ProductCode::withTrashed()
                ->where('product_id', $product->id)
                ->restore();
            $message = 'Product undeleted successfully.';
        } else {
            $product->delete();
            ProductCode::where('product_id', $product->id)
                ->delete();
            $message = 'Product deleted successfully.';
        }

        return response()->json([
            'ApiName' => 'activateDeActive',
            'status' => true,
            'message' => $message,
            'data' => $product,
        ]);
    }

    public function productDropdown(Request $request): JsonResponse
    {
        try {
            $filter = $request->input('filter', '');
            $rep_id = $request->input('rep_id');
            $productIds = [];
            if (isset($rep_id) && ! empty($rep_id)) {
                $productIds = UserOrganizationHistory::whereIn('user_id', $rep_id)->distinct()->pluck('product_id');
            }
            $products = Products::select('id', 'name', 'product_id')->where('status', '1')
                ->where(function ($q) use ($productIds) {
                    if (! empty($productIds)) {
                        $q->whereIn('id', $productIds);
                    }
                })
                ->where(function ($q) use ($filter) {
                    if (! empty($filter)) {
                        $q->where('name', 'LIKE', '%'.$filter.'%')
                            ->orWhere('product_id', 'LIKE', '%'.$filter.'%');
                    }
                });

            $products = $products->orderBy('id', 'DESC')->get();

            return response()->json([
                'ApiName' => 'product-dropdown',
                'status' => true,
                'data' => $products,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'product-dropdown',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function productByPosition($id): JsonResponse
    {
        try {
            $status = true;
            $message = null;
            $positionId = $id;
            $products = PositionProduct::with('product')->where('position_id', $positionId)->get();
            $data = [];
            foreach ($products as $positionProduct) {
                $product = $positionProduct->product; // Access the related product
                $productCodes = $product->productCodes()->pluck('product_code')->toArray();
                $productCodes = array_map(fn ($code) => str_replace(' ', '', $code), $productCodes);
                if ($product) {
                    $row = [];
                    $row['id'] = $product->id;  // Assuming 'id' is from the Product model
                    $row['name'] = $product->name;  // Accessing 'name' from the Product model
                    $row['product_id'] = $product->product_id; // Assuming 'product_id' exists on Product
                    $row['product_ids'] = $productCodes;
                    $row['description'] = $product->description;  // Accessing 'description' from Product
                    $row['schema'] = $product->productToMilestoneTrigger;  // Assuming this relationship exists
                    $data[] = $row;
                }
            }

            return response()->json([
                'ApiName' => 'product-by-position',
                'status' => $status,
                'message' => $message,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'product-by-position',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function productByMilestone($id)
    {
        try {
            $status = true;
            $message = null;
            $products = Products::with('milestoneSchemaByTriggers')->where(['id' => $id, 'status' => '1'])->first();
            if (! $products) {
                return response()->json([
                    'ApiName' => 'product-by-milestone-triggers',
                    'status' => false,
                    'message' => 'not exist product',
                ]);
            }
            // Get the related triggers
            $triggers = $products->MilestoneSchemaByTriggers;

            // Check if triggers exist
            if (is_null($triggers) || $triggers->isEmpty()) {
                $message = 'No triggers found for the product';
                $status = false;
                $triggers = [];
            } else {
                // Transform the triggers collection
                $triggers->transform(function ($trigger) {
                    return [
                        'id' => $trigger->id,
                        'name' => $trigger->name,
                        'on_trigger' => $trigger->on_trigger,
                        'name_on_trigger' => $trigger->name.'('.$trigger->on_trigger.')',
                        'milestone_schema_id' => $trigger->milestone_schema_id,
                    ];
                });
            }

            // Return the success response
            return response()->json([
                'ApiName' => 'product-by-milestone-triggers',
                'status' => $status,
                'message' => $message,
                'data' => $triggers,
            ]);
        } catch (Exception $e) {
            // Catch and return error response
            return response()->json([
                'ApiName' => 'product-by-milestone-triggers',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function getUpdateByUsers(Request $request): JsonResponse
    {
        try {
            $users = MilestoneProductAuditLog::select('users.id', 'first_name', 'last_name')
                ->leftJoin('users', 'users.id', 'milestone_product_audiotlogs.user_id')
                ->whereIn('milestone_product_audiotlogs.type', [\App\Models\Products::class, \App\Models\ProductMilestoneHistories::class])
                ->when($request->filled('product_id'), function ($q) {
                    $q->where('reference_id', request()->input('product_id'));
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

    public function productDetails(Request $request): JsonResponse
    {
        $products = Products::with('currentProductMilestoneHistories.milestone.milestone_trigger')->whereIn('id', $request->product_ids)->get();
        if (count($products) == 0) {
            return response()->json(['status' => false, 'message' => 'Product not found!!'], 400);
        }

        $response = [];
        foreach ($products as $product) {
            $triggers = $product->currentProductMilestoneHistories->milestoneSchema->milestone_trigger->slice(0, $product->currentProductMilestoneHistories->milestoneSchema->milestone_trigger->count() - 1);
            $productCodes = $product->productCodes()->pluck('product_code')->toArray();
            $productCodes = array_map(fn ($code) => str_replace(' ', '', $code), $productCodes);
            $response[] = [
                'id' => $product->id,
                'name' => $product->name,
                'product_id' => $product->product_id,
                'product_ids' => $productCodes,
                'description' => $product->description,
                'milestone_schema' => [
                    'id' => $product->currentProductMilestoneHistories->id,
                    'prefix' => $product->currentProductMilestoneHistories->milestoneSchema->prefix,
                    'schema_name' => $product->currentProductMilestoneHistories->milestoneSchema->schema_name,
                    'schema_description' => $product->currentProductMilestoneHistories->milestoneSchema->schema_description,
                    'status' => $product->currentProductMilestoneHistories->milestoneSchema->status,
                    'milestone_trigger' => $triggers,
                ],
            ];
        }

        return response()->json(['status' => true, 'message' => 'Successfully.', 'data' => $response]);
    }
}
