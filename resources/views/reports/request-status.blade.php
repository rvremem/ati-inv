@extends('layouts.app')

@section('title', 'Request Status Report')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="mb-0">Request Status Report</h2>
        {{-- Carries the filters currently applied, so the download matches
             what is on screen rather than the whole report. --}}
        <a href="{{ route('reports.request-status-csv', request()->only('hub_id')) }}" class="btn btn-outline-primary">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
    </div>

    {{-- This report had no filter form. The card is rendered only when there is
         a hub filter to put in it, so a single-hub user is not shown an empty
         toolbar. --}}
    @if (auth()->user()?->hubScope() === null)
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('reports.request-status') }}" class="row g-3">
                    <x-hub-filter />
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

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
    <x-back-link :href="route('reports.index')" />
</div>
@endsection
