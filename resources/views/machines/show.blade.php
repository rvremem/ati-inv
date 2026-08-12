@extends('layouts.app')

@section('title', 'View Machine')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('machines.index') }}" class="btn btn-outline-secondary btn-sm mb-3">
                <i class="fas fa-arrow-left"></i> Back to Machines
            </a>
            <h2 class="mb-1">{{ $machine->serial_no }}</h2>
            <p class="text-muted mb-0">Machine Details & Configuration</p>
        </div>
        <div>
            @if($machine->status === 'Receiving')
                <span class="badge bg-success p-2" style="font-size: 0.9rem;">
                    <i class="fas fa-check-circle"></i> Receiving
                </span>
            @else
                <x-status-badge :status="$machine->status" />
            @endif
        </div>
    </div>

    <div class="row">
        <!-- Machine Details Card -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-light border-bottom">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle text-primary"></i> Machine Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <p class="text-muted small mb-1">Serial Number</p>
                            <p class="h6 mb-0">{{ $machine->serial_no }}</p>
                        </div>
                        <div class="col-sm-6">
                            <p class="text-muted small mb-1">Date Received</p>
                            <p class="h6 mb-0">{{ $machine->created_at->format('M d, Y') }}</p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <p class="text-muted small mb-1">Category</p>
                            <p class="h6 mb-0">
                                <span class="badge bg-light text-dark">{{ $machine->category->code ?? '-' }}</span>
                            </p>
                        </div>
                        <div class="col-sm-6">
                            <p class="text-muted small mb-1">Model</p>
                            <p class="h6 mb-0">{{ $machine->model ?? 'Not specified' }}</p>
                        </div>
                    </div>

                    <div class="mb-3">
                        <p class="text-muted small mb-1">Description</p>
                        <p class="h6 mb-0">{{ $machine->description }}</p>
                    </div>

                    <hr>

                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <p class="text-muted small mb-1">Customer</p>
                            <p class="h6 mb-0">{{ $machine->customer->name ?? '-' }}</p>
                        </div>
                        <div class="col-sm-6">
                            <p class="text-muted small mb-1">Supplier</p>
                            <p class="h6 mb-0">{{ $machine->supplier->name ?? '-' }}</p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <p class="text-muted small mb-1">Location</p>
                            <p class="h6 mb-0">
                                <span class="badge bg-info">{{ $machine->location->code ?? '-' }}</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Machine Parts Card -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-light border-bottom">
                    <h5 class="mb-0">
                        <i class="fas fa-cubes text-primary"></i>
                        Machine Parts
                        <span class="badge bg-primary rounded-pill float-end">{{ $machine->parts->count() }}</span>
                    </h5>
                </div>
                <div class="card-body">
                    @if($machine->parts->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="font-weight: 600;">Part Number</th>
                                        <th style="font-weight: 600;">Description</th>
                                        <th style="font-weight: 600;">Serial No</th>
                                        <th class="text-center" style="font-weight: 600;">Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($machine->parts as $machinePart)
                                        <tr>
                                            <td>
                                                <span class="badge bg-light text-dark">{{ $machinePart->itemPart->part_no }}</span>
                                            </td>
                                            <td>
                                                <small class="text-muted">{{ $machinePart->itemPart->description }}</small>
                                            </td>
                                            <td>
                                                <small>{{ $machinePart->serial_no ?? '-' }}</small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-success">{{ $machinePart->quantity }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="fas fa-inbox text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2 mb-0">No parts added yet</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .card {
        border-radius: 8px;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12) !important;
    }

    .badge {
        font-weight: 500;
        letter-spacing: 0.3px;
    }

    .table-hover tbody tr:hover {
        background-color: rgba(0, 123, 255, 0.05);
    }
</style>
@endsection
