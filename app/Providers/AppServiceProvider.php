<?php

namespace App\Providers;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        $this->prefixMailSubjects();
    }

    /**
     * Stamp the app name onto every outgoing subject line.
     *
     * This mailbox receives mail from more than one system, and they share a
     * sending Gmail account — so the From address is identical and cannot tell
     * them apart. Laravel's stock subjects ("Verify Email Address") are
     * generic enough to collide too. The prefix gives an inbox rule something
     * unambiguous to match on.
     *
     * Applied here at MessageSending rather than on the notification, so any
     * mail added later (password resets, alerts) is covered without anyone
     * having to remember this.
     */
    private function prefixMailSubjects(): void
    {
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $message = $event->message;
            $subject = (string) $message->getSubject();
            $prefix = '[' . config('app.name') . '] ';

            // Guarded so a resend, or a subject already written with the
            // prefix, cannot end up with it twice.
            if ($subject === '' || str_starts_with($subject, $prefix)) {
                return;
            }

            $message->subject($prefix . $subject);
        });
    }
}