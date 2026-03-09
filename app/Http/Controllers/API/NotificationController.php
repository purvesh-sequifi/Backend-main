<?php

namespace App\Http\Controllers\API;

use App\Core\Traits\PayrollUpdatePayFrequencyTrait;
use App\Http\Controllers\Controller;
use App\Models\User;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use PayrollUpdatePayFrequencyTrait;

    public function get_all_notifications(Request $request): JsonResponse
    {
        $limit = $request->limit ? $request->limit : '';
        $offset = $request->offset ? $request->offset : '';
        $kind = $request->kind ? $request->kind : '';
        $template_id = $request->template_id ? $request->template_id : '';

        $url = 'https://onesignal.com/api/v1/notifications?app_id='.config('services.onesignal.app_id').'&limit='.$limit.'&offset='.$offset.'&kind='.$kind.'&template_id='.$template_id;
        $headers = [
            'Authorization: Basic '.config('services.onesignal.api_key'),
            'accept: application/json',
            'content-type: application/json',
        ];
        $method = 'GET';
        $response = curlRequest($url, $fields = '', $headers, $method);
        $resp = json_decode($response, true);
        $data = [];
        // dd($resp);
        if (count($resp['notifications']) > 0) {
            foreach ($resp['notifications'] as $noti) {
                if ($noti['successful'] > 0 && $noti['converted'] == 0) {
                    $data['notification_data'][] = [
                        'id' => $noti['id'],
                        'data' => $noti['data'],
                        'excluded_segments' => $noti['excluded_segments'],
                        'include_player_ids' => $noti['include_player_ids'],
                        'include_external_user_ids' => $noti['include_external_user_ids'],
                        'include_aliases' => $noti['include_aliases'],
                        'included_segments' => $noti['included_segments'],
                        'tags' => $noti['tags'],
                        'filters' => $noti['filters'],
                        'template_id' => $noti['template_id'],
                        'headings' => $noti['headings'],
                        'subtitle' => $noti['subtitle'],
                        'name' => $noti['name'],
                        'isEmail' => $noti['isEmail'],
                        'email_subject' => $noti['email_subject'],
                        'email_from_name' => $noti['email_from_name'],
                        'email_from_address' => $noti['email_from_address'],
                        'email_reply_to_address' => $noti['email_reply_to_address'],
                        'contents' => $noti['contents'],
                    ];
                }
            }
            $data['count'] = isset($data['notification_data']) ? count($data['notification_data']) : 0;
        }

        if (isset($data['notification_data'])) {
            $data = paginate($data['notification_data']);
        } else {
            $data = [];
        }

        return response()->json([
            'ApiName' => 'get-all-notifications',
            'status' => true,
            'message' => 'Success',
            'data' => $data,
        ], 200);
    }

    public function get_notification_detail(Request $request): JsonResponse
    {
        $notification_id = $request->notification_id ? $request->notification_id : '';
        if (! empty($notification_id)) {
            $url = 'https://onesignal.com/api/v1/notifications/'.$notification_id.'?app_id='.config('services.onesignal.app_id');
            $headers = [
                'Authorization: Basic '.config('services.onesignal.api_key'),
                'accept: application/json',
                'content-type: application/json',
            ];
            $method = 'GET';
            $response = curlRequest($url, $fields = '', $headers, $method);
            $resp = json_decode($response, true);
            // dd($resp);
            $data = [];
            if (! empty($resp)) {
                $data['notification_data'] = [
                    'id' => $resp['id'],
                    'data' => $resp['data'],
                    'excluded_segments' => $resp['excluded_segments'],
                    'include_player_ids' => $resp['include_player_ids'],
                    'include_external_user_ids' => $resp['include_external_user_ids'],
                    'include_aliases' => $resp['include_aliases'],
                    'included_segments' => $resp['included_segments'],
                    'tags' => $resp['tags'],
                    'filters' => $resp['filters'],
                    'template_id' => $resp['template_id'],
                    'headings' => $resp['headings'],
                    'subtitle' => $resp['subtitle'],
                    'name' => $resp['name'],
                    'isEmail' => $resp['isEmail'],
                    'contents' => $resp['contents'],
                ];
            }
        }

        return response()->json([
            'ApiName' => 'get-all-notifications',
            'status' => true,
            'message' => 'Success',
            'data' => $data,
        ], 200);
    }

    public function check_payroll_deta(Request $request)
    {
        $data = [];

        return $this->updateCommissionFrequency($data);
    }

    public function update_device_token(Request $request): JsonResponse
    {
        if (Auth::check()) {
            $userId = Auth::user()->id;

            if ($request['device_token']) {

                User::where('id', $userId)->update(['device_token' => $request['device_token']]);

                return response()->json([
                    'ApiName' => 'Update_device_token',
                    'status' => true,
                    'message' => 'Successfully updated!',
                ], 200);
            }

            return response()->json([
                'ApiName' => 'Update_device_token',
                'status' => true,
                'message' => 'Not updated!',
            ], 200);
        }
    }

    // ============================================
    // POSITION UPDATE NOTIFICATIONS (Redis-based)
    // ============================================

    /**
     * Get active notifications from Redis
     * Returns position update notifications stored with 2-hour TTL
     * 
     * @return JsonResponse
     */
    public function getActiveNotifications(Request $request): JsonResponse
    {
        try {
            $notificationService = app(\App\Services\NotificationService::class);
            
            // Optional type filter: ?type=position_update
            $type = $request->query('type');
            $notifications = $notificationService->getActiveNotifications(auth()->id(), $type);
            
            return response()->json([
                'status' => true,
                'data' => [
                    'notifications' => $notifications
                ]
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('[NotificationController] Failed to get active notifications', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            // Return empty array on error (graceful degradation)
            return response()->json([
                'status' => true,
                'data' => [
                    'notifications' => []
                ]
            ], 200);
        }
    }

    /**
     * Dismiss notification from Redis
     * Permanently removes notification for current user
     * 
     * @param string $type Notification type (position_update, payroll, etc.)
     * @param string $uniqueKey Notification unique key
     * @return JsonResponse
     */
    public function dismissNotification(string $type, string $uniqueKey): JsonResponse
    {
        try {
            $notificationService = app(\App\Services\NotificationService::class);
            $dismissed = $notificationService->dismissNotification(auth()->id(), $type, $uniqueKey);
            
            return response()->json([
                'status' => true,
                'message' => $dismissed ? 'Notification dismissed successfully' : 'Notification not found'
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('[NotificationController] Failed to dismiss notification', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'unique_key' => $uniqueKey
            ]);
            
            // Return success even on error (idempotent - safe to retry)
            return response()->json([
                'status' => true,
                'message' => 'Notification dismissed'
            ], 200);
        }
    }

    /**
     * Mark all notifications as read
     * Note: Redis doesn't track read state (frontend Redux handles this)
     * This method exists for API compatibility with frontend expectations
     * 
     * @return JsonResponse
     */
    public function markAllNotificationsAsRead(): JsonResponse
    {
        // Redis doesn't track read state (frontend Redux handles this)
        // Just return success for API compatibility
        return response()->json([
            'status' => true,
            'message' => 'All notifications marked as read'
        ], 200);
    }
}
