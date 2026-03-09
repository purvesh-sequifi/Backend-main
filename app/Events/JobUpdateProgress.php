<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Generic job progress event for long-running background processes.
 *
 * Broadcast channel matches PositionUpdateProgress: sequifi-{domain_name}
 * so the frontend can subscribe once per domain.
 */
class JobUpdateProgress implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $type;
    public string $job;
    public string $status; // started|processing|completed|failed
    public int $progress;  // 0-100
    public string $message;

    /** @var array<int> */
    public array $recipientUserIds;

    public ?string $initiatedAt;
    public ?string $completedAt;
    public string $uniqueKey;
    public array $meta;

    /**
     * @param  array{
     *   type: string,
     *   job: string,
     *   status: string,
     *   progress: int,
     *   message: string,
     *   recipientUserIds?: array<int>,
     *   initiatedAt?: string|null,
     *   completedAt?: string|null,
     *   uniqueKey: string,
     *   meta?: array<mixed>
     * }  $data
     */
    public function __construct(array $data)
    {
        $this->type = $data['type'];
        $this->job = $data['job'];
        $this->status = $data['status'];
        $this->progress = $data['progress'];
        $this->message = $data['message'];
        $this->recipientUserIds = array_values(array_unique($data['recipientUserIds'] ?? []));
        $this->initiatedAt = $data['initiatedAt'] ?? null;
        $this->completedAt = $data['completedAt'] ?? null;
        $this->uniqueKey = $data['uniqueKey'];
        $this->meta = $data['meta'] ?? [];
    }

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $domainName = config('app.domain_name', 'new');

        return [
            new Channel('sequifi-' . $domainName),
        ];
    }

    public function broadcastAs(): string
    {
        return 'job-update';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'job' => $this->job,
            'status' => $this->status,
            'progress' => $this->progress,
            'message' => $this->message,
            'recipientUserIds' => $this->recipientUserIds,
            'initiatedAt' => $this->initiatedAt,
            'completedAt' => $this->completedAt,
            'uniqueKey' => $this->uniqueKey,
            'meta' => $this->meta,
        ];
    }
}
