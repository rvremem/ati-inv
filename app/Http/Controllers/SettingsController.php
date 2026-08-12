<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // Suppliers
    public function suppliers()
    {
        $suppliers = Supplier::paginate(15);
        return view('settings.suppliers.index', compact('suppliers'));
    }

    public function supplierCreate()
    {
        return view('settings.suppliers.create');
    }

    public function supplierStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|unique:suppliers',
            'address' => 'nullable',
            'contact_no' => 'nullable',
        ]);

        $supplier = Supplier::create($validated);
        ActivityLog::log('Created Supplier', "Name: {$supplier->name}");
        return redirect()->route('settings.suppliers')->with('success', 'Supplier created successfully.');
    }

    public function supplierEdit(Supplier $supplier)
    {
        return view('settings.suppliers.edit', compact('supplier'));
    }

    public function supplierUpdate(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name' => "required|unique:suppliers,name,{$supplier->id}",
            'address' => 'nullable',
            'contact_no' => 'nullable',
            'is_active' => 'boolean',
        ]);

        $supplier->update($validated);
        ActivityLog::log('Updated Supplier', "Name: {$supplier->name}");
        return redirect()->route('settings.suppliers')->with('success', 'Supplier updated successfully.');
    }

    public function supplierDelete(Supplier $supplier)
    {
        ActivityLog::log('Deleted Supplier', "Name: {$supplier->name}");
        $supplier->delete();
        return redirect()->route('settings.suppliers')->with('success', 'Supplier deleted successfully.');
    }

    // Customers
    public function customers()
    {
        $customers = Customer::paginate(15);
        return view('settings.customers.index', compact('customers'));
    }

    public function customerCreate()
    {
        return view('settings.customers.create');
    }

    public function customerStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|unique:customers',
        ]);

        $customer = Customer::create($validated);
        ActivityLog::log('Created Customer', "Name: {$customer->name}");
        return redirect()->route('settings.customers')->with('success', 'Customer created successfully.');
    }

    public function customerEdit(Customer $customer)
    {
        return view('settings.customers.edit', compact('customer'));
    }

    public function customerUpdate(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => "required|unique:customers,name,{$customer->id}",
            'is_active' => 'boolean',
        ]);

        $customer->update($validated);
        ActivityLog::log('Updated Customer', "Name: {$customer->name}");
        return redirect()->route('settings.customers')->with('success', 'Customer updated successfully.');
    }

    public function customerDelete(Customer $customer)
    {
        ActivityLog::log('Deleted Customer', "Name: {$customer->name}");
        $customer->delete();
        return redirect()->route('settings.customers')->with('success', 'Customer deleted successfully.');
    }

    // Categories
    public function categories()
    {
        $categories = Category::paginate(15);
        return view('settings.categories.index', compact('categories'));
    }

    public function categoryCreate()
    {
        return view('settings.categories.create');
    }

    public function categoryStore(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|unique:categories',
            'description' => 'required',
        ]);

        $category = Category::create($validated);
        ActivityLog::log('Created Category', "Code: {$category->code} | {$category->description}");

        $redirect = $request->input('redirect_to', route('settings.categories'));
        return redirect($redirect)->with('success', 'Category created successfully.');
    }

    public function categoryEdit(Category $category)
    {
        return view('settings.categories.edit', compact('category'));
    }

    public function categoryUpdate(Request $request, Category $category)
    {
        $validated = $request->validate([
            'code' => "required|unique:categories,code,{$category->id}",
            'description' => 'required',
            'is_active' => 'boolean',
        ]);

        $category->update($validated);
        ActivityLog::log('Updated Category', "Code: {$category->code} | {$category->description}");
        return redirect()->route('settings.categories')->with('success', 'Category updated successfully.');
    }

    public function categoryDelete(Category $category)
    {
        ActivityLog::log('Deleted Category', "Code: {$category->code}");
        $category->delete();
        return redirect()->route('settings.categories')->with('success', 'Category deleted successfully.');
    }

    // Users
    public function users()
    {
        $users = User::paginate(15);
        return view('settings.users.index', compact('users'));
    }

    public function userCreate()
    {
        return view('settings.users.create');
    }

    public function userStore(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|unique:users',
            'name' => 'required',
            'email' => 'nullable|email',
            'password' => 'required|min:6',
            'role' => ['required', Rule::in(Role::codes())],
        ]);

        $validated['password'] = bcrypt($validated['password']);
        $newUser = User::create($validated);
        ActivityLog::log('Created User', "Username: {$newUser->username} | Role: {$newUser->role}");
        return redirect()->route('settings.users')->with('success', 'User created successfully.');
    }

    public function userEdit(User $user)
    {
        return view('settings.users.edit', compact('user'));
    }

    public function userUpdate(Request $request, User $user)
    {
        $validated = $request->validate([
            'username' => "required|unique:users,username,{$user->id}",
            'name' => 'required',
            'email' => 'nullable|email',
            'password' => 'nullable|min:6',
            'role' => ['required', Rule::in(Role::codes())],
            'is_active' => 'boolean',
        ]);

        if ($validated['password']) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        ActivityLog::log('Updated User', "Username: {$user->username} | Role: {$user->role}");
        return redirect()->route('settings.users')->with('success', 'User updated successfully.');
    }

    public function userDelete(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('settings.users')->with('error', 'Cannot delete your own account.');
        }
        ActivityLog::log('Deleted User', "Username: {$user->username} | Role: {$user->role}");
        $user->delete();
        return redirect()->route('settings.users')->with('success', 'User deleted successfully.');
    }
}
