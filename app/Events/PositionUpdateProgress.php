<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PositionUpdateProgress implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $type;  // Notification type: 'position_update'
    public int $positionId;
    public string $positionName;
    public string $status;
    public int $progress;
    public string $message;
    public string $updatedBy;
    public int $updatedById;
    public string $initiatedAt;
    public ?string $completedAt;
    public string $uniqueKey;

    /**
     * Create a new event instance.
     *
     * @param  array  $data
     */
    public function __construct(array $data)
    {
        $this->type = 'position_update';  // Always position_update for this event
        $this->positionId = $data['positionId'];
        $this->positionName = $data['positionName'];
        $this->status = $data['status']; // 'started', 'processing', 'completed', 'failed'
        $this->progress = $data['progress']; // 0-100
        $this->message = $data['message'];
        $this->updatedBy = $data['updatedBy'];
        $this->updatedById = $data['updatedById'];
        $this->initiatedAt = $data['initiatedAt'];
        $this->completedAt = $data['completedAt'] ?? null;
        $this->uniqueKey = $data['uniqueKey'] ?? $this->positionId . '_' . time();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Follow convention: sequifi-{domain_name}
        $domainName = config('app.domain_name', 'new');
        return [
            new Channel('sequifi-' . $domainName),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'position-update';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'positionId' => $this->positionId,
            'positionName' => $this->positionName,
            'status' => $this->status,
            'progress' => $this->progress,
            'message' => $this->message,
            'updatedBy' => $this->updatedBy,
            'updatedById' => $this->updatedById,
            'initiatedAt' => $this->initiatedAt,
            'completedAt' => $this->completedAt,
            'uniqueKey' => $this->uniqueKey,
        ];
    }
}

