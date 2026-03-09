<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ContextBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;

class SchedulerChangeAlert extends Notification
{
    use Queueable;

    protected $newSchedulers;

    protected $environment;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(array $newSchedulers, string $environment)
    {
        $this->newSchedulers = $newSchedulers;
        $this->environment = $environment;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     */
    public function via($notifiable): array
    {
        return ['slack'];
    }

    /**
     * Format the notification for Slack.
     *
     * @param  mixed  $notifiable
     */
    public function toSlack($notifiable): SlackMessage
    {
        $emoji = '🔔';
        $title = "$emoji New Scheduler Alert! $emoji";

        $message = "*Environment:* `{$this->environment}`\n";
        $message .= '*New Scheduler'.(count($this->newSchedulers) > 1 ? 's' : '')." Added:*\n";

        foreach ($this->newSchedulers as $scheduler) {
            $message .= "• `{$scheduler['command']}` - {$scheduler['frequency']}\n";
        }

        return (new SlackMessage)
            ->headerBlock($title)
            ->sectionBlock(function (SectionBlock $block) use ($message) {
                $block->text($message);
            })
            ->dividerBlock()
            ->contextBlock(function (ContextBlock $block) {
                $block->text('Sent from '.config('app.name').' at '.now()->format('Y-m-d H:i:s'));
            });
    }
}
