<?php

namespace App\Http\Controllers\API\V2\SalesImportTemplate;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\FiberSalesImportField;
use App\Models\FiberSalesImportTemplate;
use App\Models\FiberSalesImportTemplateDetail;
use App\Models\MortgageSalesImportField;
use App\Models\MortgageSalesImportTemplate;
use App\Models\MortgageSalesImportTemplateDetail;
use App\Models\PestSalesImportField;
use App\Models\PestSalesImportTemplate;
use App\Models\PestSalesImportTemplateDetail;
use App\Models\RoofingSalesImportField;
use App\Models\RoofingSalesImportTemplate;
use App\Models\RoofingSalesImportTemplateDetail;
use App\Models\SolarSalesImportField;
use App\Models\SolarSalesImportTemplate;
use App\Models\SolarSalesImportTemplateDetail;
use App\Models\TurfSalesImportField;
use App\Models\TurfSalesImportTemplate;
use App\Models\TurfSalesImportTemplateDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class SalesImportTemplateController extends Controller
{
    private function getCompanyProfile(): CompanyProfile
    {
        return Cache::remember('company_profile', 3600,
            fn () => CompanyProfile::first()
        );
    }

    private function modelsByType(string $companyType): ?array
    {
        $map = [
            CompanyProfile::SOLAR_COMPANY_TYPE => [
                'field' => SolarSalesImportField::class,
                'template' => SolarSalesImportTemplate::class,
                'detail' => SolarSalesImportTemplateDetail::class,
            ],
            CompanyProfile::TURF_COMPANY_TYPE => [
                'field' => TurfSalesImportField::class,
                'template' => TurfSalesImportTemplate::class,
                'detail' => TurfSalesImportTemplateDetail::class,
            ],
            CompanyProfile::MORTGAGE_COMPANY_TYPE => [
                'field' => MortgageSalesImportField::class,
                'template' => MortgageSalesImportTemplate::class,
                'detail' => MortgageSalesImportTemplateDetail::class,
            ],
            'Pest' => [
                'field' => PestSalesImportField::class,
                'template' => PestSalesImportTemplate::class,
                'detail' => PestSalesImportTemplateDetail::class,
            ],
            'Fiber' => [
                'field' => FiberSalesImportField::class,
                'template' => FiberSalesImportTemplate::class,
                'detail' => FiberSalesImportTemplateDetail::class,
            ],
            CompanyProfile::ROOFING_COMPANY_TYPE => [
                'field' => RoofingSalesImportField::class,
                'template' => RoofingSalesImportTemplate::class,
                'detail' => RoofingSalesImportTemplateDetail::class,
            ],
        ];

        return $map[$companyType] ?? null;
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

    public function templateList()
    {
        $guard = $this->getCompanyTypeOrError();
        if ($guard instanceof \Illuminate\Http\JsonResponse) {
            return $guard;
        }
        [, $models] = $guard;

        /** @var class-string $fieldModel */
        /** @var class-string $templateModel */
        $fieldModel = $models['field'];
        $templateModel = $models['template'];

        $importFields = $fieldModel::get();
        $importTemplates = $templateModel::with('templateDetails.field')->get();

        $templates = [];
        foreach ($importTemplates as $template) {
            $details = [];
            foreach ($template->templateDetails as $templateDetail) {
                // Skip template details where the field no longer exists
                // (e.g., custom field was archived, became calculated, or is now used in positions)
                if (!$templateDetail->field) {
                    continue;
                }
                
                $details[] = [
                    'id' => $templateDetail->id,
                    'template_id' => $templateDetail->template_id,
                    'field_id' => $templateDetail->field_id,
                    'excel_field' => $templateDetail->excel_field,
                    'name' => $templateDetail->field->label,
                ];
            }
            $templates[] = [
                'id' => $template->id,
                'name' => $template->name,
                'template_details' => $details,
            ];
        }

        $response = [
            'category_details' => $importFields,
            'templates' => $templates,
        ];

        return response()->json(['success' => true, 'message' => 'Template List!!', 'data' => $response]);
    }

    public function createOrUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|min:2',
            'data' => 'required|array',
            'data.*.id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()], 400);
        }

        $guard = $this->getCompanyTypeOrError();
        if ($guard instanceof \Illuminate\Http\JsonResponse) {
            return $guard;
        }
        [, $models] = $guard;

        /** @var class-string $templateModel */
        /** @var class-string $detailModel */
        $templateModel = $models['template'];
        $detailModel = $models['detail'];

        if ($request->id) {
            $template = $templateModel::where('id', $request->id)->first();
            if (! $template) {
                return response()->json(['success' => false, 'error' => 'Template does not exists!!'], 400);
            }
            $template->name = $request->name;
            $template->save();

            $detailModel::where('template_id', $template->id)->delete();
        } else {
            $template = $templateModel::create(['name' => $request->name]);
        }

        $detailRows = [];
        foreach ($request->data as $item) {
            $detailRows[] = [
                'template_id' => $template->id,
                'field_id' => $item['id'],
                'excel_field' => $item['excel_field'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if (! empty($detailRows)) {
            $detailModel::insert($detailRows);
        }

        return response()->json(['success' => true, 'message' => 'Template saved successfully!!']);
    }

    public function templateDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()], 400);
        }

        $guard = $this->getCompanyTypeOrError();
        if ($guard instanceof \Illuminate\Http\JsonResponse) {
            return $guard;
        }
        [, $models] = $guard;

        /** @var class-string $templateModel */
        /** @var class-string $detailModel */
        $templateModel = $models['template'];
        $detailModel = $models['detail'];

        $templateModel::where('id', $request->id)->delete();
        $detailModel::where('template_id', $request->id)->delete();

        return response()->json(['success' => true, 'message' => 'Template deleted successfully!!']);
    }
}
