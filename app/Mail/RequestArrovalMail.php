<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RequestArrovalMail extends Mailable
{
    use Queueable, SerializesModels;

    // public $data;
    public $mailData;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    // public function __construct($mailData)
    // {

    //     $this->$mailData= $mailData;
    //     // return $this->mailData;
    // }

    public function __construct($mailData)
    {
        $this->mailData = $mailData;
    }
    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    // public function envelope()
    // {
    //     return new Envelope(
    //         subject: 'Request Arroval Mail',
    //     );
    // }

    /**
     * Get the message content definition.
     */
    // public function content()
    // {
    //     return new Content(
    //         view: 'view.name',
    //     );
    // }
    public function build(): Content
    {
        //
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
