<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PusherPayrollCompleteEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn(): array
    {
        return [new Channel('Sequifi-staging')];
    }

    public function broadcastAs()
    {
        return 'Payroll';
    }

    public function broadcastWith()
    {
        return [
            'event' => $this->message['event'],
            'finalize_status' => $this->message['finalize_status'],
            'pay_period_from' => $this->message['pay_period_from'],
            'pay_period_to' => $this->message['pay_period_to'],
            'status' => $this->message['status'],
            'message' => $this->message['message'],
        ];
    }
}
