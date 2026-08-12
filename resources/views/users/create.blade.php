@extends('layouts.app')

@section('title', 'Add User')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Add New User</h5>
                </div>
                <div class="card-body">
                    <x-alert />

                    <form method="POST" action="{{ route('users.store') }}">
                        @csrf

                        <x-form-group
                            label="Username"
                            name="username"
                            type="text"
                            required
                        />

                        <x-form-group
                            label="Full Name"
                            name="name"
                            type="text"
                            required
                        />

                        <x-form-group
                            label="Email"
                            name="email"
                            type="email"
                        />

                        <x-form-group
                            label="Password"
                            name="password"
                            type="password"
                            required
                        />

                        <x-form-group
                            label="Confirm Password"
                            name="password_confirmation"
                            type="password"
                            required
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
                                <option value="{{ $role->code }}" @selected(old('role') === $role->code)>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </x-form-group>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Create
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
