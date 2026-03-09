<?php

namespace App\Exports\Sale;

use App\Exports\Sale\DTO\SalesTemplateStructure;
use App\Models\CompanyProfile;
use Illuminate\Support\Facades\DB;

class SalesTemplateStructureResolver
{
    public static function resolve(CompanyProfile $profile, int $templateId): SalesTemplateStructure
    {
        $templateTable = match (true) {
            $profile->company_type === CompanyProfile::FIBER_COMPANY_TYPE => 'fiber_sales_import_templates',
            $profile->company_type === CompanyProfile::SOLAR_COMPANY_TYPE => 'solar_sales_import_templates',
            $profile->company_type === CompanyProfile::TURF_COMPANY_TYPE => 'turf_sales_import_templates',
            $profile->company_type === CompanyProfile::ROOFING_COMPANY_TYPE => 'roofing_sales_import_templates',
            $profile->company_type === CompanyProfile::MORTGAGE_COMPANY_TYPE => 'mortgage_sales_import_templates',
            in_array($profile->company_type, CompanyProfile::PEST_COMPANY_TYPE, true) => 'pest_sales_import_templates',
            default => throw new \RuntimeException("Unsupported company type: {$profile->company_type}")
        };

        $detailsTable = str_replace('_templates', '_template_details', $templateTable);
        $fieldsTable = str_replace('_templates', '_fields', $templateTable);

        $details = DB::table($detailsTable)
            ->where('template_id', $templateId)
            ->orderBy('id')
            ->get();

        if ($details->isEmpty()) {
            abort(response()->json([
                'message' => "Template ID {$templateId} not found for company type: {$profile->company_type}",
            ], 404));
        }

        $fieldIds = $details->pluck('field_id')->toArray();

        $fields = DB::table($fieldsTable)
            ->whereIn('id', $fieldIds)
            ->get()
            ->keyBy('id');

        $aggregatedByHeader = [];
        $outputOrder = [];
        $seenFieldIds = [];

        $normalize = static function (string $h): string {
            $h = trim($h);
            $h = preg_replace('/\s+/u', ' ', $h);

            return mb_strtolower($h);
        };

        foreach ($details as $detail) {
            if ($detail->excel_field === null || trim($detail->excel_field) === '') {
                continue;
            }

            $fieldId = (int) $detail->field_id;

            if (isset($seenFieldIds[$fieldId])) {
                continue;
            }

            $field = $fields[$fieldId] ?? null;
            if (! $field) {
                continue;
            }

            $headerTitle = trim($detail->excel_field);
            $headerKey = $normalize($headerTitle);

            if (! isset($aggregatedByHeader[$headerKey])) {
                $aggregatedByHeader[$headerKey] = [
                    'header' => $headerTitle,
                    'required' => ((int) $field->is_mandatory === 1),
                    'date' => ($field->field_type === 'date'),
                ];
                $outputOrder[] = $headerKey;
            } else {
                $aggregatedByHeader[$headerKey]['required'] =
                    $aggregatedByHeader[$headerKey]['required'] || ((int) $field->is_mandatory === 1);

                $aggregatedByHeader[$headerKey]['date'] =
                    $aggregatedByHeader[$headerKey]['date'] || ($field->field_type === 'date');
            }

            $seenFieldIds[$fieldId] = true;
        }

        $headers = [];
        $required = [];
        $dateCols = [];

        foreach ($outputOrder as $idx => $key) {
            $agg = $aggregatedByHeader[$key];
            $headers[] = $agg['header'];

            if ($agg['required']) {
                $required[] = $idx;
            }
            if ($agg['date']) {
                $dateCols[] = $idx;
            }
        }

        return new SalesTemplateStructure(
            headers: $headers,
            requiredColumnIndexes: $required,
            dateColumnIndexes: $dateCols
        );
    }
}
