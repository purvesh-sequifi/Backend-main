<?php

namespace App\Listners;

use App\Core\Traits\EvereeTrait;
use App\Events\EvereeOnboardingUserEvent;
use App\Models\Locations;
use App\Models\State;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class EvereeOnboardingUserListener
{
    use EvereeTrait;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(EvereeOnboardingUserEvent $event)
    {
        Log::debug('EvereeOnboardingUserEvent\'s EvereeOnboardingUserListener');
        $ids = [];
        $msg = '';
        $checkObject = 0;

        if ($event->payroll == 0 && count($event->paydata) == 1 && ! array_key_exists('user_id', $event->paydata[0])) {

            $userdata = $event->paydata;
            $userdata = json_decode(json_encode($userdata), true);
            $checkObject = 1;

        } else {
            foreach ($event->paydata as $event->paydata) {
                $ids[] = $event->paydata['user_id'];
            }
            $userdata = User::whereIn('id', $ids)->where('everee_workerId', null)->where('employee_id', '!=', null)->where('dismiss', 0)->get();
        }
        foreach ($userdata as $user) {
            if ($user['id'] == 1) {
                continue;
            }

            if ($checkObject == 1) {
                $user = (object) $user;
            }

            $location_data = Locations::where('id', $user->office_id)->first();
            if (! empty($location_data)) {
                $loc = $location_data->everee_location_id;
                if ($loc == null) {
                    $l_res = $this->add_location($location_data);
                    $location_data = Locations::where('id', $user->office_id)->first();
                    $loc = $location_data->everee_location_id;
                } else {
                    $loc = $location_data->everee_location_id;
                }
            }
            if ($user->everee_workerId == null) {

                $update = $this->update_everee_id($user->employee_id);

                if (! empty($update['workerId'])) {
                    User::where('id', $user->id)->where('employee_id', $user->employee_id)->update(['everee_workerId' => $update['workerId'], 'everee_json_response' => $update]);
                } else {
                    if (isset($loc) && $loc != '') {
                        $get_loc = $this->check_location($loc);
                        if (isset($get_loc['errorCode']) && $get_loc['errorCode'] == 404) {
                            $l_res = $this->add_location($location_data);
                            $location_data = Locations::where('id', $user->office_id)->first();
                            $loc = $location_data->everee_location_id;
                        }
                        $state = State::where('id', $user->state_id)->first();
                        $this->update_emp_personal_info($user, $state);
                    } else {
                        $msg = 'location not added in SequiPay!';
                    }
                }
                $user = User::where('id', $user->id)->first();
            }

        }

        return $msg;
    }

    public function update_everee_id($externalid)
    {

        $user = User::where('employee_id', $externalid)->first();
        $worker_type = isset($user->worker_type) ? $user->worker_type : '1099';
        $token = $this->gettoken($worker_type);
        $this->api_token = $token->password;
        $this->company_id = $token->username;
        $url = 'https://api-prod.everee.com/api/v2/workers/external/'.$externalid;
        // $url = "https://api-prod.everee.com/api/v2/workers/1ad5d8e2-23ae-4a86-b1fb-49b16b2e5433";
        $method = 'GET';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'content-type: application/json',
            'x-everee-tenant-id: '.$this->company_id,
        ];
        $response = curlRequest($url, $fields = '', $headers, $method);
        $resp = json_decode($response, true);

        return $resp;
    }

    public function check_location($loc)
    {
        $token = $this->gettoken();
        $this->api_token = $token->password;
        $this->company_id = $token->username;
        $url = 'https://api-prod.everee.com/api/v2/work-locations/'.$loc;
        $method = 'GET';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'content-type: application/json',
            'x-everee-tenant-id: '.$this->company_id,
        ];
        $response = curlRequest($url, $fields = '', $headers, $method);
        $resp = json_decode($response, true);

        return $resp;
    }
}
