<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function showRegisterForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email', 'regex:/@atiph\.net$/i', 'unique:users,email'],
            'name'     => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username', 'regex:/^[a-zA-Z0-9._-]+$/'],
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
            'password.min'     => 'Password must be at least 8 characters.',
            'password.regex'   => 'Password must include uppercase, lowercase, a number, and a special character (@$!%*#?&^_-).',
        ]);

        User::create([
            'email'     => strtolower($request->email),
            'name'      => $request->name,
            'username'  => strtolower($request->username),
            'password'  => Hash::make($request->password),
            'role'      => 'Engineer',
            'is_active' => true,
        ]);

        return redirect()->route('login')
            ->with('success', 'Account created! You can now log in.');
    }
}
