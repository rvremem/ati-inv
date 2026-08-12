<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        if (auth()->user()->role === 'Engineer') {
            $users = User::where('id', auth()->id())->get();
        } else {
            $users = User::get();
        }
        return view('users.index', compact('users'));
    }

    public function create()
    {
        if (auth()->user()->role === 'Engineer') {
            abort(403);
        }
        return view('users.create', ['roles' => Role::selectable()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|unique:users',
            'name' => 'required',
            'email' => 'nullable|email|unique:users',
            'password' => 'required|min:6|confirmed',
            // Valid roles come from the roles table, so adding one there is enough.
            'role' => ['required', Rule::in(Role::codes())],
        ]);

        $validated['password'] = bcrypt($validated['password']);
        User::create($validated);
        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        if (auth()->user()->role === 'Engineer' && auth()->id() !== $user->id) {
            abort(403);
        }
        return view('users.edit', ['user' => $user, 'roles' => Role::selectable()]);
    }

    public function update(Request $request, User $user)
    {
        if (auth()->user()->role === 'Engineer' && auth()->id() !== $user->id) {
            abort(403);
        }
        $validated = $request->validate([
            'username' => "required|unique:users,username,{$user->id}",
            'name' => 'required',
            'email' => "nullable|email|unique:users,email,{$user->id}",
            'password' => 'nullable|min:6|confirmed',
            // Valid roles come from the roles table, so adding one there is enough.
            'role' => ['required', Rule::in(Role::codes())],
            'is_active' => 'boolean',
        ]);

        if ($validated['password']) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        if (auth()->user()->role === 'Engineer') {
            abort(403);
        }
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')->with('error', 'Cannot delete your own account.');
        }
        $user->delete();
        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    public function profile()
    {
        return view('users.profile', ['user' => auth()->user()]);
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        $validated = $request->validate([
            'name' => 'required',
            'email' => "nullable|email|unique:users,email,{$user->id}",
            'current_password' => 'required_if:password,!=null',
            'password' => 'nullable|min:6|confirmed',
        ]);

        if ($validated['password'] && !password_verify($validated['current_password'], $user->password)) {
            return back()->with('error', 'Current password is incorrect.');
        }

        if ($validated['password']) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        return redirect()->route('users.profile')->with('success', 'Profile updated successfully.');
    }
}
