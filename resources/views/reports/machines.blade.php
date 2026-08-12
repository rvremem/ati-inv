@extends('layouts.app')

@section('title', 'Machines Report')

@section('content')
<div class="container-fluid">
    <div class="mb-3">
        <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    <h2 class="mb-4">Machines Report</h2>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.machines') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">As of Date</label>
                    <input type="date" name="as_of_date" class="form-control" value="{{ request('as_of_date') }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table id="machinesReportTable" class="table no-datatable mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Date Received</th>
                        <th>Serial No</th>
                        <th>Description</th>
                        <th>Model</th>
                        <th>Category</th>
                        <th>Customer</th>
                        <th>Location</th>
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
    $('#machinesReportTable').DataTable(reportTableOptions(
        '{{ route('reports.machines-data') }}',
        @json(request()->only(['as_of_date'])),
        [
            { data: 'date_received', render: esc },
            { data: 'serial_no', render: (d) => '<strong>' + esc(d) + '</strong>' },
            { data: 'description', render: esc },
            { data: 'model', render: esc },
            { data: 'category', orderable: false, render: esc },
            { data: 'customer', orderable: false, render: esc },
            { data: 'location', orderable: false, render: esc },
            { data: 'status', render: statusBadge }
        ],
        [[0, 'desc']]
    ));
});
</script>
@endsection
