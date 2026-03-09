<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BusinessAddress;
use App\Models\CompanyProfile;
use App\Models\Crms;
use App\Models\EmployeeBanking;
use App\Models\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Validator;

class CompanyController extends Controller
{
    // get profile company
    public function getCompanyProfile(): JsonResponse
    {
        $company_profile = CompanyProfile::first();
        $crmData = Crms::where('name', 'Everee')->where('status', 1)->exists();
        $company_profile['everee'] = $crmData ? 1 : 0;
        // $company_profile->company_logo_s3 = null;
        if (isset($company_profile->logo) && $company_profile->logo != null) {
            $S3_BUCKET_PUBLIC_URL = Settings::where('key', 'S3_BUCKET_PUBLIC_URL')->first();
            $s3_bucket_public_url = isset($S3_BUCKET_PUBLIC_URL->value) ? $S3_BUCKET_PUBLIC_URL->value : null;
            if (! empty($s3_bucket_public_url) && $s3_bucket_public_url != null) {
                $image_file_path = $s3_bucket_public_url.config('app.domain_name');
                $company_profile->company_logo_s3 = $image_file_path.'/'.$company_profile->logo;
            }
        } else {
            if ($company_profile) {
                $company_profile->company_logo_s3 = null; // gr
            }
        }

        return response()->json([
            'ApiName' => 'get-company-profiles',
            'status' => true,
            'message' => 'Company Profile Successfully.',
            'data' => $company_profile,
        ], 200);
    }

    public function getBusinessAddress(): JsonResponse
    {

        $BusinessAddress = BusinessAddress::first();

        return response()->json([
            'ApiName' => 'getBusinessAddress',
            'status' => true,
            'message' => 'BusinessAddress Successfully.',
            'data' => $BusinessAddress,
        ], 200);
    }

    public function getCompanyProfileWithoutAuth(): JsonResponse
    {
        $company_profile = CompanyProfile::first();
        if (isset($company_profile->logo) && $company_profile->logo != null) {
            $S3_BUCKET_PUBLIC_URL = Settings::where('key', 'S3_BUCKET_PUBLIC_URL')->first();
            $s3_bucket_public_url = isset($S3_BUCKET_PUBLIC_URL->value) ? $S3_BUCKET_PUBLIC_URL->value : null;
            if (! empty($s3_bucket_public_url) && $s3_bucket_public_url != null) {
                $image_file_path = $s3_bucket_public_url.config('app.domain_name');
                $company_profile->company_logo_s3 = $image_file_path.'/'.$company_profile->logo;
            }
        } else {
            if ($company_profile) {
                $company_profile->company_logo_s3 = null;
            }
        }

        return response()->json([
            'ApiName' => 'get-company-profile-without-auth',
            'status' => true,
            'message' => 'Company Profile Successfully.',
            'data' => $company_profile,
        ], 200);
    }

    public function updateCompanyProfile(Request $request)
    {
        // return $request;
        $Validator = Validator::make($request->all(),
            [
                // 'first_name'  => 'required',
                'phone_number' => 'required',
                'company_type' => 'required',
                // 'country' => 'required',
                'company_email' => 'required',

            ]);
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $profileData = CompanyProfile::where('id', $request['id'])->first();
        if ($profileData != null && $profileData != '') {
            $profileData = $profileData->toArray();
        } else {
            $profileData = [];
        }

        $data = CompanyProfile::find($request['id']);
        if ($data) {
            $stored_bucket = 'public';
            if ($request->file('logo')) {
                $file = $request->file('logo');
                // s3 bucket
                $img_path = time().$file->getClientOriginalName();
                $img_path = str_replace(' ', '_', $img_path);
                $awsPath = config('app.domain_name').'/'.'company-image/'.$img_path;
                s3_upload($awsPath, file_get_contents($file), false, $stored_bucket);
                // s3 bucket end
                // $image_path =  time().$file->getClientOriginalName();
                $ex = $file->getClientOriginalExtension();
                $destinationPath = 'company-image';
                $image_path = $file->move($destinationPath, $img_path);
                $data['logo'] = $image_path;
            }
            if ($request['remove_logo'] == 1) {
                $data['logo'] = '';
            }

            if (! empty($request['name'])) {
                $data->name = $request['name'];
            }
            if (! empty($request['phone_number'])) {
                $data->phone_number = $request['phone_number'];
            }

            //                if(!empty($request['company_type'])){
            //                     $data->company_type = $request['company_type'];
            //                }

            if (! empty($request['country'])) {
                $data->country = $request['country'];
            }

            if (! empty($request['company_email'])) {
                $data->company_email = $request['company_email'];
            }

            if (! empty($request['time_zone'])) {
                $data->time_zone = $request['time_zone'];
            }

            if (! empty($request['business_name'])) {
                $data->business_name = $request['business_name'];
            }

            // if(!empty($request['mailing_address'])){
            $data->mailing_address = $request['mailing_address'];
            // }

            // if(!empty($request['mailing_address_1'])){
            $data->mailing_address_1 = $request['mailing_address_1'];
            // }
            // if(!empty($request['mailing_address_2'])){
            $data->mailing_address_2 = $request['mailing_address_2'];
            // }
            // if(!empty($request['mailing_lat'])){
            $data->mailing_lat = $request['mailing_lat'];
            // }
            // if(!empty($request['mailing_long'])){
            $data->mailing_long = $request['mailing_long'];
            // }

            // if(!empty($request['mailing_state'])){
            $data->mailing_state = $request['mailing_state'];
            // }

            // if(!empty($request['mailing_city'])){
            $data->mailing_city = $request['mailing_city'];
            // }

            // if(!empty($request['mailing_zip'])){
            $data->mailing_zip = $request['mailing_zip'];
            // }

            // if(!empty($request['business_address'])){
            $data->business_address = $request['business_address'];
            // }

            // if(!empty($request['business_state'])){
            $data->business_state = $request['business_state'];
            // }

            // if(!empty($request['business_city'])){
            $data->business_city = $request['business_city'];
            // }

            // if(!empty($request['business_zip'])){
            $data->business_zip = $request['business_zip'];
            // }

            // if(!empty($request['business_address_1'])){
            $data->business_address_1 = $request['business_address_1'];
            // }
            // if(!empty($request['business_address_2'])){
            $data->business_address_2 = $request['business_address_2'];
            // }
            // if(!empty($request['business_lat'])){
            $data->business_lat = $request['business_lat'];
            // }
            // if(!empty($request['business_long'])){
            $data->business_long = $request['business_long'];
            // }
            // if(!empty($request['business_address_time_zone'])){
            $data->business_address_time_zone = $request['business_address_time_zone'];
            // }
            // if(!empty($request['mailing_address_time_zone'])){
            $data->mailing_address_time_zone = $request['mailing_address_time_zone'];
            // }

            if (in_array($data->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $data['business_ein'] = null;
            } else {
                // if (!empty($request['business_ein'])) {
                $data->business_ein = $request['business_ein'];
                // }
            }

            if (! empty($request['business_phone'])) {
                $data->business_phone = $request['business_phone'];
            }

            // if(!empty($request['address'])){
            $data->address = $request['address'];
            // }
            // if(!empty($request['company_website'])){
            $data->company_website = $request['company_website'];
            // }
            $userProfile = $data->save();
        } else {
            $data['name'] = $request['name'];
            $data['phone_number'] = $request['phone_number'];
            $data['company_type'] = $request['company_type'];
            $data['country'] = $request['country'];
            $data['company_email'] = $request['company_email'];
            $data['time_zone'] = $request['time_zone'];
            $data['business_name'] = $request['business_name'];
            $data['mailing_address'] = $request['mailing_address'];
            $data['mailing_state'] = $request['mailing_state'];
            $data['mailing_city'] = $request['mailing_city'];
            $data['mailing_zip'] = $request['mailing_zip'];
            $data['mailing_ein'] = $request['mailing_ein'];

            $data['mailing_address_1'] = isset($request['mailing_address_1']) ? $request['mailing_address_1'] : null;
            $data['mailing_address_2'] = isset($request['mailing_address_2']) ? $request['mailing_address_2'] : null;
            $data['mailing_lat'] = isset($request['mailing_lat']) ? $request['mailing_lat'] : null;
            $data['mailing_long'] = isset($request['mailing_long']) ? $request['mailing_long'] : null;
            $data['mailing_address_time_zone'] = isset($request['mailing_address_time_zone']) ? $request['mailing_address_time_zone'] : null;

            $data['business_address'] = $request['business_address'];
            $data['business_state'] = $request['business_state'];
            $data['business_city'] = $request['business_city'];
            $data['business_zip'] = $request['business_zip'];

            $data['business_address_1'] = isset($request['business_address_1']) ? $request['business_address_1'] : null;
            $data['business_address_2'] = isset($request['business_address_2']) ? $request['business_address_2'] : null;
            $data['business_lat'] = isset($request['business_lat']) ? $request['business_lat'] : null;
            $data['business_long'] = isset($request['business_long']) ? $request['business_long'] : null;
            $data['business_address_time_zone'] = isset($request['business_address_time_zone']) ? $request['business_address_time_zone'] : null;

            if (in_array($request['company_type'], CompanyProfile::PEST_COMPANY_TYPE)) {
                $data['business_ein'] = null;
            } else {
                $data['business_ein'] = $request['business_ein'];
            }
            $data['business_phone'] = $request['business_phone'];
            $data['address'] = $request['address'];
            $data['company_website'] = $request['company_website'];

            $userProfile = CompanyProfile::create($data);
        }

        $company = [];
        foreach ($profileData as $key => $value) {
            if ($value != $data[$key]) {
                $company[$key] = $key.' =>'.$data[$key];
            }
        }
        $desc = implode(',', $company);
        if ($userProfile) {
            $page = 'Setting';
            $action = 'Company Profile Update';
            $description = $desc;
            user_activity_log($page, $action, $description);
        }
        create_paystub_employee();
        StripeBillingController::companyaddupdateinfo();

        return response()->json([
            'ApiName' => 'update-profile',
            'status' => true,
            'message' => 'Profile update Successfully.',
            //  'data' =>$data,
        ], 200);
    }

    public function updateBusinessAaddress(Request $request): JsonResponse
    {
        $Validator = Validator::make($request->all(),
            [
                // 'phone_number' => 'required',
                // 'company_type' => 'required',
                // 'country' => 'required',
                // 'company_email' => 'required',

            ]);
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $data = BusinessAddress::find($request['id']);

        if ($data) {
            $data->name = $request['name'];
            $data->phone_number = $request['phone_number'];
            $data->company_type = $request['company_type'];
            $data->country = $request['country'];
            $data->company_email = $request['company_email'];
            $data->time_zone = $request['time_zone'];
            $data->business_name = $request['business_name'];
            $data->mailing_address = $request['mailing_address'];
            $data->mailing_state = $request['mailing_state'];
            $data->mailing_city = $request['mailing_city'];
            $data->mailing_zip = $request['mailing_zip'];
            $data->mailing_ein = $request['mailing_ein'];
            $data->business_address = $request['business_address'];
            $data->business_state = $request['business_state'];
            $data->business_city = $request['business_city'];
            $data->business_zip = $request['business_zip'];
            $data->business_ein = $request['business_ein'];
            $data->business_phone = $request['business_phone'];
            $data->address = $request['address'];
            $data->company_website = $request['company_website'];
            $data->save();
        } else {
            $data['name'] = $request['name'];
            $data['phone_number'] = $request['phone_number'];
            $data['company_type'] = $request['company_type'];
            $data['country'] = $request['country'];
            $data['company_email'] = $request['company_email'];
            $data['time_zone'] = $request['time_zone'];
            $data['business_name'] = $request['business_name'];
            $data['mailing_address'] = $request['mailing_address'];
            $data['mailing_state'] = $request['mailing_state'];
            $data['mailing_city'] = $request['mailing_city'];
            $data['mailing_zip'] = $request['mailing_zip'];
            $data['mailing_ein'] = $request['mailing_ein'];
            $data['business_address'] = $request['business_address'];
            $data['business_state'] = $request['business_state'];
            $data['business_city'] = $request['business_city'];
            $data['business_zip'] = $request['business_zip'];
            $data['business_ein'] = $request['business_ein'];
            $data['business_phone'] = $request['business_phone'];
            $data['address'] = $request['address'];
            $data['company_website'] = $request['company_website'];

            BusinessAddress::create($data);

        }
        StripeBillingController::companyaddupdateinfo();

        return response()->json([
            'ApiName' => 'update-profile',
            'status' => true,
            'message' => 'BusinessAddress  update Successfully.',
        ], 200);
    }

    public function updateCompanyMargin(Request $request): JsonResponse
    {
        $id = $request->id;
        $CompanyProfile = CompanyProfile::where('id', $id)->first();
        $CompanyProfile->company_margin = $request->company_margin;
        $CompanyProfile->save();

        return response()->json([
            'ApiName' => 'updateCompanyMargin',
            'status' => true,
            'message' => 'Company Margin  update Successfully.',
        ], 200);
    }

    public function bankInfo(Request $request): JsonResponse
    {
        $uid = auth()->user()->id;
        $companyProfile = CompanyProfile::where('id', $request['id'])->first();

        if ($request->type == 'checking') {
            $accountType = 'Salary account';
        } else {
            $accountType = 'Savings account';
        }

        $empBankings = EmployeeBanking::where('employee_id', $request['id'])->first();
        if ($empBankings == '') {
            $empBanking = EmployeeBanking::create([
                'user_id' => $uid,
                'company_id' => $request->companyID,
                'employee_id' => $request['id'],
                'bank_name' => $request->institution_name,
                'account_number' => $request->account_number,
                'routing_number' => $request->routing_number,
                'account_type' => $accountType,
                'is_sandbox' => $request->is_sandbox,
            ]);
        } else {
            $empBanking = EmployeeBanking::where('employee_id', $request['id'])->update([
                'user_id' => $uid,
                'company_id' => $request->companyID,
                'employee_id' => $request['id'],
                'bank_name' => $request->institution_name,
                'account_number' => $request->account_number,
                'routing_number' => $request->routing_number,
                'account_type' => $accountType,
                'is_sandbox' => $request->is_sandbox,
            ]);
        }

        return response()->json([
            'ApiName' => 'bankInfo',
            'status' => true,
            'message' => 'bankInfo update Successfully.',
        ], 200);

    }

    public function employee(Request $request): JsonResponse
    {
        $data = [
            'companyID' => $request->companyID,
            'new_employees' => [
                'default_ot_wage' => $request->new_employees[0]['default_ot_wage'],
                'default_dt_wage' => $request->new_employees[0]['default_dt_wage'],
                'is_943' => $request->new_employees[0]['is_943'],
                'is_scheduleH' => $request->new_employees[0]['is_scheduleH'],
                'email' => $request->new_employees[0]['email'],
                'first_name' => $request->new_employees[0]['first_name'],
                'last_name' => $request->new_employees[0]['last_name'],
                'phone_number' => $request->new_employees[0]['phone_number'],
                'address' => $request->new_employees[0]['address'],
                'address_line2' => $request->new_employees[0]['address_line2'],
                'city' => $request->new_employees[0]['city'],
                'state' => $request->new_employees[0]['state'],
                'zip' => $request->new_employees[0]['zip'],
                'title' => $request->new_employees[0]['title'],
                'default_pay_schedule' => $request->new_employees[0]['default_pay_schedule'],
                'default_wage' => $request->new_employees[0]['default_wage'],
                'workLocationID' => $request->new_employees[0]['workLocationID'],
                'start_date' => $request->new_employees[0]['start_date'],
                'dob' => $request->new_employees[0]['dob'],
                'ssn' => $request->new_employees[0]['ssn'],
            ],
        ];

        return response()->json([
            'ApiName' => 'employee',
            'status' => true,
            'message' => 'employee update Successfully.',
            'data' => $data,
        ], 200);

    }

    public function getCompanyMargin(Request $request): JsonResponse
    {
        $data = CompanyProfile::Select('company_margin')->first();

        return response()->json([
            'ApiName' => 'getCompanyMargin',
            'status' => true,
            'message' => 'Company Margin  get Successfully.',
            'data' => $data,
        ], 200);
    }


    /**
     * Create company profile (cannot be changed once set up).
     * Seeding happens asynchronously via job queue.
     *
     * @param StoreCompanyProfileRequest $request
     * @return JsonResponse
     */
    public function storeCompanyProfile(\App\Http\Requests\StoreCompanyProfileRequest $request): JsonResponse
    {
        try {
            // Create or update company profile in transaction with duplicate check
            $companyProfile = \DB::transaction(function () use ($request) {
                // Check if company profile exists (INSIDE transaction to prevent race condition)
                $existingProfile = CompanyProfile::lockForUpdate()->first();

                // Prevent changes if company profile is already set up and completed
                if ($existingProfile && $existingProfile->setup_status === 'completed') {
                    throw new \Exception('Company profile already exists. Please contact support if you need to update your company information.');
                }

                // Generate unique default email if not provided
                $defaultEmail = $request->input('company_email') ?: 'admin+' . time() . '@yourcompany.com';

                // Prepare data for company profile
                $profileData = [
                    'name' => $request->input('name'),
                    'business_name' => $request->input('name'),
                    'company_type' => $request->input('type'),
                    'company_email' => $defaultEmail,
                    'phone_number' => $request->input('phone_number', '111-222-3333'),
                    'address' => $request->input('address', 'Salt Lake City, Utah'),
                    'time_zone' => $request->input('time_zone', 'America/Denver'),
                    'logo' => 'sequifi-images/defaultCompanyImage.png',
                    'country' => 'US',

                    // Business Address Fields
                    'business_address' => $request->input('address', 'Salt Lake City, Utah'),
                    'business_address_1' => $request->input('business_address_1', '123 Main Street'),
                    'business_city' => $request->input('business_city', 'Salt Lake City'),
                    'business_state' => $request->input('business_state', 'Utah'),
                    'business_zip' => $request->input('business_zip', '84101'),
                    'business_lat' => $request->input('business_lat', '40.758701'),
                    'business_long' => $request->input('business_long', '-111.876183'),
                    'business_address_time_zone' => $request->input('time_zone', 'America/Denver'),
                    'lat' => $request->input('lat', '40.758701'),
                    'lng' => $request->input('lng', '-111.876183'),

                    // Reset status to pending for retry
                    'setup_status' => 'pending',
                    'setup_error' => null,  // Clear previous error
                ];

                // If profile exists (failed/pending/seeding), update it instead of creating new one
                if ($existingProfile) {
                    $existingProfile->update($profileData);
                    return $existingProfile->fresh();
                }

                // Create new profile if none exists
                return CompanyProfile::create($profileData);
            });

        // Safety check: Only dispatch seeding job if not already completed
        if ($companyProfile->setup_status === 'completed') {
            \Log::warning('Company setup already completed, skipping job dispatch', [
                'company_id' => $companyProfile->id,
            ]);
        } elseif (in_array($companyProfile->setup_status, ['seeding', 'pending'])) {
            // Dispatch background job for seeding (ASYNC - No blocking)
            \App\Jobs\SeedCompanyDependentDataJob::dispatch($companyProfile->id);
        } else {
            \Log::warning('Company setup in unexpected status', [
                'company_id' => $companyProfile->id,
                'setup_status' => $companyProfile->setup_status,
            ]);
        }

        return response()->json([
            'ApiName' => 'store-company-profile',
            'status' => true,
            'message' => 'Company profile created successfully. Setup is in progress and will complete shortly.',
            'data' => [
                'company_profile' => $companyProfile,
                'setup_status' => $companyProfile->setup_status,
                'estimated_time' => '15-30 seconds'
            ],
        ], 201);

        } catch (\Exception $e) {
            // Handle duplicate profile attempt
            if (str_contains($e->getMessage(), 'Company profile already exists')) {
                return response()->json([
                    'ApiName' => 'store-company-profile',
                    'status' => false,
                    'message' => $e->getMessage(),
                    'data' => null
                ], 403);
            }

            // Log exception using helper function
            log_exception('Failed to create company profile', $e);

            return response()->json([
                'ApiName' => 'store-company-profile',
                'status' => false,
                'message' => 'Failed to create company profile. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get company setup status (for polling or checking progress)
     *
     * @return JsonResponse
     */
    public function getCompanySetupStatus(): JsonResponse
    {
        $companyProfile = CompanyProfile::first();

        if (!$companyProfile) {
            return response()->json([
                'ApiName' => 'get-company-setup-status',
                'status' => false,
                'message' => 'Company profile not found',
            ], 404);
        }

        return response()->json([
            'ApiName' => 'get-company-setup-status',
            'status' => true,
            'data' => [
                'setup_status' => $companyProfile->setup_status,
                'setup_completed_at' => $companyProfile->setup_completed_at,
                'setup_error' => $companyProfile->setup_error,
                'company_profile' => $companyProfile
            ],
        ], 200);
    }

}
