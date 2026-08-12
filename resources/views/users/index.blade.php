@extends('layouts.app')

@section('title', 'Users')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Users</h2>
        <a href="{{ route('users.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add User
        </a>
    </div>

    <x-alert />

    <div class="card">
        <div class="card-body">
            <x-data-table :headers="['Username', 'Name', 'Email', 'Role', 'Status']" :rows="$users">
                @foreach ($users as $user)
                    <tr>
                        <td><strong>{{ $user->username }}</strong></td>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email ?? '-' }}</td>
                        <td>
                            <span class="badge bg-primary">{{ $user->userRole->name ?? $user->role ?? '—' }}</span>
                        </td>
                        <td>
                            @if ($user->is_active)
                                <x-status-badge status="Active" />
                            @else
                                <x-status-badge status="Inactive" />
                            @endif
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="{{ route('users.edit', $user) }}" class="btn btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @if (auth()->id() !== $user->id)
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    </div>
</div>
@endsection
