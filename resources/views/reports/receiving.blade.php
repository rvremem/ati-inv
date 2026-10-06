@extends('layouts.app')

@section('title', 'Receiving Report')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="mb-0">Receiving Report</h2>
        {{-- Carries the filters currently applied, so the download matches
             what is on screen rather than the whole report. --}}
        <a href="{{ route('reports.export-csv', ['report' => 'receiving'] + request()->query()) }}" class="btn btn-outline-primary">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.receiving') }}" class="row g-3">
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
            <table id="receivingTable" class="table no-datatable mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>PO No</th>
                        <th>Part No</th>
                        <th>Description</th>
                        <th>Serial No</th>
                        <th>Quantity</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Warranty</th>
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
    $('#receivingTable').DataTable(reportTableOptions(
        '{{ route('reports.receiving-data') }}',
        @json($filters),
        [
            { data: 'date_received' , render: esc },
            { data: 'ati_po', render: esc },
            { data: 'part_no', orderable: false, render: (d) => '<strong>' + esc(d) + '</strong>' },
            { data: 'description', orderable: false, render: esc },
            { data: 'serial_no', render: esc },
            { data: 'quantity', className: 'text-center', render: esc },
            { data: 'location', orderable: false, render: esc },
            { data: 'status', render: statusBadge },
            // Expiry sits under the badge; it is its own column in the CSV.
            { data: 'warranty', orderable: false, render: (d, type, row) => statusBadge(d)
                + (row.warranty_expiry !== '-' ? '<div class="small text-muted mt-1">' + esc(row.warranty_expiry) + '</div>' : '') }
        ],
        [[0, 'desc']]
    ));
});
</script>
@endsection
