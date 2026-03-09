<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\BlockKit\Blocks\ContextBlock;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;

class HorizonJobAlert extends Notification
{
    use Queueable;

    protected $jobName;

    protected $status;

    protected $exception;

    /**
     * Create a new notification instance.
     */
    public function __construct($jobName, $status, $exception = null)
    {
        $this->jobName = $jobName;
        $this->status = $status;
        $this->exception = $exception;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        if ($this->status !== 'failed') {
            return [];
        }

        return ['slack'];
    }

    /**
     * Format the notification for Slack.
     */
    public function toSlack($notifiable)
    {

        $statusEmoji = '❌';
        $message = "*Job Name:* {$this->jobName}\n*Status:* {$this->status}";

        if ($this->exception) {
            $message .= "\n*Exception Message:* ```".$this->exception->getMessage().'```';
            $message .= "\n*Line:* `".$this->exception->getLine().'`';
            $message .= "\n*File:* `".$this->exception->getFile().'`';
        }

        $message .= "\n*Environment:* `".config('app.url').'`';

        return (new SlackMessage)
            ->headerBlock("$statusEmoji Horizon Job Alert! $statusEmoji")
            ->sectionBlock(function (SectionBlock $block) use ($message) {
                $block->text($message);
            })
            ->dividerBlock()
            ->contextBlock(function (ContextBlock $block) {
                $block->text('Sent from '.config('app.name'));
            });
    }
}
