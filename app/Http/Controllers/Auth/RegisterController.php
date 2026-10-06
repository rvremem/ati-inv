<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function showRegisterForm()
    {
        $hubs = Hub::orderBy('hub_name')->get();

        return view('auth.register', compact('hubs'));
    }

    public function register(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email', 'regex:/@atiph\.net$/i', 'unique:users,email'],
            'name'     => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'hub_id'   => ['required', 'exists:hub,id'],
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
            'email.regex'      => 'Only @atiph.net email addresses are allowed.',
            'email.unique'     => 'This email is already registered.',
            'username.unique'  => 'This username is already taken.',
            'username.regex'   => 'Username may only contain letters, numbers, dots, dashes, and underscores.',
            'hub_id.required'  => 'Please select your hub.',
            'hub_id.exists'    => 'That hub is not valid.',
            'password.min'     => 'Password must be at least 8 characters.',
            'password.regex'   => 'Password must include uppercase, lowercase, a number, and a special character (@$!%*#?&^_-).',
        ]);

        // email_verified_at is left NULL: the account exists but cannot sign in
        // until the link in the mailbox is clicked. is_active stays true so the
        // "deactivated" path keeps its own distinct meaning — an admin
        // switching an account off — rather than doubling as "not yet verified".
        $user = User::create([
            'email'     => strtolower($request->email),
            'name'      => $request->name,
            'username'  => strtolower($request->username),
            'password'  => Hash::make($request->password),
            'hub_id'    => $request->hub_id,
            'role'      => 'Engineer',
            'is_active' => true,
        ]);

        $user->sendEmailVerificationNotification();

        ActivityLog::log('Registered', "Username: {$user->username} | Email: {$user->email} | Awaiting email verification");

        // Only the id, so the notice page can offer "resend" without a session.
        $request->session()->put(EmailVerificationController::PENDING_KEY, $user->id);

        return redirect()->route('verification.notice');
    }
}
