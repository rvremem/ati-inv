<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells approvers (Developer, Admin, SA) that a request is waiting. One email
 * per submission, with every requested item as a row of one table, rather
 * than one email per item.
 */
class NewRequestSubmitted extends Notification
{
    /**
     * @param string                $type     e.g. 'Part', 'Machine', 'Machine Part'
     * @param string[]              $headers  table column headings
     * @param array<int, array>     $rows     one row per requested item, in $headers order
     * @param array<string, string> $details  request-wide fields shown above the table
     */
    public function __construct(
        private string $type,
        private User $requester,
        private array $headers,
        private array $rows,
        private array $details = [],
    ) {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New {$this->type} Request from {$this->requester->name}")
            ->markdown('emails.request-submitted', [
                'recipient' => $notifiable,
                'type' => $this->type,
                'requester' => $this->requester,
                'details' => $this->details,
                'headers' => $this->headers,
                'rows' => $this->rows,
                'url' => route('requests.pending'),
            ]);
    }
}
