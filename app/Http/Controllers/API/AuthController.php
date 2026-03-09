<?php

namespace App\Http\Controllers\API;

/**
 * @OA\Info(
 *     title="Sequifi API Documentation",
 *     version="1.0.0",
 *     description="Sequifi API Documentation",
 *
 *     @OA\Contact(
 *         email="gary@sequifi.in",
 *         name="Sequifi Support"
 *     )
 * )
 *
 * @OA\Server(
 *     url="/api",
 *     description="API Server"
 * )
 */

use App\Core\Traits\FieldRoutesTrait;
use App\Core\Traits\HubspotTrait;
use App\Exports\ClawbackDataExport;
use App\Exports\EmployeesExport;
use App\Exports\PendingInstallExport;
use App\Exports\UserExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginValidatedRequest;
use App\Jobs\HubSpotDataSyncJob;
use App\Jobs\Sales\SaleMasterJob;
use App\Jobs\Sales\SaleMasterJobAwsLambda;
use App\Mail\forgotPassword;
use App\Models\AdditionalLocations;
use App\Models\BatchProcessTracker;
use App\Models\CompanyProfile;
use App\Models\Crms;
// use App\Models\UserPermissions;
// use App\Models\PermissionModules;
// use App\Models\PermissionTabs;
// use App\Models\PermissionSubModules;
use App\Models\CrmSetting;
// use App\Models\AdditionalLocations;
use App\Models\Device;
use App\Models\DomainSetting;
use App\Models\GroupPermissions;
use App\Models\GroupPolicies;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\leadComment;
use App\Models\LegacyApiRawDataHistory;
use App\Models\Locations;
use App\Models\OnboardingEmployees;
use App\Models\Permissions;
use App\Models\PoliciesTabs;
use App\Models\Positions;
use App\Models\ProfileAccessPermission;
use App\Models\Roles;
use App\Models\SalesMaster;
use App\Models\SequiDocsEmailSettings;
use App\Models\SequiDocsTemplate;
use App\Models\TestData;
use App\Models\User;
use App\Models\UserIsManagerHistory;
// SaleMasterProcess import removed - using BatchProcessTracker instead
use App\Models\UserOrganizationHistory;
use App\Models\UserProfileHistory;
use App\Traits\EmailNotificationTrait;
use App\Traits\forgotPasswordTrait;
use Auth;
use DB;
use Excel;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class AuthController extends Controller
{
    use EmailNotificationTrait;
    use FieldRoutesTrait;
    use forgotPasswordTrait;
    use HubspotTrait;

    protected $url;

    public function __construct(UrlGenerator $url)
    {
        $this->url = $url;
    }

    /**
     * @OA\Post(
     *     path="/login",
     *     summary="User login",
     *     description="Authenticate a user and receive a token",
     *     operationId="userLogin",
     *     tags={"Authentication"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="User credentials",
     *
     *         @OA\JsonContent(
     *             required={"email","password"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Login Successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="token", type="string", example="1|abcdefghijklmnopqrstuvwxyz1234567890"),
     *                 @OA\Property(property="user", type="object")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid credentials")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object"
     *             )
     *         )
     *     )
     * )
     */
    public function login(LoginValidatedRequest $request)
    {

        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'ApiName' => 'Login',
                'status' => false,
                'message' => 'Email & Password does not match with our record.',
            ], 401);
        }

        $user = User::with('state', 'city', 'office')->where('email', $request['email'])->firstOrFail();
        if ($user->disable_login == 1) {
            return response()->json([
                'ApiName' => 'Login',
                'status' => false,
                'message' => 'Your login has been Suspended',
            ], 401);
        }

        $device = Device::where('user_id', $user->id)
            ->where('device_identifier', $request->device_token)
            ->whereNotNull('verify_at')
            ->first();
        if (! $device && $user->id != 1 && 1 == 2) {
            if (! $request->two_fa_code) {
                $code = rand(100000, 999999);
                $expires_at = now()->addMinutes(2);

                $existingCode = Device::where('user_id', $user->id)
                    ->where('device_identifier', $request->device_token)
                    ->first();
                if ($existingCode) {
                    if ($existingCode->expires_at > now()) {
                        $code = $existingCode->code;
                        $expires_at = $existingCode->expires_at;
                    }
                }
                Device::updateOrCreate([
                    'user_id' => $user->id,
                    'device_identifier' => $request->device_token,
                ], [
                    'user_id' => $user->id,
                    'code' => $code,
                    'device_identifier' => $request->device_token,
                    'expires_at' => $expires_at,
                ]);

                // Generate 2FA code and send it
                $twoFactorAffectingMail['email'] = $user->email;
                $twoFactorAffectingMail['subject'] = 'Your Two-Factor Authentication Code';
                $twoFactorAffectingMail['template'] = view('mail.two_factor_code', compact('code', 'user'));

                // Send code via email or SMS
                // dd($twoFactorAffectingMail);
                $this->sendEmailNotification($twoFactorAffectingMail);

                Auth::logout();

                return response()->json([
                    'ApiName' => 'Login',
                    'status' => false,
                    '2FA' => true,
                    'message' => 'New device detected. Please verify with 2FA.',
                    'expires_at' => $expires_at,
                ], 403);
            } else {
                // Verify the 2FA code
                $inputCode = $request->two_fa_code;
                $twoFactorCode = Device::where('user_id', $user->id)
                    ->where('code', $inputCode)
                    ->where('device_identifier', $request->device_token)
                    ->where('expires_at', '>', now())
                    ->first();
                if ($twoFactorCode) {
                    $twoFactorCode->update(['verify_at' => now()]);
                } else {
                    return response()->json([
                        'ApiName' => 'Login',
                        'status' => false,
                        'message' => 'The provided code is incorrect or expired.',
                    ], 400);
                }
            }
        }

        // if ($user->dismiss == 1 && $user->status_id == 7) {
        //     return response()->json([
        //         'ApiName' => 'Login',
        //         'status' => false,
        //         // 'message' => 'This account is dismiss',
        //         'message' => 'This account is terminated',
        //     ], 400);
        // }
        // if ($user->status_id == 2 && $user->dismiss == 0) {
        //     return response()->json([
        //         'ApiName' => 'Login',
        //         'status' => false,
        //         'message' => 'This account is Inactive',
        //     ], 400);
        // }
        if ($user->status_id == 4) {
            return response()->json([
                'ApiName' => 'Login',
                'status' => false,
                'message' => 'This account is delete',
            ], 400);
        }
        // if ($user->status_id == 6) {
        //     return response()->json([
        //         'ApiName' => 'Login',
        //         'status' => false,
        //         'message' => 'This account is disable login',
        //     ], 400);
        // }
        // if ($user->disable_login == 1) {
        //     return response()->json([
        //         'ApiName' => 'Login',
        //         'status' => false,
        //         'message' => 'This account is disable login',
        //     ], 400);
        // }
        if (isset($request['worker_type']) && $request['worker_type'] != '') {
            if ($user->worker_type === 'w2' || $user->worker_type === 'W2' || $user->worker_type === '1099') {
                // Process for Manager or Employee
            } else {
                return response()->json([
                    'ApiName' => 'Login',
                    'status' => false,
                    'message' => 'This account does not have worker privileges.',
                ], 400);
            }

        }
        if ($user->manager_id != null) {
            $manageName = User::where('id', $user->manager_id)->first();
            if ($manageName) {
                $user['manager_name'] = $manageName->first_name.' '.$manageName->last_name;
            } else {
                $user['manager_name'] = null;
            }

        } else {
            $user['manager_name'] = null;
        }
        if (isset($user->image) && $user->image != null) {
            $user['user_image_s3'] = s3_getTempUrl(config('app.domain_name').'/'.$user->image);
        } else {
            $user['user_image_s3'] = null;
        }
        $user['city'] = isset($user->city->name) ? $user->city->name : null;
        $user['state_name'] = isset($user->state->name) ? $user->state->name : null;
        $user['state_code'] = isset($user->state->state_code) ? $user->state->state_code : null;
        $user['office_id'] = isset($user->office->id) ? $user->office->id : null;
        $user['office_name'] = isset($user->office->office_name) ? $user->office->office_name : null;
        $user['additional_location'] = AdditionalLocations::with('state', 'city', 'office')->where('user_id', $user->id)->get();
        if ($user['additional_location']) {
            $user['additional_location']->transform(function ($data) {
                return [
                    // dd($data->Override),
                    'id' => $data->id,
                    'office_id' => isset($data->office->id) ? $data->office->id : null,
                    'office_name' => isset($data->office->office_name) ? $data->office->office_name : null,
                    'state_id' => isset($data->state_id) ? $data->state_id : null,
                    'state_name' => isset($data->state->name) ? $data->state->name : null,
                    'state_code' => isset($data->state->state_code) ? $data->state->state_code : null,
                    'city_id' => isset($data->city->id) ? $data->city->id : null,
                    'city_name' => isset($data->city->name) ? $data->city->name : null,
                    // 'override' => isset($data->override[0]->overridessattlement->sattlement_type) ? $data->override[0]->overridessattlement->sattlement_type : 'NA',
                    // 'status' => $data->setup_status,
                ];
            });
        } else {
            $user['additional_location'];
        }
        // dd($user['additional_location']);
        $roleId = auth('api')->user()->position_id;
        $groupId = $user->group_id;
        /*$permissionData = UserPermissions::distinct()->select('position_id','module_id')->with('permissionModule')->where('position_id', $roleId)->get();
        $moduleData=[];
        foreach($permissionData as $key => $modules)
        {
        $moduleData[] = PermissionModules::select('id','module')->with('subModule')->where('id', $modules->module_id)->first();
        }
        $user['access_rights'] =isset($moduleData)?$moduleData:null;*/

        if ($groupId == null) {
            $permissionData = [];
        } else {
            $permissionData = $this->get_permission($groupId);
        }
        // $user['access_rights'] = isset($permissionData) ? $permissionData : null;
        $user['access_rights'] = isset($permissionData['access_rights']) ? $permissionData['access_rights'] : [];
        $user['profile_access_config'] = isset($permissionData['profile_access_config']) ? $permissionData['profile_access_config'] : [];
        // $personal_access = '';
        if ($request['device_token']) {

            User::where('id', $user->id)->update(['device_token' => $request['device_token']]);
        }
        $userOrganizationHistory = UserOrganizationHistory::where('user_id', $user['id'])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->first();
        if ($userOrganizationHistory) {
            $subPosition = $userOrganizationHistory->sub_position_id;
        } else {
            $subPosition = $user->sub_position_id;
        }
        $position = Positions::with('position_wage')->where('id', $subPosition)->first();
        if ($position) {
            $user['wages_status'] = isset($position->position_wage->wages_status) ? $position->position_wage->wages_status : 0;
        } else {
            $user['wages_status'] = 0;
        }
        $personal_access = DB::table('personal_access_tokens')->where(['name' => 'API Token', 'tokenable_id' => $user->id])->orderBy('id', 'desc')->first();

        return response()->json([
            'ApiName' => 'Login',
            'status' => true,
            'last_login' => isset($personal_access->created_at) ? $personal_access->created_at : null,
            'message' => 'User Logged In Successfully.',
            'token' => $user->createToken('API Token')->plainTextToken,
            'data' => $user,
            // 'APP_ID' => config('services.onesignal.app_id'),
            // 'user_image_s3' => $s3_user_url,

        ], 200);
    }

    public function isManagerCheckr($userId)
    {
        $managers = UserIsManagerHistory::where(['user_id' => $userId])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date')->get();

        $man = '';
        foreach ($managers as $manager) {
            if (! $man) {
                $man = $manager;
            } else {
                if ($manager->is_manager != $man->is_manager) {
                    $man = $manager;
                }
            }
        }

        return $man;
    }

    public function get_userdata()
    {
        try {
            // Check if user is authenticated
            if (!Auth()->check()) {
                return response()->json([
                    'ApiName' => 'get_user_data',
                    'status' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $id = Auth()->user()->id;

        // Cache the basic user data that doesn't change frequently
        $cacheKey = "user:basic_data:{$id}";
        $ttl = now()->addMinutes(15); // 15 minutes TTL

        $user = Cache::remember($cacheKey, $ttl, function () use ($id) {
            return User::with('state', 'office')->where('id', $id)->firstOrFail();
        });
        } catch (\Exception $e) {
            \Log::error('get_userdata error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'ApiName' => 'get_user_data',
                'status' => false,
                'message' => 'Failed to fetch user data: ' . $e->getMessage(),
            ], 500);
        }

        // Always get fresh data for dynamic/time-sensitive information
        /* Check manager id and effective date */
        $isManager = $this->isManagerCheckr($user->id);
        $user->is_manager = isset($isManager->is_manager) ? $isManager->is_manager : null;
        $user->is_manager_effective_date = isset($isManager->effective_date) ? $isManager->effective_date : null;

        // Handle employee image URL (always fresh due to temporary URLs)
        if (isset($user->image) && $user->image != null) {
            $user['employee_image_s3'] = s3_getTempUrl(config('app.domain_name').'/'.$user->image);
        } else {
            $user['employee_image_s3'] = null;
        }

        $office = Locations::find(@$user->office_id);
        $user['office_name'] = isset($office->office_name) ? $office->office_name : null;
        $user['state_code'] = isset($user->state->state_code) ? $user->state->state_code : null;

        // Get additional locations (always fresh)
        $additionalLocations = AdditionalLocations::with('office', 'state', 'city')
            ->where('user_id', $user->id)
            ->groupBy('office_id')
            ->get();

        if ($additionalLocations->count() > 0) {
            $user['additional_location'] = $additionalLocations->transform(function ($data) {
                return [
                    'id' => $data->id,
                    'state_id' => isset($data->state_id) ? $data->state_id : null,
                    'state_name' => isset($data->state->name) ? $data->state->name : null,
                    'state_code' => isset($data->state->state_code) ? $data->state->state_code : null,
                    'city_id' => isset($data->city->id) ? $data->city->id : null,
                    'city_name' => isset($data->city->name) ? $data->city->name : null,
                    'office_id' => isset($data->office->id) ? $data->office->id : null,
                    'office_name' => isset($data->office->office_name) ? $data->office->office_name : null,
                ];
            });
        } else {
            $user['additional_location'] = $additionalLocations;
        }

        // Get organization history and position data (always fresh)
        $userOrganizationHistory = UserOrganizationHistory::where('user_id', $user['id'])
            ->where('effective_date', '<=', date('Y-m-d'))
            ->orderBy('effective_date', 'DESC')
            ->first();

        if ($userOrganizationHistory) {
            $subPosition = $userOrganizationHistory->sub_position_id;
        } else {
            $subPosition = $user->sub_position_id;
        }

        $position = Positions::with('position_wage')->where('id', $subPosition)->first();
        if ($position) {
            $user['wages_status'] = isset($position->position_wage->wages_status) ? $position->position_wage->wages_status : 0;
        } else {
            $user['wages_status'] = 0;
        }

        // Get permissions (always fresh)
        $groupId = $user->group_id;
        if ($groupId == null) {
            $permissionData = [];
        } else {
            $permissionData = $this->get_permission($groupId);
        }
        $user['access_rights'] = isset($permissionData['access_rights']) ? $permissionData['access_rights'] : [];
        $user['profile_access_config'] = isset($permissionData['profile_access_config']) ? $permissionData['profile_access_config'] : [];

        return response()->json([
            'ApiName' => 'get_user_data',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $user,
        ], 200);
    }

    public function change_password(Request $request)
    {

        $Validator = Validator::make(
            $request->all(),
            [
                'old_password' => 'required',
                'new_password' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $user_data = User::where('id', Auth()->user()->id)->first();
        $message = 'Your Password changed Successfully ';
        $status = false;
        $status_code = 400;

        if (! Hash::check($request->old_password, $user_data->password)) {
            $message = 'Old password is incorrect!!';
        } else {
            // $user_data->update(['password' => Hash::make($request->new_password)]);

            $user_data->password = Hash::make($request->new_password);
            $user_data->first_time_changed_password = 1;
            $user_data->save();
            $other_data['new_password'] = $request->new_password;
            $other_data['email'] = $user_data->email;
            $other_data['first_name'] = $user_data->first_name;

            // New mail send funcnality.
            $change_password_email_content = SequiDocsEmailSettings::change_password_email_content($user_data, $other_data);
            $email_content['email'] = $user_data->email;
            $email_content['subject'] = $change_password_email_content['subject'];
            $email_content['template'] = $change_password_email_content['template'];
            // return $email_content;
            if ($change_password_email_content['is_active'] == 1 && $change_password_email_content['template'] != '') {
                $this->sendEmailNotification($email_content);
            } else {
                $email_content = [];
                $email_content['subject'] = 'Change Password';
                $email_content['email'] = $other_data['email'];
                $email_content['template'] = view('mail.change_password_success', compact('other_data'));
                $this->sendEmailNotification($email_content);
            }
            $status = true;
            $status_code = 200;
        }

        return response()->json([
            'ApiName' => 'Change Password Api',
            'status' => $status,
            'message' => $message,
        ], $status_code);
    }

    private function generateShortLink($url)
    {
        // Example implementation of URL shortening logic
        // In a real-world scenario, you'd likely integrate with a URL shortening service
        // or create a database of short URLs mapping to long URLs.

        // For simplicity, this example just returns a dummy short URL.
        return 'https://short.url/'.substr(md5($url), 0, 8);
    }

    /**
     * @OA\Post(
     *     path="/forgot-password",
     *     summary="Forgot password",
     *     description="Send a password reset link to the user's email",
     *     operationId="forgotPassword",
     *     tags={"Authentication"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="User email",
     *
     *         @OA\JsonContent(
     *             required={"email"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Password reset link sent",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Password reset link sent to your email")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="email",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The email field is required.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function forgotPassword(Request $request): JsonResponse
    {

        $Validator = Validator::make(
            $request->all(),
            [
                'email' => 'required|email',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $user = User::where('email', $request->email)->first();
        $check_domain_setting = DomainSetting::check_domain_setting($request->email);
        if (! $check_domain_setting['status']) {
            return response()->json([
                'ApiName' => 'Send email Forgot Password',
                'status' => false,
                'message' => $check_domain_setting['message'],
            ], 400);
        }
        if ($user) {
            $userId = Crypt::encrypt($user->id, 12);
            $user['encrypt_id'] = $userId;
            $user['url'] = $this->url->to('/');
            $user->encrypt_id;

            $forgot = [];
            $serverIP = $this->url->to('/');
            $other_data['serverIP'] = $serverIP;
            $other_data['encrypt_id'] = $userId;

            $forgot_password_email_content = SequiDocsEmailSettings::forgot_password_email_content($user, $other_data);
            $forgot_password_email_content['template'];
            $email_content['email'] = $user->email;
            $email_content['subject'] = $forgot_password_email_content['subject'];
            $email_content['template'] = $forgot_password_email_content['template'];
            $Forgot_Password_Link = $forgot_password_email_content['Forgot_Password_Link'];

            if ($forgot_password_email_content['is_active'] == 1 && $forgot_password_email_content['template'] != '') {
                $this->sendEmailNotification($email_content);
            } else {
                // old template for mail send
                $user['encrypt_id'] = $userId;
                $user['url'] = $this->url->to('/');
                $user['subject'] = 'Forgot Password';
                $user['email'] = $user->email;
                $user['template'] = view('mail.forgotPassword', compact('user'));

                // $this->sendEmailForForgotPassword($user);
                $this->sendEmailNotification($user);
            }
            if ($user->email && $request->email) {
                UserProfileHistory::create([
                    'user_id' => $user->id,
                    'updated_by' => 1,
                    'field_name' => 'reset_password',
                    'old_value' => 'Password updated',
                    'new_value' => null,
                ]);
            }

            return response()->json([
                'ApiName' => 'Send email Forgot Password',
                'status' => true,
                'message' => 'Send email Successfully.',
                // 'Forgot_Password_Link' => $Forgot_Password_Link
                'Forgot_Password_Link' => '',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Send email Forgot Password',
                'status' => false,
                'message' => 'This email does not match any user.',
            ], 200);
        }
    }

    public function review_personal_information_taxes(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'email' => 'required',
                'unique_email_template_code' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        if (isset($request->email) && $request->email != '') {
            $emailArray = explode(',', $request->email);
            // dd($emailArray);
            foreach ($emailArray as $emailkey => $email) {
                $user = User::where('email', $email)->first();
                if ($user) {
                    $userId = Crypt::encrypt($user->id, 12);
                    $user['encrypt_id'] = $userId;
                    $user['url'] = $this->url->to('/');
                    $user->encrypt_id;
                    $forgot = [];
                    $serverIP = $this->url->to('/');
                    $other_data['serverIP'] = $serverIP;
                    $other_data['encrypt_id'] = $userId;
                    $forgot_password_email_content = SequiDocsEmailSettings::review_personal_information_taxes_email_content($user, $other_data, $request);
                    $forgot_password_email_content['template'];
                    $email_content['email'] = $user->email;
                    $email_content['subject'] = $forgot_password_email_content['subject'];
                    $email_content['template'] = $forgot_password_email_content['template'];
                    $Forgot_Password_Link = $forgot_password_email_content['Forgot_Password_Link'];
                    if ($forgot_password_email_content['is_active'] == 1 && $forgot_password_email_content['template'] != '') {
                        $this->sendEmailNotification($email_content);
                    }

                    $emailsentcount[] = $emailkey;
                    $sentemailAddressArray[] = $email;

                } else {
                    return response()->json([
                        'ApiName' => 'Send email to review personal information taxes',
                        'status' => false,
                        'message' => 'Email is not match.',
                    ], 200);
                }
            }

            return response()->json([
                'ApiName' => 'Send email to review personal information taxes',
                'sent_email_count' => count($emailsentcount),
                'sent_email_addresses' => implode(',', $sentemailAddressArray),
                'status' => true,
                'message' => 'Send email Successfully.',
                // 'Forgot_Password_Link' => $Forgot_Password_Link
                'Forgot_Password_Link' => '',
            ], 200);
        }
    }

    public function resetPass(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'encrypt_id' => 'required',
            'newpassword' => 'required|min:6',
            'confirmpassword' => 'required|same:newpassword',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        try {
            // Log the incoming encrypted ID
            Log::info('Incoming encrypt_id: '.$request->encrypt_id);

            // Attempt to decrypt the encrypted ID
            $userId = Crypt::decrypt($request->encrypt_id);
            Log::info('Decrypted User ID: '.$userId);

            // Find the user by decrypted ID
            $user = User::find($userId);
            if ($user) {
                Log::info('User found: '.$user->email);
                // Update the user's password
                $user->password = Hash::make($request->newpassword); // Use Hash facade
                $user->first_time_changed_password = 1; // to prevent asking reset password after forgot password
                $user->save();
                Log::info('Password updated successfully for user: '.$user->email);

                return response()->json([
                    'ApiName' => 'Reset Password',
                    'status' => true,
                    'message' => 'Password reset successfully.',
                ], 200);
            } else {
                Log::error('User not found for ID: '.$userId);

                return response()->json([
                    'ApiName' => 'Reset Password',
                    'status' => false,
                    'message' => 'User not found.',
                ], 404);
            }
        } catch (DecryptException $e) {
            Log::error('DecryptException: '.$e->getMessage());

            return response()->json([
                'ApiName' => 'Reset Password',
                'status' => false,
                'message' => 'Invalid encrypted ID.',
            ], 400);
        }
    }

    public function resetPassword($id): View
    {
        return view('mail.resetPassword', compact('id'));
    }

    public function updatePassword(Request $request)
    {

        // $request->validate([
        //     'password' => 'required|min:8|confirmed',
        //     'password_confirmation' => 'required|min:8',
        //     'uid' => 'required',
        // ]);

        $uid = Crypt::decrypt($request->uid);
        $passwordReset = User::firstWhere('id', $uid);

        // check if it does not expired: the time is one hour
        if ($passwordReset->created_at > now()->addHour()) {
            $passwordReset->delete();

            return response(['message' => trans('passwords.code_is_expire')], 422);
        }

        // find user's email
        $user = User::firstWhere('email', $passwordReset->email);
        $user->password = Hash::make($request->password);
        $user->first_time_changed_password = 1; // to prevent asking reset password upon login after change password
        $user->save();

        $message = 'Your Password Reset Successfully ';

        return view('mail.thankYouResetPassword', compact('message'));

        // return response()->json([
        //     'ApiName' => 'Reset Password API',
        //     'status' => true,
        //     'message' => 'Reset Password Successfully.',
        // ], 200);

    }

    /**
     * @OA\Post(
     *     path="/logout",
     *     summary="User logout",
     *     description="Invalidate the user's access token",
     *     operationId="userLogout",
     *     tags={"Authentication"},
     *     security={"sanctum":{}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Successfully Logged out")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
    // method for user logout and delete token
    public function logout()
    {
        auth()->user()->tokens()->delete();

        return [
            'message' => 'You have successfully logged out successfully.',
        ];
    }

    // get group wise permission
    public function get_permission($groupId)
    {
        // $groupId = $id;
        $roledata = GroupPermissions::distinct()->select('role_id')->where('group_id', $groupId)->get();

        $data = [];
        foreach ($roledata as $key => $role) {
            $datarole = Roles::where('id', $role->role_id)->first();
            $data[$key] = $datarole;

            $group_policies = GroupPermissions::select('group_policies_id')->where('group_id', $groupId)->where('role_id', $role->role_id)->groupBy('group_policies_id')->get();
            $moduleData = [];
            foreach ($group_policies as $key1 => $module) {
                $module_id = $module->group_policies_id;
                $grouppolicies = GroupPolicies::where('id', $module_id)->first();
                $moduleData[$key1] = $grouppolicies;
                $data[$key]['groupPolicy'] = $moduleData;

                $tabData = GroupPermissions::select('policies_tabs_id')->where('group_id', $groupId)->where('group_policies_id', $module_id)->groupBy('policies_tabs_id')->get();

                $moduleData1 = [];
                foreach ($tabData as $key2 => $tab) {
                    $tab_id = $tab->policies_tabs_id;
                    $moduleTabData = PoliciesTabs::where('id', $tab_id)->first();
                    $moduleData1[$key2] = $moduleTabData;
                    $moduleData[$key1]['policyTab'] = $moduleData1;
                    $subData = GroupPermissions::where('group_id', $groupId)->where('policies_tabs_id', $tab_id)->get();

                    $moduleData2 = [];
                    foreach ($subData as $key3 => $sub) {
                        $submodule_id = $sub->permissions_id;
                        $submoduleData = Permissions::where('id', $submodule_id)->first();
                        $moduleData2[$key3] = $submoduleData;
                        $moduleData1[$key2]['submodule'] = $moduleData2;
                    }
                }
            }
        }

        // Position Access Permission
        if ($groupId) {
            $positionAccess = ProfileAccessPermission::where(['group_id' => $groupId, 'type' => 'position_access'])->pluck('position_id')->toArray();
            if (! empty($positionAccess)) {
                $data1['position_access'] = $positionAccess;
            } else {
                $data1['position_access'] = [];
            }

            $profileAccess = ProfileAccessPermission::where(['group_id' => $groupId, 'type' => 'profile_access'])->first();
            $data1['profile_access'] = isset($profileAccess['profile_access_for']) ? $profileAccess['profile_access_for'] : null;

            $accountAccess = ProfileAccessPermission::where(['group_id' => $groupId, 'type' => 'account_access'])->first();
            $data1['account_access'] = [
                'payroll_history' => isset($accountAccess['payroll_history']) ? $accountAccess['payroll_history'] : 0,
                'reset_password' => isset($accountAccess['reset_password']) ? $accountAccess['reset_password'] : 0,
            ];
        }
        $result['access_rights'] = $data;
        $result['profile_access_config'] = $data1;
        // End Position Access Permission

        return $result;
        // return response()->json([
        //     'ApiName' => 'get_permission',
        //     'status' => true,
        //     'message' => 'Successfully.',
        //     'data' => $data,
        // ], 200);
    }

    // get profile company
    public function getCompanyProfile()
    {

        $company_profile = CompanyProfile::first();

        // return $user;
        return response()->json([
            'ApiName' => 'get-profile',
            'status' => true,
            'message' => 'Profile Successfully.',
            'data' => $company_profile,
        ], 200);
    }

    public function updateCompanyProfile(Request $request)
    {
        // return $request;
        $Validator = Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'address' => 'required',
                'phone_number' => 'required',
                'company_type' => 'required',
                'md_charge' => 'required',
                'hold_commission' => 'required',
                'overrides_on_other_sales' => 'required',
                'plan_id' => 'required',
                'crm_id' => 'required',
                'payroll_id' => 'required',
                'accounting_software_id' => 'required',
                'state_id' => 'required',
                'city_id' => 'required',
                'lat' => 'required',
                'lng' => 'required',
                'image' => 'required|image|mimes:jpg,png,jpeg,gif,svg|max:2048',
                // 'logo'  => 'required|mimes:jpg,png,jpeg,gif,svg|max:2048',

            ]
        );

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        // dd($request->all());
        // $imageName = time().'.'.$request->logo->extension();
        // $image_path = $request->file('image')->store('Company_profile', 'public');
        $file = $request->file('image');
        $image_path = time().$file->getClientOriginalName();
        $ex = $file->getClientOriginalExtension();
        $destinationPath = 'company-image';
        $image_path = $file->move($destinationPath, time().$file->getClientOriginalName());

        // $image_path =  "company-image/".time() . $file->getClientOriginalName();
        // \Storage::disk("s3")->put($image_path,file_get_contents($file));

        // dd($image_path);die;
        $data = CompanyProfile::find($request['id']);
        $data->name = $request['name'];
        $data->address = $request['address'];
        $data->phone_number = $request['phone_number'];
        $data->company_type = $request['company_type'];
        $data->hold_commission = $request['hold_commission'];
        $data->overrides_on_other_sales = $request['overrides_on_other_sales'];
        $data->plan_id = $request['plan_id'];
        $data->crm_id = $request['crm_id'];
        $data->payroll_id = $request['payroll_id'];
        $data->accounting_software_id = $request['accounting_software_id'];
        $data->state_id = $request['state_id'];
        $data->city_id = $request['city_id'];
        $data->lat = $request['lat'];
        $data->lng = $request['lng'];
        $data->logo = $image_path;
        $data->save();

        return response()->json([
            'ApiName' => 'update-profile',
            'status' => true,
            'message' => 'Profile  update Successfully.',
            'data' => $data,
        ], 200);
    }

    public function updateHiringProcessStatusByUser($id, $status): View
    {
        $uid = Crypt::decrypt($id);

        $onBoarding = OnboardingEmployees::where('id', $uid)->where('status_id', 4)->orWhere('status_id', 12)->where('id', $uid)->first();
        $date = date('Y-m-d');
        if (isset($onBoarding->offer_expiry_date) && $onBoarding->offer_expiry_date < $date) {
            $status = 'expire';

            return view('mail.thankyou', compact('status'));
        }

        $onBoardings = OnboardingEmployees::where('id', $uid)->first();
        if ($onBoardings->status_id == 1 || $onBoardings->status_id == 13 || $onBoardings->status_id == 2 || $onBoardings->status_id == 6 || $onBoardings->status_id == 7) {
            $status = 'already';

            return view('mail.thankyou', compact('status'));
        }
        // dd($uid);
        if ($onBoarding) {

            if ($status == 'Accepted') {
                $statusId = 1;
                if ($onBoarding->user_offer_letter != null) {
                    $statusId = 13;
                    $signatureDocument = $this->signatureRequests($uid);
                }
            } elseif ($status == 'Declined') {
                $statusId = 2;
            } else {
                $statusId = 6;
            }
            $data = OnboardingEmployees::find($uid);
            $data->status_id = $statusId;
            $data->save();
        }

        return view('mail.thankyou', compact('status'));
    }

    public function updateHiringProcessChangeRequestByUser($id, $status)
    {
        // return $status;
        $uid = Crypt::decrypt($id);

        $onBoarding = OnboardingEmployees::where('id', $uid)->where('status_id', 4)->orWhere('status_id', 12)->where('id', $uid)->first();

        $onBoardings = OnboardingEmployees::where('id', $uid)->first();
        if ($onBoardings->status_id == 1 || $onBoardings->status_id == 13 || $onBoardings->status_id == 2 || $onBoardings->status_id == 6 || $onBoardings->status_id == 7) {
            $status = 'already';

            return view('mail.thankyou', compact('status'));
        }

        $date = date('Y-m-d');
        if (isset($onBoarding->offer_expiry_date) && $onBoarding->offer_expiry_date < $date) {
            $status = 'expire';

            return view('mail.thankyou', compact('status'));
        }

        return view('mail.requestChange', compact('id'));
    }

    public function signatureRequests($id)
    {
        $userDoc = OnboardingEmployees::with('managerDetail')->Select('id', 'manager_id', 'email', 'user_offer_letter')->where('id', $id)->first();
        $path = public_path($userDoc->user_offer_letter);
        $uriSegments = explode('/', $userDoc->user_offer_letter);
        $filename = end($uriSegments);

        $emailId = explode('@', $userDoc->email);
        $domain = DomainSetting::where('domain_name', $emailId[1])->where('status', 1)->first();
        $emailArray = [];
        if ($domain) {
            $emailArray[] = ['email' => $userDoc->email];
        }

        if ($userDoc->managerDetail) {

            $emailId = explode('@', $userDoc->managerDetail->email);
            $domain2 = DomainSetting::where('domain_name', $emailId[1])->where('status', 1)->first();
            if ($domain2) {
                $managerSignReq = SequiDocsTemplate::where(['id' => 30, 'manager_sign_req' => 1])->first();
                if ($managerSignReq) {
                    $emailArray[] = ['email' => $userDoc->managerDetail->email];
                }
            }
        }

        $url = 'https://api.digisigner.com/v1/signature_requests';

        $status = Crms::where('id', 2)->where('status', 1)->first();
        if (! empty($status)) {
            $token = 'ODQ1NDIwMzctNjgxNC00MmE3LTlmYmItMmJkYTgxMGY5ODkyOjg0NTQyMDM3LTY4MTQtNDJhNy05ZmJiLTJiZGE4MTBmOTg5Mg==';
        } else {
            $token = 'ZGIwN2YxYTctYjQ1Yy00ZjVjLWE4YzQtY2ZiM2Y2ZDBiN2U1OmRiMDdmMWE3LWI0NWMtNGY1Yy1hOGM0LWNmYjNmNmQwYjdlNQ==';
        }
        $headers = [
            'Content-Type: application/json',
            'Authorization: Basic '.$token,
        ];

        $documentId = $this->uploadDocument($path, $filename);
        $update = OnboardingEmployees::where('id', $id)->update(['document_id' => $documentId]);
        // return $documentId;
        $subject = 'Offer Letter';
        $message = 'Send Offer Letter';

        $data = '{
            "documents": [
                {
                    "document_id": "'.$documentId.'",
                    "subject": "'.$subject.'",
                    "message": "'.$message.'",
                    "signers": '.json_encode($emailArray).'
                }
            ]
        }';

        $response = $this->curlRequest($url, $data, $headers);

        return $response;
        if ($response === false) {
            return response()->json(['error' => 'Failed to communicate with the API'], 500);
        }

        return response()->json([
            'ApiName' => 'signature_request',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $response,
        ], 200);

        // return $responseData = json_decode($response, true);
    }

    public function uploadDocument($path, $filename)
    {
        $url = 'https://api.digisigner.com/v1/documents';

        $status = Crms::where('id', 2)->where('status', 1)->first();
        if (! empty($status)) {
            $token = 'ODQ1NDIwMzctNjgxNC00MmE3LTlmYmItMmJkYTgxMGY5ODkyOjg0NTQyMDM3LTY4MTQtNDJhNy05ZmJiLTJiZGE4MTBmOTg5Mg==';
        } else {
            $token = 'ZGIwN2YxYTctYjQ1Yy00ZjVjLWE4YzQtY2ZiM2Y2ZDBiN2U1OmRiMDdmMWE3LWI0NWMtNGY1Yy1hOGM0LWNmYjNmNmQwYjdlNQ==';
        }
        $headers = [
            'Authorization: Basic '.$token,
        ];

        // $path = public_path('Offer_letter.pdf');
        // $filename = 'Offer_letter.pdf';
        $cfile = new \CURLFile($path, '', $filename);
        $data = ['file' => $cfile];

        $response = $this->curlRequest($url, $data, $headers);

        if ($response === false) {
            return response()->json(['error' => 'Failed to communicate with the API'], 500);
        }

        $responseData = json_decode($response, true);

        if (isset($responseData['error'])) {
            return response()->json(['error' => $responseData['error']], 400);
        }

        return $documentId = $responseData['document_id'];
        // return response()->json(['document_id' => $documentId]);
    }

    public function curlRequest($url, $data, $headers)
    {

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => $headers,

        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        return $response;
    }

    // not in use new function created in SequiDocsTemplateContoller user_comment_new method
    public function userComment(Request $request): View
    {

        $uid = Crypt::decrypt($request->uid);
        $user = OnboardingEmployees::where('id', $uid)->first();
        leadComment::create([
            'lead_id' => $uid,
            'user_id' => isset($user->recruiter_id) ? $user->recruiter_id : null,
            'comments' => $request->comment,
            'status' => 1,
        ]);
        $status = 'Request Change';
        $email = User::where('id', $user->recruiter_id)->first();
        if ($email) {

            $comment = $request->comment;
            $data['email'] = $email->email;
            $data['subject'] = 'Request Change for user';
            $data['template'] = view('mail.changerequest', compact('user', 'comment'));
            $this->sendEmailNotification($data);
        }

        return view('mail.thankyou', compact('status'));
    }

    public function exportSalesData(Request $request)
    {
        $file_name = 'employees_'.date('Y_m_d_H_i_s').'.csv';
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        return Excel::download(new EmployeesExport($startDate, $endDate), $file_name);
    }

    public function exportClawbackData(Request $request)
    {
        $file_name = 'employees_'.date('Y_m_d_H_i_s').'.csv';
        if (isset($request->state_code) && isset($request->time_val)) {
            if ($request->time_val == 'this_year') {
                $now = Carbon::now();
                $monthStart = $now->startOfYear();
                $startDate = date('Y-m-d', strtotime($monthStart));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
                $statCode = $request->state_code;
            } elseif ($request->time_val == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
                $statCode = $request->state_code;
            } elseif ($request->time_val == 'this_month') {
                $new = Carbon::now(); // returns current day
                $firstDay = $new->firstOfMonth();
                $startDate = date('Y-m-d', strtotime($firstDay));
                $end = Carbon::now();
                $endDate = date('Y-m-d', strtotime($end));
                $statCode = $request->state_code;
            } elseif ($request->time_val == 'last_month') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonth()->startOfMonth()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonth()->endOfMonth()));
                $statCode = $request->state_code;
            } elseif ($request->time_val == 'past_three_month') {
                $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
                $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(30)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
                $statCode = $request->state_code;
            }

            return Excel::download(new ClawbackDataExport($startDate, $endDate, $statCode), $file_name);
        } else {

            return Excel::download(new ClawbackDataExport, $file_name);
        }
    }

    public function exportPendingData(Request $request)
    {
        $file_name = 'employees_'.date('Y_m_d_H_i_s').'.csv';

        if (isset($request->state_code) && isset($request->time_val)) {
            if ($request->time_val == 'this_year') {
                $now = Carbon::now();
                $monthStart = $now->startOfYear();
                $startDate = date('Y-m-d', strtotime($monthStart));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
                $statCode = $request->state_code;
            }
            if ($request->time_val == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
                $statCode = $request->state_code;
            }
            if ($request->time_val == 'this_month') {
                $new = Carbon::now(); // returns current day
                $firstDay = $new->firstOfMonth();
                $startDate = date('Y-m-d', strtotime($firstDay));
                $end = Carbon::now();
                $endDate = date('Y-m-d', strtotime($end));
                $statCode = $request->state_code;
            }
            if ($request->time_val == 'last_month') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonth()->startOfMonth()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonth()->endOfMonth()));
                $statCode = $request->state_code;
            }
            if ($request->time_val == 'past_three_month') {
                $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
                $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(30)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
                $statCode = $request->state_code;
            }

            return Excel::download(new PendingInstallExport($startDate, $endDate, $statCode), $file_name);
        } else {
            return Excel::download(new PendingInstallExport, $file_name);
        }
    }

    public function thirdParty(Request $request)
    {
        $ch = curl_init();
        $url = $request['url'];
        $googleMapsKey = config('services.google.maps_api_key');

        if (isset($request['type']) && $request['type'] == 'gmap') {
            $url = $this->addOrUpdateQueryParam($request['url'], 'key', $googleMapsKey);
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 80);

        $response = curl_exec($ch);

        if (curl_error($ch)) {
            echo 'Request Error:'.curl_error($ch);
        } else {
            echo $response;
        }

        curl_close($ch);
    }

    public function hubspotImportData(Request $request, $url = '')
    {

        // $hs_key = $request->header('hs_key');
        $hs_key = $_GET['hs_key'];
        // $json = file_get_contents('php://input');

        // decode json
        // $object = json_decode($json);
        $hubspotImportData = json_encode(json_decode(file_get_contents('php://input')));
        $data = [
            'first_name' => json_encode(json_decode(file_get_contents('php://input'))),
            'last_name' => $hs_key,
        ];
        TestData::create($data);
        // die;

        if ($hs_key == 'sKcxsgcpR8xxDXp4Rzu0A535CAE') {

            // $url = "https://api.hubapi.com/crm/v3/objects/p_installs?properties=first_name%2Clast_name%2Chubspot_owner_id%2Chs_created_by_user_id%2Caccount_manager%2Cproject%2Cemail%2Cfull_name%2Chubspot_owner_id%2Cadders_total%2Caddress%2Cappointment_date%2Capr%2Ccancelation_date%2Ccity%2Cclawback%2Ccloser%2Ccontract_hia%2Ccontract_loan%2Ccontract_agreement_hia%2Ccontract_sign_date%2Ccontract_signed%2Ccounty%2Cdays_in_stage%2Cdealer_fee_%2Cdealer_fee_amount%2Cdesign_approved%2Cdesign_time%2Cdiscounts%2Cemail%2Cenefro_source%2Cenerflo%2Cenerflo_install_id%2Cengineering_status%2Cest_commissions%2Cestimated_first_year_production%2Cfinance_product%2Cfinancer%2Cfull_address%2Cfull_name%2Cgross_ppw%2Chs_object_id%2Cinspection_complete%2Cinspection_status%2Cm1_com_approved%2Cm2_com_date%2Cnet_ppw_calc%2Cphone%2Cpostal_code%2Cproject_status%2Crep_redline%2Cpto%2Cpto_status%2Csetter%2Csigned_to_install%2Cstate%2Csystem_size%2Csystem_size_kw%2Cteam%2Ctoday_s_date%2Ctotal_cost%2Cutility_bill%2Cutility_company%2Cuuid_sales_rep%2Cproject%2Cadder_%2Cadders_description,dealer_fee,setter_id,closer_id";

            // $tokens = "pat-na1-e6d7ca8e-8fbd-460a-a3df-8fa64cb12641";
            // $ch = curl_init();
            // curl_setopt_array($ch, array(
            //     CURLOPT_URL => $url, // your preferred url
            //     CURLOPT_RETURNTRANSFER => true,
            //     CURLOPT_ENCODING => "",
            //     CURLOPT_MAXREDIRS => 10,
            //     CURLOPT_TIMEOUT => 30000,
            //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            //     CURLOPT_CUSTOMREQUEST => "GET",
            //     //CURLOPT_POSTFIELDS => json_encode($data),
            //     CURLOPT_HTTPHEADER => array(
            //         "content-type: application/json",
            //         "Authorization:Bearer $tokens",
            //     ),
            // )
            // );

            // $response = curl_exec($ch);
            // $err = curl_error($ch);
            // $res = (object) json_decode($response);

            $data = $hubspotImportData;
            $aa = json_decode($data, true);
            $data = $aa['properties'];
            // foreach ($newData as $key => $data) {

            $hubspotSubroutine = $this->hubspotSubroutine($data);

            // }

            // Get data api table and excel sheet table.....................................................................

            $newData = \DB::table('legacy_api_raw_data as lad')->select('lad.pid', 'lad.weekly_sheet_id', 'lad.install_partner', 'lad.homeowner_id', 'lad.proposal_id', 'lad.install_partner_id', 'lad.kw', 'lad.setter_id', 'lad.proposal_id', 'lad.customer_name', 'lad.customer_address', 'lad.customer_address_2', 'lad.customer_city', 'lad.customer_state', 'lad.customer_zip', 'lad.customer_email', 'lad.customer_phone', 'lad.employee_id', 'lad.sales_rep_name', 'lad.sales_rep_email', 'lad.customer_signoff', 'lad.m1_date', 'lad.scheduled_install', 'lad.install_complete_date', 'lad.m2_date', 'lad.date_cancelled', 'lad.return_sales_date', 'lad.gross_account_value', 'lad.cash_amount', 'lad.loan_amount', 'lad.dealer_fee_percentage', 'lad.adders', 'lad.adders_description', 'lad.funding_source', 'lad.financing_rate', 'lad.financing_term', 'lad.product', 'lad.epc', 'lad.net_epc', 'lad.cancel_fee', 'lad.closer_id', 'lad.contract_sign_date', 'lad.dealer_fee_amount', 'lad.dealer_fee_percentage')
                ->where('lad.install_complete_date', null)
                ->get();

            // Update data by previous comparison in Sales_Master
            foreach ($newData as $checked) {

                $val = [
                    'pid' => $checked->pid,
                    'weekly_sheet_id' => $checked->weekly_sheet_id,
                    'install_partner' => $checked->install_partner,
                    'install_partner_id' => $checked->install_partner_id,
                    'customer_name' => $checked->customer_name,
                    'customer_address' => $checked->customer_address,
                    'customer_address_2' => $checked->customer_address_2,
                    'customer_city' => $checked->customer_city,
                    'customer_state' => $checked->customer_state,
                    'customer_zip' => $checked->customer_zip,
                    'customer_email' => $checked->customer_email,
                    'customer_phone' => $checked->customer_phone,
                    'homeowner_id' => $checked->homeowner_id,
                    'proposal_id' => $checked->proposal_id,
                    'sales_rep_name' => $checked->sales_rep_name,
                    'employee_id' => $checked->employee_id,
                    'sales_rep_email' => $checked->sales_rep_email,
                    'kw' => $checked->kw,
                    'date_cancelled' => isset($checked->date_cancelled) ? $checked->date_cancelled : null,
                    'customer_signoff' => $checked->contract_sign_date,
                    'm1_date' => isset($checked->m1_date) ? $checked->m1_date : null,
                    'm2_date' => isset($checked->m2_date) ? $checked->m2_date : null,
                    'product' => $checked->product,
                    'epc' => isset($checked->epc) ? $checked->epc : null,
                    'net_epc' => isset($checked->net_epc) ? $checked->net_epc : null,
                    'gross_account_value' => $checked->gross_account_value,
                    'dealer_fee_percentage' => $checked->dealer_fee_percentage,
                    'adders' => $checked->adders,
                    'adders_description' => $checked->adders_description,
                    'funding_source' => $checked->funding_source,
                    'financing_rate' => $checked->financing_rate,
                    'financing_term' => $checked->financing_term,
                    'scheduled_install' => $checked->scheduled_install,
                    'install_complete_date' => $checked->install_complete_date,
                    'return_sales_date' => $checked->return_sales_date,
                    'cash_amount' => $checked->cash_amount,
                    'loan_amount' => $checked->loan_amount,
                    'dealer_fee_amount' => $checked->dealer_fee_amount,
                    'dealer_fee_percentage' => $checked->dealer_fee_percentage,
                    'data_source_type' => 'api',
                ];

                if (! empty($checked->net_epc)) {
                    $calculate = SalesMaster::where('pid', $checked->pid)->first();
                    // dd($calculate);
                    if (empty($calculate)) {
                        $insertData = '';
                        $user = User::where('employee_id', $checked->closer_id)->first();
                        $insertData = SalesMaster::create($val);
                        $data = [
                            'sale_master_id' => $insertData->id,
                            'weekly_sheet_id' => $insertData->weekly_sheet_id,
                            'pid' => $checked->pid,
                            'closer1_id' => $user->id,
                        ];
                        SaleMasterProcess::create($data);
                    } else {
                        $updateData = SalesMaster::where('pid', $checked->pid)->update($val);
                    }
                }
            }
            // dd('check salemaster');die;
        }
        exit('check');
    }

    /**
     * @OA\Post(
     *     path="/sequifi/api/addLead",
     *     summary="Register a new lead",
     *
     *     @OA\Parameter(
     *         name="api_key",
     *         in="query",
     *         description="api key",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="first_name",
     *         in="query",
     *         description="User's first name",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="last_name",
     *         in="query",
     *         description="User's last name",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     * @OA\Parameter(
     *         name="email",
     *         in="query",
     *         description="User's email",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     * @OA\Parameter(
     *         name="mobile_no",
     *         in="query",
     *         description="User's mobile no",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     * @OA\Parameter(
     *         name="state_id",
     *         in="query",
     *         description="State id",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     * * @OA\Parameter(
     *         name="comments",
     *         in="query",
     *         description="comments",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     * * @OA\Parameter(
     *         name="office_id",
     *         in="query",
     *         description="office id",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     * * @OA\Parameter(
     *         name="source",
     *         in="query",
     *         description="source",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(response="201", description="User registered successfully"),
     *     @OA\Response(response="422", description="Validation errors")
     * )
     */
    public function addLead(Request $request): JsonResponse
    {

        // $recru = User::where('id',$request['recruiter_id'])->first();
        $apiKey = $request->input('api_key');

        if ($apiKey == 'sKdhhdfR8xxDXp4Rzu0A535CEF') {

            if (! null == $request->all()) {
                $Validator = Validator::make(
                    $request->all(),
                    [
                        // 'email' => 'required|email|unique:leads|unique:users|unique:onboarding_employees',
                        // 'mobile_no' => 'required|unique:leads,mobile_no',

                    ]
                );
                if ($Validator->fails()) {
                    return response()->json(['error' => $Validator->errors()], 400);
                }
                $user = auth('api')->user();
                if (! empty($user)) {
                    $source = User::where('id', $user->id)->first();
                    $lastName = isset($source->last_name) ? $source->last_name : null;
                } else {
                    $lastName = 'API';
                }

                $data = Lead::create(
                    [
                        'first_name' => $request['first_name'],
                        'last_name' => $request['last_name'],
                        'email' => $request['email'],
                        'mobile_no' => $request['mobile_no'],
                        'state_id' => $request['state_id'],
                        'office_id' => isset($request['office_id']) ? $request['office_id'] : null,
                        'comments' => $request['comments'],
                        'source' => $lastName,
                        'status' => 'FollowUp',
                        'action_status' => 'Schedule Interview',
                        'source' => $lastName,
                        'recruiter_id' => null,
                        'type' => 'Lead',
                    ]
                );

                $comment = leadComment::create(
                    [
                        'user_id' => null,
                        'lead_id' => $data->id,
                        'comments' => $request['comments'],
                        'status' => 1,
                    ]
                );

                return response()->json([
                    'ApiName' => 'add-leads',
                    'status' => true,
                    'message' => 'add Successfully.',
                    'data' => $data,
                    // 'comment' => $comment
                ], 200);
            }
        } else {
            return response()->json([
                'ApiName' => 'add-leads',
                'status' => false,
                'message' => 'Invalid api key.',
            ], 200);
        }
    }

    /**
     * @OA\Post(
     *     path="/sequifi/api/updateLead",
     *     summary="Update lead",
     *
     *     @OA\Parameter(
     *         name="api_key",
     *         in="query",
     *         description="api key",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="first_name",
     *         in="query",
     *         description="User's first name",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="last_name",
     *         in="query",
     *         description="User's last name",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     * @OA\Parameter(
     *         name="email",
     *         in="query",
     *         description="User's email",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     * @OA\Parameter(
     *         name="mobile_no",
     *         in="query",
     *         description="User's mobile no",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     * * @OA\Parameter(
     *         name="comments",
     *         in="query",
     *         description="comments",
     *         required=true,
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(response="201", description="User registered successfully"),
     *     @OA\Response(response="422", description="Validation errors")
     * )
     */
    public function updateLead(Request $request): JsonResponse
    {

        $apiKey = $request->input('api_key');
        $id = $request->id;
        if ($apiKey == 'sKdhhdfR8xxDXp4Rzu0A535CEF') {
            if (! null == $request->all()) {
                $Validator = Validator::make(
                    $request->all(),
                    [
                        // 'email' => 'required|email|unique:leads,email,'.$id.'|unique:users|unique:onboarding_employees',
                        // 'mobile_no' => 'required|unique:leads,mobile_no,'.$id.'',

                    ]
                );
                if ($Validator->fails()) {
                    return response()->json(['error' => $Validator->errors()], 400);
                }

                $data = Lead::find($id);
                if ($data == null) {
                    return response()->json([
                        'ApiName' => 'Update Leads',
                        'status' => false,
                        'message' => 'Invalid ID',
                    ], 404);
                }
                $data->first_name = $request['first_name'];
                $data->last_name = $request['last_name'];
                $data->email = $request['email'];
                $data->mobile_no = $request['mobile_no'];
                $data->comments = $request['comments'];

                $data->save();

                return response()->json([
                    'ApiName' => 'Update Leads',
                    'status' => true,
                    'message' => 'Updated Lead Successfully.',
                    // 'data' => $data,
                ], 200);
            }
        } else {
            return response()->json([
                'ApiName' => 'add-leads',
                'status' => false,
                'message' => 'Invalid api key.',
            ], 200);
        }
    }

    public function exportUsersData(Request $request)
    {
        $file_name = 'users_'.date('Y_m_d_H_i_s').'.csv';

        return Excel::download(new UserExport, $file_name);
    }

    public function hubspotSyncData(Request $request)
    {
        if (config('app.domain_name') == 'aveyo' || config('app.domain_name') == 'aveyo2') {
            $CrmData = Crms::where('id', 2)->where('status', 1)->first();
            $CrmSetting = CrmSetting::where('crm_id', 2)->first();
            if (! empty($CrmData) && ! empty($CrmSetting)) {

                // $data = User::

                $val = json_decode($CrmSetting['value']);
                $token = $val->api_key;
                $data = User::with([
                    'recruiter:id,first_name,last_name',
                    'managerDetail:id,first_name,last_name',
                    'teamsDetail:id,team_name:',
                    'office:id,office_name,work_site_id,general_code',
                    'positionDetailTeam:id,position_name',
                    'departmentDetail:id,name',
                    'state:id,name',
                ])
                    ->where('is_super_admin', '!=', 1)
                    // ->get();

                    ->chunk(200, function ($items) use ($token) {
                        // return $items;
                        HubSpotDataSyncJob::Dispatch($token, $items);
                        // $hubspotSaleDataCreate = $this->SyncHsSalesDataCreate($items,$token);
                    });
            }

            $HsSyncCount = User::where('aveyo_hs_id', '!=', null)->where('is_super_admin', '!=', 1)->count();

            return response()->json([
                'ApiName' => 'hubspot Sync Data',
                'status' => true,
                // 'message' => 'hubspot Sync Data Successfully',
                'message' => 'Request sent Successfully. hubspot Data syncing  in background',
                'success_sync_data' => $HsSyncCount,
            ], 200);
        }

        return response()->json([
            'ApiName' => 'hubspot Sync Data',
            'status' => false,
            // 'message' => 'hubspot Sync Data Successfully',
            'message' => "Oops! It seems like the sync feature isn't compatible with this domain.",
            'success_sync_data' => 0,
        ], 400);
    }

    /**
     * @method addOrUpdateQueryParam
     * add or update query string of a url
     */
    public function addOrUpdateQueryParam($url, $key, $value)
    {
        $urlComponents = parse_url($url);
        parse_str($urlComponents['query'] ?? '', $queryParams);
        $queryParams[$key] = $value;
        $newQueryString = http_build_query($queryParams);
        $newUrl = $urlComponents['scheme'].'://'.$urlComponents['host'];
        if (isset($urlComponents['path'])) {
            $newUrl .= $urlComponents['path'];
        }
        $newUrl .= '?'.$newQueryString;

        return $newUrl;
    }

    public function pushUserFieldRoutes()
    {
        // Implements the field routes API for sending onboarding employee data
        $integration = Integration::where(['name' => 'FieldRoutes', 'status' => 1])->first();

        if (! empty($integration)) {

            $enc_value = openssl_decrypt(
                $integration->value,
                config('app.encryption_cipher_algo'),
                config('app.encryption_key'),
                0,
                config('app.encryption_iv')
            );
            $dnc_value = json_decode($enc_value);

            $authenticationKey = $dnc_value->authenticationKey;
            $authenticationToken = $dnc_value->authenticationToken;
            $baseURL = $dnc_value->base_url;
            $api_office = $dnc_value->office;
            $checkStatus = 'Onboarding';

            $userData = User::where('id', '>', 68)->get();
            // /$uid = ($userId->is_super_admin == 0) ? $userId->id : NULL;

            foreach ($userData as $datEmp) {
                // $userDataToCreate = [
                //     'fname' => $datEmp->first_name,
                //     'lname' => $datEmp->last_name,
                //     'email' => $datEmp->email,
                //     'phone' => $datEmp->mobile_no,
                //     'state_id' => $datEmp->state_id,
                // ];
                $uid = $datEmp->id;
                $this->fieldRoutesCreateEmployee($datEmp, $checkStatus, $uid, $authenticationKey, $authenticationToken, $baseURL);
            }

        }
    }

    public function processSaleDataKinWebhook()
    {
        $user = User::first();
        $namespace = app()->getNamespace();
        $salesController = app()->make($namespace.\Http\Controllers\API\V2\Sales\SalesController::class);

        return $salesController->excelInsertUpdateSaleMaster($user, 'KinWebhook');
    }

    public function processSaleData(Request $request): JsonResponse
    {
        $request->merge([
            'type' => trim($request->input('type')),
            'workerName' => trim($request->input('workerName')),
        ]);
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'regex:/^[a-zA-Z0-9_-]+(?: [a-zA-Z0-9_-]+)*$/'],
            'workerName' => ['required', 'regex:/^[a-zA-Z_-]+$/'],
        ], [
            'type.regex' => 'The type may only contain letters, numbers, underscores, hyphens, and single spaces — no leading, trailing, or multiple spaces.',
            'workerName.regex' => 'The worker name may only contain letters, underscores, and hyphens.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ApiName' => 'process-sale-data',
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        try {
            // Get the first user as before
            $user = User::first();

            // Check if there are records to process
            $domainName = config('app.domain_name');
            $query = LegacyApiRawDataHistory::where('data_source_type', $request->type)
                ->where('import_to_sales', '0');
            if ($domainName != 'momentumv2') {
                $query->whereNotNull('closer1_id'); // Ensure we only process records with a valid closer
            }

            // Apply domain-specific filters
            if ($domainName == 'evomarketing') {
                $query->whereNotNull('initial_service_date');
            } elseif ($domainName == 'whitenight') {
                $query->whereNotNull('initial_service_date');
            }

            $count = $query->count();

            // Check if there are records to process
            if ($count == 0) {
                return response()->json([
                    'status' => true,
                    'message' => 'No '.$request->type.' sales data records found to process',
                    'data' => [
                        'records_to_process' => 0,
                    ],
                ]);
            }

            // Create a batch process tracker record
            $tracker = BatchProcessTracker::create([
                'process_type' => $request->type,
                'status' => 'queued',
                'total_records' => $count,
                'processed_records' => 0,
                'success_count' => 0,
                'error_count' => 0,
                'user_id' => $user ? $user->id : null,
                'started_at' => now(),
                'stats' => [
                    'data_source' => $request->type,
                    'domain' => $domainName,
                ],
            ]);

            // Dispatch job to process the data
            ProcessMomentumSalesDataJob::dispatch($user, $request->type, null, $tracker->id, $request->workerName)
                ->onQueue('sales-process')
                ->onConnection(config('queue.default')); // Use the default connection from config

            return response()->json([
                'status' => true,
                'message' => 'Processing started for '.$request->type.' sales data',
                'data' => [
                    'records_to_process' => $count,
                    'job_dispatched' => true,
                    'job_type' => 'Process'.$request->type.'SalesDataJob',
                    'dispatched_at' => now()->toDateTimeString(),
                    'tracker_id' => $tracker->id,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error dispatching '.$request->type.' sales processing job: '.$e->getMessage(), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Error starting processing for '.$request->type.' sales data: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }

    }

    /**
     * Get the status of a batch processing job
     *
     * @param  int  $id  The batch tracker ID
     */
    public function getBatchStatus(int $id): JsonResponse
    {
        try {
            $tracker = BatchProcessTracker::find($id);

            if (! $tracker) {
                return response()->json([
                    'status' => false,
                    'message' => 'Batch process not found',
                    'data' => null,
                ], 404);
            }

            // Calculate progress percentage
            $progressPercentage = 0;
            if ($tracker->total_records > 0) {
                $progressPercentage = round(($tracker->processed_records / $tracker->total_records) * 100, 2);
            }

            // Get estimated time remaining based on average processing speed
            $estimatedTimeRemaining = null;
            if ($tracker->started_at && $tracker->processed_records > 0 && $tracker->status !== 'completed' && $tracker->status !== 'error') {
                $elapsedSeconds = now()->diffInSeconds($tracker->started_at);
                $recordsPerSecond = $tracker->processed_records / max($elapsedSeconds, 1);
                $remainingRecords = $tracker->total_records - $tracker->processed_records;

                if ($recordsPerSecond > 0) {
                    $remainingSeconds = $remainingRecords / $recordsPerSecond;
                    $estimatedTimeRemaining = round($remainingSeconds / 60, 1); // in minutes
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Batch process status retrieved successfully',
                'data' => [
                    'id' => $tracker->id,
                    'process_type' => $tracker->process_type,
                    'status' => $tracker->status,
                    'total_records' => $tracker->total_records,
                    'processed_records' => $tracker->processed_records,
                    'success_count' => $tracker->success_count,
                    'error_count' => $tracker->error_count,
                    'progress_percentage' => $progressPercentage,
                    'estimated_time_remaining' => $estimatedTimeRemaining,
                    'started_at' => $tracker->started_at,
                    'completed_at' => $tracker->completed_at,
                    'stats' => $tracker->stats,
                    'created_at' => $tracker->created_at,
                    'updated_at' => $tracker->updated_at,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving batch status: '.$e->getMessage(), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Error retrieving batch status: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function processSaleDataLGCY()
    {
        $user = User::first();
        $namespace = app()->getNamespace();
        $salesController = app()->make($namespace.\Http\Controllers\API\V2\Sales\SalesController::class);

        return $salesController->excelInsertUpdateSaleMaster($user, 'LGCY API');
    }

    /**
     * Dispatch SaleMasterJobAwsLambda to the parlley queue
     */
    public function dispatchSaleMasterJob(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'data_source_type' => 'required|string',
                'batch_size' => 'integer|nullable',
                'worker_queue' => 'string|nullable',
                'include_closer1_id_null' => 'boolean|nullable',
            ]);

            $workerQueue = $request->input('worker_queue', 'parlley');
            $includeCloser1IdNull = $request->input('include_closer1_id_null', false);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data_source_type = $request->input('data_source_type');
            $batch_size = $request->input('batch_size', 100);

            // Log the job dispatch attempt
            Log::info("API: Dispatching SaleMasterJobAwsLambda for data source: {$data_source_type}");

            // Dispatch the job to the parlley queue
            // dispatch((new SaleMasterJobAwsLambda($data_source_type, $batch_size, $workerQueue, $includeCloser1IdNull))->onQueue($workerQueue));
            dispatch((new SaleMasterJob($data_source_type, $batch_size, $workerQueue, $includeCloser1IdNull))->onQueue($workerQueue));

            Log::info("API: SaleMasterJobAwsLambda dispatched successfully for data source: {$data_source_type}");

            return response()->json([
                'status' => true,
                'message' => 'SaleMasterJobAwsLambda dispatched successfully',
                'data' => [
                    'data_source_type' => $data_source_type,
                    'batch_size' => $batch_size,
                    'queue' => $workerQueue,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("API: Error dispatching SaleMasterJobAwsLambda: {$e->getMessage()}", [
                'data_source_type' => $request->input('data_source_type', 'unknown'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to dispatch job',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
