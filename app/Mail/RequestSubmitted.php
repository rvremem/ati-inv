<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells approvers that a request is waiting. Sent once per submission, with
 * every approver on the same email (To/CC), and every requested item as a row
 * of one table.
 */
class RequestSubmitted extends Mailable
{
    /**
     * @param string                $type     e.g. 'Part', 'Machine', 'Machine Part'
     * @param string[]              $headers  table column headings
     * @param array<int, array>     $rows     one row per requested item, in $headers order
     * @param array<string, string> $details  request-wide fields shown above the table
     */
    public function __construct(
        public string $type,
        public User $requester,
        public array $headers,
        public array $rows,
        public array $details = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New {$this->type} Request from {$this->requester->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.request-submitted',
            with: ['url' => route('requests.pending')],
        );
    }
}
