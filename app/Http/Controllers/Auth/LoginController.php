<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        // Use the same landing page as "/" so the login UI is consistent
        // whether a guest lands on / or is redirected to /login. Previously
        // this returned auth.login, a second, differently-styled sign-in page.
        return view('landing');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string|min:6',
        ]);

        // Registration stores the address lowercased, so the lookup matches it.
        // SQL Server's default collation is case-insensitive and would have
        // matched anyway — this does not rely on that.
        $email = strtolower(trim($credentials['email']));

        try {
            $user = DB::table('users')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();

            // Check if user exists and password matches
            if ($user && password_verify($credentials['password'], $user->password)) {
                // Check if user is active
                if (!$user->is_active) {
                    ActivityLog::logFailedLogin(
                        $email,
                        'account is deactivated',
                        $request->ip(),
                        $user->id
                    );

                    return back()->withErrors([
                        'email' => 'Your account has been deactivated.',
                    ])->onlyInput('email');
                }

                $authUser = User::find($user->id);

                // An unverified account is refused here rather than at the
                // dashboard, so the person is told what to do about it while
                // they are still on the login screen. Existing users were
                // backfilled as verified by the migration, so this only ever
                // catches accounts created through the new registration flow.
                if (! $authUser->hasVerifiedEmail()) {
                    ActivityLog::logFailedLogin(
                        $email,
                        'email not verified',
                        $request->ip(),
                        $user->id
                    );

                    // Lets the login page offer a resend without a session.
                    $request->session()->put(
                        EmailVerificationController::PENDING_KEY,
                        $authUser->id
                    );

                    return back()->withErrors([
                        'email' => 'Please verify your email address first. We sent a link to ' . $authUser->email . '.',
                    ])->onlyInput('email');
                }

                // Create User model instance and login
                Auth::login($authUser, true); // Remember me enabled
                $request->session()->regenerate();

                ActivityLog::log('Logged In', "Username: {$user->username} | Email: {$user->email}");

                return redirect()->intended(route('dashboard'))
                    ->with('success', 'Login successful! Welcome ' . $user->name . '!');
            }

            // No such address or wrong password. The message deliberately does
            // not say which — the log does, because an admin auditing it needs to
            // tell a brute-force against a real account apart from someone
            // guessing addresses, and the log is not visible to the attacker.
            ActivityLog::logFailedLogin(
                $email,
                $user ? 'incorrect password' : 'no such email',
                $request->ip(),
                $user->id ?? null
            );

            return back()->withErrors([
                'email' => 'Invalid email or password.',
            ])->onlyInput('email');

        } catch (\Exception $e) {
            // Database or other errors
            return back()->withErrors([
                'email' => 'An error occurred: ' . $e->getMessage(),
            ])->onlyInput('email');
        }
    }

    public function logout(Request $request)
    {
        ActivityLog::log('Logged Out');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('success', 'Logged out successfully!');
    }
}
