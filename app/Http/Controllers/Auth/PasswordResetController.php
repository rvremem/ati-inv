<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Notifications\VerifyPasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /** Step 1: username, new password, confirm. */
    public function showRequestForm()
    {
        return view('auth.password-reset');
    }

    /**
     * Step 2: park the new password and email a confirmation link.
     *
     * Nothing is written to the user here. This form is unauthenticated —
     * knowing a username is the only thing it asks for — so applying the
     * password now would let anyone lock a colleague out of their account.
     * The change waits in password_reset_requests until the link is clicked.
     */
    public function sendVerification(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*#?&^_\-]/',
            ],
        ], [
            'password.min'   => 'Password must be at least 8 characters.',
            'password.regex' => 'Password must include uppercase, lowercase, a number, and a special character (@$!%*#?&^_-).',
        ]);

        $user = User::where('username', strtolower(trim($validated['username'])))->first();

        // Everything below reaches the same screen whether or not the account
        // exists. Saying "no such username" would turn this form into a way to
        // discover who has an account, and it is reachable without logging in.
        if ($user && $user->email && $user->isActive()) {
            // Only the newest request should be live — see verificationHash().
            PasswordResetRequest::where('user_id', $user->id)->delete();

            $reset = PasswordResetRequest::create([
                'user_id'  => $user->id,
                'password' => Hash::make($validated['password']),
            ]);

            $user->notify(new VerifyPasswordReset($reset));

            ActivityLog::log(
                'Requested Password Reset',
                "Username: {$user->username} | Email: {$user->email} | Awaiting email confirmation"
            );
        }

        return redirect()->route('password.notice');
    }

    /** Step 3: "check your email". Deliberately says nothing about the account. */
    public function notice()
    {
        return view('auth.password-reset-notice');
    }

    /**
     * Step 4: the link. This is where the password actually changes.
     *
     * Guest-accessible for the same reason the email-verification link is:
     * it is opened from a mail client, often in another browser. The 'signed'
     * middleware carries the security — an expiring HMAC over id and hash.
     */
    public function verify(Request $request, string $id, string $hash)
    {
        $reset = PasswordResetRequest::with('user')->find($id);

        if (! $reset || ! hash_equals($hash, $reset->verificationHash())) {
            return redirect()->route('login')->withErrors([
                'email' => 'That password-change link is no longer valid. Please start again.',
            ]);
        }

        // Belt and braces: the signed URL expires on its own, but a row that
        // outlived its window must not be applied even if the URL somehow did.
        if ($reset->isExpired()) {
            $reset->delete();

            return redirect()->route('login')->withErrors([
                'email' => 'That password-change link has expired. Please start again.',
            ]);
        }

        $user = $reset->user;

        if (! $user || ! $user->isActive()) {
            $reset->delete();

            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been deactivated.',
            ]);
        }

        $user->forceFill([
            'password' => $reset->password,
            // Any "remember me" cookie still out there was issued against the
            // old password. Rotating this invalidates those sessions, so a
            // reset actually evicts whoever might already be signed in.
            'remember_token' => Str::random(60),
        ])->save();

        // Spent on first use.
        $reset->delete();

        ActivityLog::log('Reset Password', "Username: {$user->username} | Email: {$user->email}");

        // Not signed in here: making them enter the new password once proves
        // they know it, and catches a typo now rather than at the next login.
        return redirect()->route('login')
            ->with('success', 'Password changed. Please log in with your new password.');
    }
}
