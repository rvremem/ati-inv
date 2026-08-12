@extends('layouts.app')

@section('title', 'Machines')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Machines</h2>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#categoryModal">
                <i class="fas fa-plus"></i> Add Category
            </button>
            <a href="{{ route('machines.batch-upload') }}" class="btn btn-outline-primary">
                <i class="fas fa-upload"></i> Batch Upload
            </a>
            <a href="{{ route('machines.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Machine
            </a>
        </div>
    </div>

    <x-alert />

    <div class="card">
        <div class="card-body">
            <x-data-table :headers="['Serial No', 'Description', 'Category', 'Customer', 'Location', 'Status']" :rows="$machines">
                @foreach ($machines as $machine)
                    <tr>
                        <td><strong>{{ $machine->serial_no }}</strong></td>
                        <td>{{ $machine->description }}</td>
                        <td>{{ $machine->category->code ?? '-' }}</td>
                        <td>{{ $machine->customer->name ?? '-' }}</td>
                        <td>{{ $machine->location->code ?? '-' }}</td>
                        <td>
                            {{-- machines.status is the single source of truth now. --}}
                            @if (in_array($machine->status, \App\Http\Controllers\RequestController::MACHINE_AVAILABLE_STATUSES))
                                <span class="badge bg-success">Available</span>
                            @else
                                <x-status-badge :status="$machine->status" />
                            @endif
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="{{ route('machines.show', $machine) }}" class="btn btn-info">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <form method="POST" action="{{ route('machines.destroy', $machine) }}" style="display:inline;">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    </div>
</div>
<!-- Add Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('settings.category-store') }}">
                @csrf
                <input type="hidden" name="redirect_to" value="{{ route('machines.index') }}">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" placeholder="e.g. SERVER" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                        <input type="text" name="description" class="form-control" placeholder="e.g. Server Equipment" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
