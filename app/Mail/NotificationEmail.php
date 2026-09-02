<?php

namespace App\Mail;

use App\Models\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly UserNotification $notification,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notification->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.notification',
            with: [
                'title' => $this->notification->title,
                'body' => $this->notification->body,
            ],
        );
    }
}
