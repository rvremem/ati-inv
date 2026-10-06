<?php

namespace App\Notifications;

use App\Models\PasswordResetRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class VerifyPasswordReset extends Notification
{
    public function __construct(private PasswordResetRequest $reset)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm Your Password Change')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Someone asked to change the password on your ' . config('app.name') . ' account.')
            ->action('Confirm Password Change', $this->verificationUrl())
            ->line('Your password will not change until you click that button.')
            ->line('This link expires in ' . PasswordResetRequest::EXPIRES_MINUTES . ' minutes.')
            ->line('If this was not you, ignore this email — your current password still works and nothing has changed.');
    }

    /**
     * Same shape as the email-verification link: an expiring signed URL, so it
     * cannot be forged, and a hash bound to this specific pending password, so
     * a superseded request's email is inert.
     */
    private function verificationUrl(): string
    {
        return URL::temporarySignedRoute(
            'password.verify',
            Carbon::now()->addMinutes(PasswordResetRequest::EXPIRES_MINUTES),
            [
                'id' => $this->reset->id,
                'hash' => $this->reset->verificationHash(),
            ]
        );
    }
}
