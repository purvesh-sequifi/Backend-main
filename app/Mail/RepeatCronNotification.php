<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RepeatCronNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $message;

    public function __construct($message)
    {
        $this->message = $message;
        // dd($this->message);
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    // public function envelope()
    // {
    //     return new Envelope(
    //         subject: 'Repeat Cron Notification',
    //     );
    // }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    // public function content()
    // {
    //     return new Content(
    //         view: 'mail.repeat_cron_notification',
    //     );
    // }

    /**
     * Get the attachments for the message.
     */
    // public function attachments()
    // {
    //     return [];
    // }

    public function build(): array
    {
        // dd($this->message);
        $data = $this->subject('Cron Job Notification')
            ->view('mail.repeat_cron_notification')
            ->with([
                'messageContent' => $this->message,
            ]);

        return $data;
        // echo '<pre>'; print_r($data);die;
    }
}
