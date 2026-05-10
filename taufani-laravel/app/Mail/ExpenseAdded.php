<?php

namespace App\Mail;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExpenseAdded extends Mailable
{
    use Queueable, SerializesModels;

    public float $recipientShare;

    public function __construct(
        public Expense $expense,
        public User $recipient,
    ) {
        $participant = $expense->participants->firstWhere('id', $recipient->id);

        if ($participant) {
            $pivotAmount = $participant->pivot->amount;
            $this->recipientShare = $pivotAmount !== null
                ? (float) $pivotAmount
                : (float) $expense->amount / max($expense->participants->count(), 1);
        } else {
            $this->recipientShare = 0.0;
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New expense in {$this->expense->group->name}: {$this->expense->description}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.expense-added');
    }
}
