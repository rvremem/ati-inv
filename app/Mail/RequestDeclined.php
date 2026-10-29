<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells the requester their request was declined, with the approvers copied.
 * Same table layout as RequestSubmitted.
 */
class RequestDeclined extends Mailable
{
    /**
     * @param string                $type     e.g. 'Part', 'Machine', 'Machine Part'
     * @param User|null             $requester null for old rows that predate requested_by
     * @param string[]              $headers  table column headings
     * @param array<int, array>     $rows     one row per declined item, in $headers order
     * @param array<string, string> $details  request-wide fields shown above the table
     */
    public function __construct(
        public string $type,
        public ?User $requester,
        public User $decliner,
        public string $reason,
        public array $headers,
        public array $rows,
        public array $details = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->type} Request Declined",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.request-declined',
            with: ['url' => route('requests.my')],
        );
    }
}
