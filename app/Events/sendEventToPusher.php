<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class sendEventToPusher implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    // Broadcast immediately, don't queue
    public $connection = 'sync';

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public $domain_name;

    public $event_name;

    public $message;

    public $otherDatas;

    public function __construct($domain_name, $event_name, $message, $otherDatas)
    {
        $this->domain_name = $domain_name;
        $this->event_name = $event_name;
        $this->message = $message;
        $this->otherDatas = $otherDatas;
    }

    public function broadcastOn(): array
    {
        return [new Channel('sequifi-'.$this->domain_name)];
    }

    public function broadcastAs()
    {
        return $this->event_name;
    }

    public function broadcastWith()
    {
        $pusherData = [
            'event' => $this->event_name,
            'message' => $this->message,
        ];

        // Merge all otherDatas (includes progress, status, session_key, etc.)
        if (!empty($this->otherDatas) && is_array($this->otherDatas)) {
            $pusherData = array_merge($pusherData, $this->otherDatas);
        }

        return $pusherData;
    }
}
