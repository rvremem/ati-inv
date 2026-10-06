<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailVerificationController extends Controller
{
    /**
     * Session key holding the id of the account that just registered.
     *
     * Registration deliberately does NOT log the user in — the whole point is
     * that the mailbox proves ownership first — so there is no auth()->user()
     * to hang "resend" off. This carries just enough to re-send the link, and
     * it is the id only: nothing here grants access on its own.
     */
    public const PENDING_KEY = 'pending_verification_user_id';

    /** "We've emailed you a link" — shown right after registering. */
    public function notice(Request $request)
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('login')
                ->with('success', 'Your email is already verified. Please log in.');
        }

        return view('auth.verify-email', ['email' => $user->email]);
    }

    /**
     * The link in the email.
     *
     * Guest-accessible on purpose: the link is opened from a mail client, very
     * often in a different browser — or a different device — from the one that
     * submitted the form, so requiring an existing session would break the flow
     * for exactly the people it is meant to serve.
     *
     * What makes that safe is the 'signed' middleware: the URL carries an
     * expiring HMAC over the id and hash, so it cannot be forged or replayed
     * after it lapses. The sha1(email) check below is the second half — it
     * invalidates every outstanding link the moment an address is changed.
     */
    public function verify(Request $request, string $id, string $hash)
    {
        $user = User::find($id);

        if (! $user || ! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return redirect()->route('login')->withErrors([
                'email' => 'That verification link is not valid. Please register again or request a new link.',
            ]);
        }

        // A deactivated account must not be signed in by a stale link.
        if (! $user->isActive()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been deactivated.',
            ]);
        }

        // The link signs someone in, so it is a credential. It is spent on
        // first use: a second click verifies nothing and grants nothing, it
        // just sends them to the login screen. Without this the link would
        // stay a working password-less way into the account for the whole
        // hour it remains valid — a real risk for a message sitting in a
        // mailbox, or pre-fetched by a link scanner.
        if ($user->hasVerifiedEmail()) {
            return redirect()->route('login')
                ->with('success', 'Your email is already verified. Please log in.');
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        Auth::login($user);
        ActivityLog::log('Verified Email', "Username: {$user->username} | Email: {$user->email}");

        $request->session()->forget(self::PENDING_KEY);
        $request->session()->regenerate();
        ActivityLog::log('Logged In', "Username: {$user->username}");

        return redirect()->route('dashboard')
            ->with('success', 'Email verified. Welcome, ' . $user->name . '!');
    }

    /** Re-send the link to the account that just registered. */
    public function resend(Request $request)
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('login')
                ->with('success', 'Your email is already verified. Please log in.');
        }

        $user->sendEmailVerificationNotification();

        return back()->with('success', 'A new verification link has been sent to ' . $user->email . '.');
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get(self::PENDING_KEY);

        return $id ? User::find($id) : null;
    }
}
