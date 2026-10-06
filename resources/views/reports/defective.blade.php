@extends('layouts.app')

@section('title', 'Defective Report')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="mb-0">Defective Report</h2>
        {{-- Carries the filters currently applied, so the download matches
             what is on screen rather than the whole report. --}}
        <a href="{{ route('reports.export-csv', ['report' => 'defective'] + request()->query()) }}" class="btn btn-outline-primary">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.defective') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="defectiveTable" class="table no-datatable mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Part No</th>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Engineer</th>
                        <th>Remarks</th>
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
<script>
$(function () {
    $('#defectiveTable').DataTable(reportTableOptions(
        '{{ route('reports.defective-data') }}',
        @json(request()->only(['from_date', 'to_date'])),
        [
            { data: 'date_received', render: esc },
            { data: 'customer', orderable: false, render: esc },
            { data: 'part_no', orderable: false, render: (d) => '<strong>' + esc(d) + '</strong>' },
            { data: 'description', orderable: false, render: esc },
            { data: 'quantity', className: 'text-center', render: esc },
            { data: 'engineer', orderable: false, render: esc },
            { data: 'remarks', render: esc }
        ],
        [[0, 'desc']]
    ));
});
</script>
@endsection
