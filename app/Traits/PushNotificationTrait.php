<?php

namespace App\Traits;

trait PushNotificationTrait
{
    public function sendNotification($notificationData)
    {
        $line1 = $notificationData['title'];
        $msg = $line1;

        // $device_id = "ec430c3c-6663-4bb3-bf0d-a0f5a5184c63";
        $device_id = $notificationData['device_token'];
        $content = [
            'en' => $notificationData['title'],
        ];
        $subtitle = [
            'en' => $notificationData['body'],
        ];

        $fields = [
            'app_id' => config('services.onesignal.app_id'),
            'include_player_ids' => [$device_id],
            'data' => ['foo' => 'bar', 'line1' => $notificationData['title']],
            'contents' => $content,
            'apns-push-type' => 'background',
            'apns-priority' => 5,
            'content_available' => true,
            'headings' => $subtitle,
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
        // return $response;die;

        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            //  echo "cURL Error #:" . $err;
        } else {
            // echo $response;die;
        }

    }

    public function webPushNotification($notificationData)
    {
        $external_data = $this->createUser($notificationData);
        // $external_id = $external_data['identity']['external_id'];
        $url = 'https://onesignal.com/api/v1/notifications';
        $method = 'POST';
        $fields = json_encode([
            'contents' => [
                'en' => $notificationData['body'],
            ],
            'name' => $notificationData['title'],
            'target_channel' => 'push',
            'app_id' => 'fc460012-2bcb-45d3-9f54-25ce038a8d1e', // '2576e41d-22a1-499d-b5c7-cc8e398cc68f',
            'headings' => [
                'en' => 'Welcome Onboard',
            ],
            'apns-push-type' => 'background',
            'apns-priority' => 5,
            'content_available' => true,
            'included_segments' => 'dev-onboard', // 'dev-sequifi-segment',
        ]);
        $headers = [
            'Authorization: Basic MDUyNzU1ZGMtYTA5ZS00ZWVjLWJmMmYtMzI0NDYxMWVlMTI3', // 'ZjVlMTQ1OGQtNTFkYi00NzllLTkyYjMtMGE1OGU2N2Y0NjA2' ,
            'accept: application/json',
            'content-type: application/json',
        ];

        $response = curlRequest($url, $fields, $headers, $method);
        dd($response);

    }

    public function createUser($notificationData)
    {
        $url = 'https://onesignal.com/api/v1/apps/fc460012-2bcb-45d3-9f54-25ce038a8d1e/users';
        $method = 'POST';
        $fields = json_encode([
            'properties' => [
                'tags' => [
                    'type' => 'Onboarding',
                ],
                'language' => 'en',
                'timezone_id' => 'Asia/Calcutta',
                'lat' => 90,
                'long' => 135,
                'country' => 'IN',
            ],
            'identity' => [
                'external_id' => 'onboarding'.$notificationData['user_id'],
            ],
            // 'subscriptions' => [
            //     [
            //         'type' => 'SafariPush',
            //         'token' => 'onboarding'.$notificationData['user_id'],
            //         'enabled' => true,
            //     ]
            // ]
        ]);
        $headers = [
            'Authorization: Basic MDUyNzU1ZGMtYTA5ZS00ZWVjLWJmMmYtMzI0NDYxMWVlMTI3', // ZjVlMTQ1OGQtNTFkYi00NzllLTkyYjMtMGE1OGU2N2Y0NjA2
            'accept: application/json',
            'content-type: application/json',
        ];

        $response = curlRequest($url, $fields, $headers, $method);
        $resp = json_decode($response, true);

        // $this->createSub($notificationData,$resp);
        return $resp;
    }

    public function createSub($notificationData, $resp)
    {
        $url = 'https://onesignal.com/api/v1/apps/fc460012-2bcb-45d3-9f54-25ce038a8d1e/users/by/'.$resp['identity']['external_id'].'/'.$resp['identity']['external_id'].'/subscriptions';
        $method = 'POST';
        $fields = json_encode([
            'subscription' => [

                'type' => 'SafariPush',
                'token' => 'onboarding'.$notificationData['user_id'],
                'enabled' => true,

            ],
        ]);
        $headers = [
            'Authorization: Basic MDUyNzU1ZGMtYTA5ZS00ZWVjLWJmMmYtMzI0NDYxMWVlMTI3', // ZjVlMTQ1OGQtNTFkYi00NzllLTkyYjMtMGE1OGU2N2Y0NjA2
            'accept: application/json',
            'content-type: application/json',
        ];
        $response = curlRequest($url, $fields, $headers, $method);
        // dd($response);
    }
}
