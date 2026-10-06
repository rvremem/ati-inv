@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">My Profile</h5>
                </div>
                <div class="card-body">
                    <x-alert />

                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <strong>Role:</strong> {{ $user->userRole->name ?? $user->role ?? '—' }}<br>
                                <strong>Status:</strong>
                                @if ($user->is_active)
                                    <x-status-badge status="Active" />
                                @else
                                    <x-status-badge status="Inactive" />
                                @endif
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('users.profile-update') }}">
                        @csrf @method('PUT')

                        <x-form-group
                            label="Username"
                            name="username"
                            type="text"
                            :value="$user->username"
                            disabled
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

                        <hr>

                        <h6 class="mb-3">Change Password (Optional)</h6>

                        <x-form-group
                            label="Current Password"
                            name="current_password"
                            type="password"
                            help="Required if changing password"
                        />

                        <x-form-group
                            label="New Password"
                            name="password"
                            type="password"
                            help="Leave blank to keep current password"
                        />

                        <x-form-group
                            label="Confirm Password"
                            name="password_confirmation"
                            type="password"
                        />

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                            {{-- Kept beside Update Profile rather than moved to the
                                 page foot: here it reads as this form's cancel. --}}
                            <a href="{{ route('dashboard') }}" class="btn-back">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
