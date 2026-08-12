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
            'username' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        try {
            // Fetch user from database
            $user = DB::table('users')
                ->where('username', $credentials['username'])
                ->first();

            // Check if user exists and password matches
            if ($user && password_verify($credentials['password'], $user->password)) {
                // Check if user is active
                if (!$user->is_active) {
                    return back()->withErrors([
                        'username' => 'Your account has been deactivated.',
                    ])->onlyInput('username');
                }

                // Create User model instance and login
                $authUser = User::find($user->id);
                Auth::login($authUser, true); // Remember me enabled
                $request->session()->regenerate();

                ActivityLog::log('Logged In', "Username: {$user->username}");

                return redirect()->intended(route('dashboard'))
                    ->with('success', 'Login successful! Welcome ' . $user->name . '!');
            }

            // User not found or password incorrect
            return back()->withErrors([
                'username' => 'Invalid username or password.',
            ])->onlyInput('username');

        } catch (\Exception $e) {
            // Database or other errors
            return back()->withErrors([
                'username' => 'An error occurred: ' . $e->getMessage(),
            ])->onlyInput('username');
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
