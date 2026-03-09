<?php

namespace App\Traits;

trait forgotPasswordTrait
{
    public function sendEmailForForgotPassword($data)
    {

        $email = $data['email'];
        $subject = $data['subject'];
        $template = $data['template'];
        $fields = [
            'app_id' => config('services.onesignal.app_id'),
            'email_subject' => $subject,
            'email_body' => "$template",
            'include_email_tokens' => [$email],
        ];

        $fields = json_encode($fields);
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://onesignal.com/api/v1/notifications',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic '.config('services.onesignal.api_key'),
                'accept: application/json',
                'content-type: application/json',
            ],
        ]);

        $response = curl_exec($curl);

        return $response;
        // $err = curl_error($curl);
        curl_close($curl);
    }
}
