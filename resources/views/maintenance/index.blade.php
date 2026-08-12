@extends('layouts.app')

@section('title', 'Maintenance Contracts')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Maintenance Contracts</h2>
        {{-- $canManage comes from MaintenanceController::canManage(). Roles without
             it get the list read-only; the routes behind these buttons 403 anyway. --}}
        @if ($canManage)
            <div class="btn-group">
                <a href="{{ route('maintenance.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Contract
                </a>
                <a href="{{ route('maintenance.batch-upload') }}" class="btn btn-info">
                    <i class="fas fa-upload"></i> Batch Upload
                </a>
                <a href="{{ route('maintenance.report') }}" class="btn btn-secondary">
                    <i class="fas fa-file-alt"></i> Expiry Report
                </a>
            </div>
        @endif
    </div>

    <x-alert />

    <div class="card">
        <div class="card-body">
            {{-- no-datatable keeps the layout's auto-init from claiming this table;
                 it is initialised below in server-side mode instead. --}}
            <table id="contractsTable" class="table no-datatable mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Equipment</th>
                        <th>Serial No</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        @if ($canManage)<th style="width:150px;">Actions</th>@endif
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const esc = s => (s ?? '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

const statusBadge = (s) => {
    if (s === 'expired') return '<span class="badge bg-danger">Expired</span>';
    if (s === 'active')  return '<span class="badge bg-success">Active</span>';
    return '<span class="badge bg-secondary">Inactive</span>';
};

$(function () {
    $('#contractsTable').DataTable({
        serverSide: true,
        processing: true,
        ajax: '{{ route('maintenance.data') }}',
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: [[4, 'asc']],
        pagingType: 'simple',
        dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rtip',
        language: {
            search: '',
            searchPlaceholder: 'Search...',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            processing: 'Loading...',
            paginate: { previous: '← Prev', next: 'Next →' }
        },
        columns: [
            { data: 'client', render: (d) => '<strong>' + esc(d) + '</strong>' },
            { data: 'equipment_type', render: esc },
            { data: 'serial_no', render: esc },
            { data: 'start_date', render: esc },
            { data: 'end_date', render: esc },
            { data: 'status', orderable: false, searchable: false, render: statusBadge },
            @if ($canManage)
            { data: null, orderable: false, searchable: false, render: (data, type, row) =>
                '<div class="btn-group btn-group-sm" role="group">'
                + '<a href="' + row.edit_url + '" class="btn btn-warning"><i class="fas fa-edit"></i></a>'
                + '</div>'
            }
            @endif
        ]
    });
});
</script>
@endsection
