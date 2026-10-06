@extends('layouts.app')

@section('title', 'Machine Parts Report')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="mb-0">Machine Parts Report</h2>
        {{-- Carries the filters currently applied, so the download matches
             what is on screen rather than the whole report. --}}
        <a href="{{ route('reports.export-csv', ['report' => 'machine-parts'] + request()->query()) }}" class="btn btn-outline-primary">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.machine-parts') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Movement</label>
                    <select name="movement" class="form-control">
                        <option value="">-- All --</option>
                        <option value="assembly" {{ request('movement') == 'assembly' ? 'selected' : '' }}>Assembly</option>
                        <option value="disassembly" {{ request('movement') == 'disassembly' ? 'selected' : '' }}>Disassembly</option>
                        <option value="pending" {{ request('movement') == 'pending' ? 'selected' : '' }}>Pending Disassembly</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                </div>
                <x-hub-filter />
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
            <p class="small text-muted mb-0 mt-3">
                The date range applies to the movement itself &mdash; when the part was fitted for
                Assembly, when it was pulled for Disassembly.
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            {{-- no-datatable keeps the layout's auto-init off; initialised below
                 in server-side mode instead. --}}
            <table id="machinePartsTable" class="table no-datatable mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Movement</th>
                        <th>Machine Serial</th>
                        <th>Part No</th>
                        <th>Description</th>
                        <th>Part Serial</th>
                        <th>Qty</th>
                        <th>Location</th>
                        <th>Handled By</th>
                        <th>Requested By</th>
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
    // Assigned first, then passed to @json as a single variable. Inlining the
    // request()->only([...]) call with this many keys overruns Blade's
    // directive-argument matcher, which silently truncates the array and emits
    // unparseable PHP — the other report views only get away with it because
    // their lists are shorter.
    $filters = request()->only(['movement', 'from_date', 'to_date', 'machine_id', 'hub_id']);
@endphp
<script>
$(function () {
    $('#machinePartsTable').DataTable(reportTableOptions(
        '{{ route('reports.machine-parts-data') }}',
        @json($filters),
        [
            { data: 'date', orderable: false, render: esc },
            // Assembly / Disassembly / Pending Disassembly, derived from status.
            { data: 'movement', render: (d) => {
                const cls = d === 'Disassembly' ? 'bg-danger'
                    : d === 'Assembly' ? 'bg-success' : 'bg-warning text-dark';
                return '<span class="badge ' + cls + '">' + esc(d) + '</span>';
            } },
            { data: 'machine_serial', orderable: false, render: (d) => '<strong>' + esc(d) + '</strong>' },
            { data: 'part_no', orderable: false, render: esc },
            { data: 'description', orderable: false, render: esc },
            { data: 'serial_no', render: esc },
            { data: 'quantity', className: 'text-center', render: esc },
            { data: 'location', orderable: false, render: esc },
            { data: 'handled_by', orderable: false, render: esc },
            { data: 'requested_by', orderable: false, render: esc }
        ],
        [[1, 'asc']]
    ));
});
</script>
@endsection
