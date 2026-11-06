@extends('layouts.app')

@section('title', 'Maintenance Contract Expiry Report')

@section('styles')
<style>
    .state-card {
        background: #fff;
        padding: 0;
        cursor: pointer;
        transition: box-shadow .15s, transform .15s, background-color .15s;
    }
    .state-card:hover { box-shadow: 0 4px 14px rgba(15,23,42,0.08); transform: translateY(-1px); }
    .state-card:focus-visible { outline: 2px solid #4f46e5; outline-offset: 2px; }
    .state-card.active { border-width: 2px; box-shadow: 0 4px 14px rgba(15,23,42,0.1); }
    .state-card.active.border-danger  { background: #fef2f2; }
    .state-card.active.border-warning { background: #fffbeb; }
    .state-card.active.border-success { background: #f0fdf4; }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Maintenance Contract Expiry Report</h2>
        {{-- Exports every active contract, not the page on screen: the table
             below is paged 10 at a time. Same style as the Activity Log export. --}}
        <a href="{{ route('maintenance.report-csv') }}" id="exportCsv" class="btn btn-outline-primary"
           data-base="{{ route('maintenance.report-csv') }}">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
    </div>

    {{-- Counted in SQL across every active contract, not just the page on
         screen, so the totals stay right whatever the table is showing.
         Clicking a card filters the table (and the export) to that bucket;
         clicking it again clears the filter. --}}
    <div class="row g-3 mb-2">
        <div class="col-md-4">
            <button type="button" class="card state-card border-danger w-100 text-start" data-state="expired" aria-pressed="false">
                <div class="card-body py-3">
                    <div class="text-muted small text-uppercase">Expired</div>
                    <div class="fs-4 fw-bold text-danger">{{ number_format($expiredCount) }}</div>
                </div>
            </button>
        </div>
        <div class="col-md-4">
            <button type="button" class="card state-card border-warning w-100 text-start" data-state="soon" aria-pressed="false">
                <div class="card-body py-3">
                    <div class="text-muted small text-uppercase">Expiring within 30 days</div>
                    <div class="fs-4 fw-bold text-warning">{{ number_format($soonCount) }}</div>
                </div>
            </button>
        </div>
        <div class="col-md-4">
            <button type="button" class="card state-card border-success w-100 text-start" data-state="ok" aria-pressed="false">
                <div class="card-body py-3">
                    <div class="text-muted small text-uppercase">Not due yet</div>
                    <div class="fs-4 fw-bold text-success">{{ number_format($okCount) }}</div>
                </div>
            </button>
        </div>
    </div>
    <div class="mb-3 small text-muted" id="stateFilterNote" style="min-height:1.5em;">
        Click a card to filter the table.
    </div>

    <div class="card">
        <div class="card-body">
            <table id="expiryTable" class="table no-datatable mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Equipment</th>
                        <th>Serial No</th>
                        <th>PO No</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Days Left</th>
                        <th style="width:130px;">Status</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
    <x-back-link :href="route('maintenance.index')">Back to Contracts</x-back-link>
</div>
@endsection

@section('scripts')
<script>
const esc = s => (s ?? '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

// DataTables calls render as (data, type, row) — the second argument is the
// draw type, not the row. Both of these read the column's own value, which is
// what `data` already holds.
const daysCell = (data) => {
    if (data === null || data === undefined) return '-';
    return data < 0
        ? Math.abs(data) + ' day(s) ago'
        : data + ' day(s)';
};

const stateBadge = (data) => ({
    expired: '<span class="badge bg-danger">Expired</span>',
    soon:    '<span class="badge bg-warning text-dark">Expiring Soon</span>',
    ok:      '<span class="badge bg-success">Active</span>',
}[data] || '<span class="badge bg-secondary">—</span>');

let activeState = null;
const stateLabels = { expired: 'Expired', soon: 'Expiring within 30 days', ok: 'Not due yet' };

$(function () {
    const table = $('#expiryTable').DataTable({
        serverSide: true,
        processing: true,
        ajax: {
            url: '{{ route('maintenance.report-data') }}',
            data: (d) => { if (activeState) d.state = activeState; }
        },
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: [[5, 'asc']],
        pagingType: 'simple',
        dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rtip',
        language: {
            search: '', searchPlaceholder: 'Search...',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            processing: 'Loading...',
            emptyTable: 'No active maintenance contracts.',
            paginate: { previous: '← Prev', next: 'Next →' }
        },
        // Tints the whole row to match its status badge, as the old full-page
        // report did.
        createdRow: (tr, data) => {
            if (data.state === 'expired') tr.classList.add('table-danger');
            else if (data.state === 'soon') tr.classList.add('table-warning');
        },
        columns: [
            { data: 'client', render: (d) => '<strong>' + esc(d) + '</strong>' },
            { data: 'equipment', render: esc },
            { data: 'serial_no', render: esc },
            { data: 'po_no', render: esc },
            { data: 'start_date', className: 'text-nowrap', render: esc },
            { data: 'end_date', className: 'text-nowrap', render: esc },
            { data: 'days_left', className: 'text-nowrap', render: daysCell },
            { data: 'state', orderable: false, searchable: false, render: stateBadge }
        ]
    });

    const exportBtn = document.getElementById('exportCsv');
    const note = document.getElementById('stateFilterNote');

    $('.state-card').on('click', function () {
        const state = this.dataset.state;
        activeState = activeState === state ? null : state;

        $('.state-card').each(function () {
            const on = this.dataset.state === activeState;
            this.classList.toggle('active', on);
            this.setAttribute('aria-pressed', on ? 'true' : 'false');
        });

        exportBtn.href = exportBtn.dataset.base + (activeState ? '?state=' + activeState : '');
        note.innerHTML = activeState
            ? 'Showing <strong>' + stateLabels[activeState] + '</strong> only &middot; <a href="#" id="clearStateFilter">Show all</a>'
            : 'Click a card to filter the table.';

        table.page(0).draw(false);
    });

    $(document).on('click', '#clearStateFilter', function (e) {
        e.preventDefault();
        $('.state-card.active').trigger('click');
    });
});
</script>
@endsection
