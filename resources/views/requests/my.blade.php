@extends('layouts.app')

@section('title', 'My Requests')

@section('content')
<div class="container-fluid">
    <h2 class="mb-4">My Requests</h2>

    <x-alert />

    {{-- no-datatable on all three: the layout auto-inits DataTables on every
         table.table, which would put three separate search boxes on one page. --}}

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-hand-holding me-2"></i>Requested Parts</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped no-datatable mb-0">
                    <thead>
                        <tr>
                            <th>Part Number</th>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Serial</th>
                            <th>Client</th>
                            <th>Pick-Up Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($partRequests as $req)
                            <tr>
                                <td><strong>{{ $req->itemPart->part_no ?? '-' }}</strong></td>
                                <td>{{ $req->itemPart->description ?? '—' }}</td>
                                <td>{{ $req->quantity }}</td>
                                <td>{{ $req->serial_no ?? '-' }}</td>
                                <td>{{ $req->customer->name ?? '—' }}</td>
                                <td>{{ $req->pick_date ? $req->pick_date->format('M d, Y') : '-' }}</td>
                                <td><x-status-badge :status="$req->status" /></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">You have no part requests.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-dolly me-2"></i>Requested Machines</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped no-datatable mb-0">
                    <thead>
                        <tr>
                            <th>Machine Serial</th>
                            <th>Description</th>
                            <th>Client</th>
                            <th>Pick-Up Date</th>
                            <th>Purpose</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($machineRequests as $req)
                            <tr>
                                <td><strong>{{ $req->machine->serial_no ?? '-' }}</strong></td>
                                <td>{{ $req->description ?? $req->machine->description ?? '—' }}</td>
                                <td>{{ $req->customer->name ?? '—' }}</td>
                                <td>{{ $req->pick_date ? $req->pick_date->format('M d, Y') : '-' }}</td>
                                <td>{{ $req->purpose ?? '-' }}</td>
                                <td><x-status-badge :status="$req->status" /></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">You have no machine requests.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Requested Machine Parts</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped no-datatable mb-0">
                    <thead>
                        <tr>
                            <th>Machine Serial</th>
                            <th>Part Number</th>
                            <th>Description</th>
                            <th>Serial No</th>
                            <th>Quantity</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($machinePartRequests as $mp)
                            <tr>
                                <td><strong>{{ $mp->machine->serial_no ?? '-' }}</strong></td>
                                <td>{{ $mp->itemPart->part_no ?? '-' }}</td>
                                <td>{{ $mp->itemPart->description ?? '-' }}</td>
                                <td>{{ $mp->serial_no ?? '-' }}</td>
                                <td>{{ $mp->quantity }}</td>
                                <td><x-status-badge :status="$mp->status" /></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">You have no machine part requests.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
