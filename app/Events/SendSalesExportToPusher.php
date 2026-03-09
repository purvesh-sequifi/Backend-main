<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SendSalesExportToPusher implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $domain_name;

    public $event_name;

    public $message;

    public $report_url;

    public $pusherUniqueKey;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($domain_name, $event_name, $message, $report_url, $pusherUniqueKey)
    {
        $this->domain_name = $domain_name;
        $this->event_name = $event_name;
        $this->message = $message;
        $this->report_url = $report_url;
        $this->pusherUniqueKey = $pusherUniqueKey;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
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
            'url' => $this->report_url,
            'session_key' => $this->pusherUniqueKey,
        ];

        return $pusherData;
    }
}
