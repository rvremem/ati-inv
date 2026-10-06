@extends('layouts.app')

@section('title', 'Maintenance Report')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="mb-0">Maintenance Report</h2>
        {{-- Carries the filters currently applied, so the download matches
             what is on screen rather than the whole report. --}}
        <a href="{{ route('reports.export-csv', ['report' => 'maintenance'] + request()->query()) }}" class="btn btn-outline-primary">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="maintenanceReportTable" class="table no-datatable mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>PO No</th>
                        <th>Serial No</th>
                        <th>Description</th>
                        <th>Brand</th>
                        <th>Coverage</th>
                        <th>Start Date</th>
                        <th>End Date</th>
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
<script>
$(function () {
    $('#maintenanceReportTable').DataTable(reportTableOptions(
        '{{ route('reports.maintenance-data') }}',
        @json(request()->only(['customer_id'])),
        [
            { data: 'customer', orderable: false, render: esc },
            { data: 'po_no', render: esc },
            { data: 'serial_no', render: (d) => '<strong>' + esc(d) + '</strong>' },
            { data: 'description', render: esc },
            { data: 'brand', render: esc },
            { data: 'coverage', orderable: false, render: esc },
            { data: 'start_date', render: esc },
            { data: 'end_date', render: esc },
            { data: 'is_active', render: (d) => d
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>' }
        ],
        [[7, 'asc']]
    ));
});
</script>
@endsection
