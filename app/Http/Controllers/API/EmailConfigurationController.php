<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\EmailConfiguration;
use App\Traits\EmailNotificationTrait;
use Exception;
use Illuminate\Http\JsonResponse;
// use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmailConfigurationController extends Controller
{
    use EmailNotificationTrait;

    public function emailConfiguration(Request $request): JsonResponse
    {
        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'email_from_address' => 'required',
                'service_provider' => 'required',
                'protocal' => 'required',
                'host_name' => 'required',
                'smtp_port' => 'required',
                'security_protocol' => 'required',
                'authentication_method' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        } else {
            $getData = EmailConfiguration::first();
            $data = [
                'email_from_address' => $request->email_from_address,
                'service_provider' => $request->service_provider,
                'host_mailer' => $request->protocal,
                'host_name' => $request->host_name,
                'smtp_port' => $request->smtp_port,
                'timeout' => isset($request->timeout) ? $request->timeout : '',
                'security_protocol' => $request->security_protocol,
                'authentication_method' => $request->authentication_method,
                'token_app_id' => isset($request->token_app_id) ? $request->token_app_id : '',
                'token_app_key' => isset($request->token_app_key) ? $request->token_app_key : '',
                'user_name' => isset($request->mail_user_name) ? $request->mail_user_name : '',
                'password' => isset($request->mail_password) ? $request->mail_password : '',
            ];
            if ($getData == '') {
                EmailConfiguration::create($data);
                $message = 'Add Email Configuration Successfully.';
            } else {
                EmailConfiguration::where('id', 1)->update($data);
                $message = 'Edit Email Configuration Successfully.';
            }
        }

        return response()->json([
            'ApiName' => 'add_EmailConfiguration',
            'status' => true,
            'message' => $message,
        ], 200);
    }

    public function emailConfigurationList(): JsonResponse
    {
        $list = EmailConfiguration::where('id', 1)->first();
        if (! $list) {
            $list = new EmailConfiguration;
        }
        $data = [
            'id' => $list->id,
            'email_from_address' => $list->email_from_address,
            'service_provider' => $list->service_provider,
            'protocal' => $list->host_mailer,
            'host_name' => $list->host_name,
            'smtp_port' => $list->smtp_port,
            'timeout' => $list->timeout,
            'security_protocol' => $list->security_protocol,
            'authentication_method' => $list->authentication_method,
            'token_app_id' => $list->token_app_id,
            'token_app_key' => $list->token_app_key,
            'mail_user_name' => $list->user_name,
            'mail_password' => $list->password,
            'created_at' => $list->created_at,
            'updated_at' => $list->updated_at,
        ];

        return response()->json([
            'ApiName' => 'List_Email_Configuration',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function emailConfigurationCheck(Request $request): JsonResponse
    {
        $reciveEmail = $request->email;
        $emailConfig = EmailConfiguration::where('status', 1)->first();
        if (! empty($emailConfig)) {
            try {
                $data['email'] = $reciveEmail;
                $data['subject'] = 'Test email configuration';
                $data['template'] = '<b>Email config test mail.</b>';
                $this->sendEmailNotification($data);
            } catch (Exception $e) {
                return response()->json([
                    'ApiName' => 'Email_Configuration_check',
                    'status' => false,
                    'message' => 'Failed.',
                ], 400);
            }

            return response()->json([
                'ApiName' => 'Email_Configuration_check',
                'status' => true,
                'message' => 'Successfully.',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Email_Configuration_check',
                'status' => false,
                'message' => 'Email settings not activated',
            ], 400);
        }
    }
}
