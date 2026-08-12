@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">Edit User: {{ $user->username }}</h5>
                </div>
                <div class="card-body">
                    <x-alert />

                    <form method="POST" action="{{ route('users.update', $user) }}">
                        @csrf @method('PUT')

                        <x-form-group
                            label="Username"
                            name="username"
                            type="text"
                            :value="$user->username"
                            required
                        />

                        <x-form-group
                            label="Full Name"
                            name="name"
                            type="text"
                            :value="$user->name"
                            required
                        />

                        <x-form-group
                            label="Email"
                            name="email"
                            type="email"
                            :value="$user->email"
                        />

                        <x-form-group
                            label="Password (Leave blank to keep current)"
                            name="password"
                            type="password"
                        />

                        <x-form-group
                            label="Confirm Password"
                            name="password_confirmation"
                            type="password"
                        />

                        <x-form-group
                            label="Role"
                            name="role"
                            type="select"
                            required
                        >
                            {{-- Options come from the roles table — add a role there
                                 and it appears here and passes validation. --}}
                            @foreach ($roles as $role)
                                <option value="{{ $role->code }}" @selected(old('role', $user->role) === $role->code)>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </x-form-group>

                        <x-form-group
                            label="Active"
                            name="is_active"
                            type="checkbox"
                            :value="$user->is_active"
                        />

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update
                            </button>
                            <a href="{{ route('users.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
