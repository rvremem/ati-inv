@extends('layouts.app')

@section('title', 'Maintenance Report')

@section('content')
<div class="container-fluid">
    <div class="mb-3">
        <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    <h2 class="mb-4">Maintenance Report</h2>

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
