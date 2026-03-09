<?php

namespace App\Listeners;

use App\Events\UserloginNotification;
use App\Models\Notification;

class UserloginNotificationListener
{
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
    public function handle(UserloginNotification $event)
    {
        $data = Notification::create([
            'user_id' => $event->user['user_id'],
            'type' => $event->user['type'],
            'description' => $event->user['description'],
            'is_read' => $event->user['is_read'],

        ]);

        return $data;
    }
}
