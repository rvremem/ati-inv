@extends('layouts.app')

@section('title', 'Machine Inbound Report')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="mb-0">Machine Inbound Report</h2>
        {{-- Carries the filters currently applied, so the download matches
             what is on screen rather than the whole report. --}}
        <a href="{{ route('reports.export-csv', ['report' => 'machine-inbound'] + request()->query()) }}" class="btn btn-outline-primary">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.machine-inbound') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="">-- All --</option>
                        <option value="Receiving" {{ request('status') == 'Receiving' ? 'selected' : '' }}>Receiving</option>
                        <option value="Return" {{ request('status') == 'Return' ? 'selected' : '' }}>Return</option>
                    </select>
                </div>
                <x-hub-filter />
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            {{-- no-datatable keeps the layout's auto-init off; initialised below
                 in server-side mode instead. --}}
            <table id="machineInboundTable" class="table no-datatable mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Date Received</th>
                        <th>PO No</th>
                        <th>Serial No</th>
                        <th>Description</th>
                        <th>Model</th>
                        <th>Supplier</th>
                        <th>Location</th>
                        <th>Return Date</th>
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
    $filters = request()->only(['from_date', 'to_date', 'status', 'hub_id']);
@endphp
<script>
$(function () {
    $('#machineInboundTable').DataTable(reportTableOptions(
        '{{ route('reports.machine-inbound-data') }}',
        @json($filters),
        [
            { data: 'date_received', render: esc },
            { data: 'ati_po', render: esc },
            { data: 'serial_no', render: (d) => '<strong>' + esc(d) + '</strong>' },
            { data: 'description', render: esc },
            { data: 'model', render: esc },
            { data: 'supplier', orderable: false, render: esc },
            { data: 'location', orderable: false, render: esc },
            { data: 'return_date', render: esc },
            { data: 'status', render: statusBadge }
        ],
        [[0, 'desc']]
    ));
});
</script>
@endsection
