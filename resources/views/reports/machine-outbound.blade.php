@extends('layouts.app')

@section('title', 'Machine Outbound Report')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="mb-0">Machine Outbound Report</h2>
        {{-- Carries the filters currently applied, so the download matches
             what is on screen rather than the whole report. --}}
        <a href="{{ route('reports.export-csv', ['report' => 'machine-outbound'] + request()->query()) }}" class="btn btn-outline-primary">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.machine-outbound') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                </div>
                <x-hub-filter />
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="machineOutboundTable" class="table no-datatable mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Out Date</th>
                        <th>MRIS No</th>
                        <th>Serial No</th>
                        <th>Description</th>
                        <th>Model</th>
                        <th>Client</th>
                        <th>Purpose</th>
                        <th>Location</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
    <x-back-link :href="route('reports.index')" />
</div>
@endsection

@section('scripts')
@include('partials.datatable-helpers')
@php
    // Assigned first, then passed to @json as a single variable: inlining
    // request()->only([...]) with this many keys overruns Blade's
    // directive-argument matcher, which truncates the array and emits
    // unparseable PHP. Same reason as the machine parts report.
    $filters = request()->only(['from_date', 'to_date', 'customer_id', 'hub_id']);
@endphp
<script>
$(function () {
    $('#machineOutboundTable').DataTable(reportTableOptions(
        '{{ route('reports.machine-outbound-data') }}',
        @json($filters),
        [
            { data: 'out_date', render: esc },
            { data: 'mris_no', render: esc },
            { data: 'serial_no', orderable: false, render: (d) => '<strong>' + esc(d) + '</strong>' },
            { data: 'description', orderable: false, render: esc },
            { data: 'model', orderable: false, render: esc },
            { data: 'customer', orderable: false, render: esc },
            { data: 'purpose', render: esc },
            { data: 'location', orderable: false, render: esc },
            { data: 'status', render: statusBadge }
        ],
        [[0, 'desc']]
    ));
});
</script>
@endsection
