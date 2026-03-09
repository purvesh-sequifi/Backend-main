<?php

use App\Models\CompanyProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $companyProfile = CompanyProfile::first();

        if (
            $companyProfile &&
            $companyProfile->company_type === CompanyProfile::MORTGAGE_COMPANY_TYPE
        ) {
            DB::table('import_category_details')
                ->where('name', 'LOA_email')
                ->update(['is_mandatory' => 0]);
            DB::table('import_category_details')
                ->where('name', 'MLO_email')
                ->update(['is_mandatory' => 1]);
        }
    }

    public function down(): void
    {
        // Optional rollback logic
        DB::table('import_category_details')
            ->where('name', 'LOA_email')
            ->update(['is_mandatory' => 1]);

        DB::table('import_category_details')
            ->where('name', 'MLO_email')
            ->update(['is_mandatory' => 0]);
    }
};
