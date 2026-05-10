<?php

namespace App\Mail;

use App\Models\Group;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MonthlySummary extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $recipient,
        public Group $group,
        public array $stats,
        public string $monthLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->monthLabel} summary · {$this->group->name}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.monthly-summary');
    }
}
