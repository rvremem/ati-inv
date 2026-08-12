@extends('layouts.app')

@section('title', 'Request Status Report')

@section('content')
<div class="container-fluid">
    <div class="mb-3">
        <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    <h2 class="mb-4">Request Status Report</h2>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body">
                    <div class="fs-1 fw-bold text-warning">{{ $pending }}</div>
                    <div class="text-muted small mt-1"><i class="fas fa-clock me-1"></i>Pending</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body">
                    <div class="fs-1 fw-bold text-success">{{ $out }}</div>
                    <div class="text-muted small mt-1"><i class="fas fa-box-open me-1"></i>Out</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="card-body">
                    <div class="fs-1 fw-bold text-secondary">{{ $partRequests->count() }}</div>
                    <div class="text-muted small mt-1"><i class="fas fa-list me-1"></i>Total Requests</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-bordered table-hover align-middle" id="requestStatusTable">
                <thead class="table-light">
                    <tr>
                        <th>Pick-Up Date</th>
                        <th>MRIS No</th>
                        <th>Customer</th>
                        <th>Part No</th>
                        <th>Description</th>
                        <th>Serial No</th>
                        <th class="text-center">Qty</th>
                        <th>Location</th>
                        <th>Requested By</th>
                        <th>Remarks</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($partRequests as $req)
                        <tr>
                            <td style="white-space:nowrap;">
                                {{ $req->pick_date ? $req->pick_date->format('M d, Y') : '-' }}
                            </td>
                            <td>{{ $req->mris_no ?? '-' }}</td>
                            <td>{{ $req->customer->name ?? '-' }}</td>
                            <td><strong>{{ $req->itemPart->part_no ?? '-' }}</strong></td>
                            <td>{{ $req->itemPart->description ?? '-' }}</td>
                            <td>{{ $req->serial_no ?? '-' }}</td>
                            <td class="text-center">{{ $req->quantity }}</td>
                            <td>{{ $req->location->code ?? '-' }}</td>
                            <td>{{ $req->requestedBy->name ?? '-' }}</td>
                            <td>{{ $req->remarks ?? '-' }}</td>
                            <td class="text-center"><x-status-badge :status="$req->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
