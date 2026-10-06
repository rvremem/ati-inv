<?php

namespace App\Http\Controllers;

use App\Models\Hub;
use App\Models\Role;
use App\Models\User;
use App\Http\Controllers\Concerns\ScopesByHub;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use ScopesByHub;

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Forces a restricted administrator's own hub onto the record.
     *
     * The form only offers hubs they may see, but hub_id is a posted value —
     * without this, editing the request would file a user into a branch the
     * poster cannot even view. Unrestricted roles keep whatever they chose,
     * including blank.
     */
    private function clampHub(array &$validated): void
    {
        if (($allowed = $this->hubScope()) !== null) {
            $validated['hub_id'] = $allowed;
        }
    }
    public function index()
    {
        if (auth()->user()->role === 'Engineer') {
            $users = User::with('hub')->where('id', auth()->id())->get();
        } else {
            $users = User::with('hub')->get();
        }
        return view('users.index', compact('users'));
    }

    public function create()
    {
        if (auth()->user()->role === 'Engineer') {
            abort(403);
        }
        return view('users.create', [
            'roles' => Role::selectable(),
            // Only hubs this administrator may see, so a branch admin
            // cannot post a colleague into someone else's hub.
            'hubs' => $this->selectableHubs(),
        ]);
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
            // Nullable: an administrator who oversees every branch has no
            // single hub of their own, and the column is nullable to match.
            'hub_id' => 'nullable|exists:hub,id',
        ]);

        $this->clampHub($validated);

        $validated['password'] = bcrypt($validated['password']);
        User::create($validated);
        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        if (auth()->user()->role === 'Engineer' && auth()->id() !== $user->id) {
            abort(403);
        }
        return view('users.edit', [
            'user' => $user,
            'roles' => Role::selectable(),
            'hubs' => $this->selectableHubs(),
        ]);
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
            'hub_id' => 'nullable|exists:hub,id',
            'is_active' => 'boolean',
        ]);

        $this->clampHub($validated);

        // ?? null because a nullable field that is not submitted at all is
        // absent from the validated array rather than empty. The forms always
        // post the box, so this only bites a caller that does not — but it
        // threw an undefined-key error when one did.
        if ($validated['password'] ?? null) {
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

        // ?? null because a nullable field that is not submitted at all is
        // absent from the validated array rather than empty. The forms always
        // post the box, so this only bites a caller that does not — but it
        // threw an undefined-key error when one did.
        if ($validated['password'] ?? null) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        return redirect()->route('users.profile')->with('success', 'Profile updated successfully.');
    }
}
