@extends('layouts.app')

@section('title', 'Stock Levels')

@section('styles')
<style>
    /* Hub picker — pills rather than a select. With this few branches every
       option is visible at once, switching is one click instead of three, and
       the hub you are looking at stays on screen while you read the table. */
    .hub-bar {
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
    }

    .hub-bar-label {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: #94a3b8;
        white-space: nowrap;
    }

    .hub-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .hub-pill {
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #475569;
        border-radius: 999px;
        padding: 7px 16px;
        font-size: 13.5px;
        font-weight: 600;
        line-height: 1.2;
        cursor: pointer;
        transition: background-color .15s, border-color .15s, color .15s, box-shadow .15s;
    }

    .hub-pill:hover {
        border-color: #c7d2fe;
        background: #f5f6ff;
        color: #4f46e5;
    }

    .hub-pill.is-active {
        background: #4f46e5;
        border-color: #4f46e5;
        color: #fff;
        box-shadow: 0 2px 8px rgba(79, 70, 229, .28);
    }

    .hub-pill:focus-visible {
        outline: 2px solid #4f46e5;
        outline-offset: 2px;
    }

    /* Header summary, only meaningful once a hub is chosen. */
    .hub-stats {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .stat-chip {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 6px 12px;
        font-size: 12.5px;
        color: #64748b;
        white-space: nowrap;
    }

    .stat-chip strong {
        color: #0f172a;
        font-size: 15px;
        margin-right: 4px;
    }

    /* Empty state replaces the table entirely — a bare grid of column headers
       above "no data" reads like something failed to load. */
    .stock-empty {
        text-align: center;
        padding: 56px 24px;
    }

    .stock-empty .icon {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: #f1f5f9;
        color: #94a3b8;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        margin-bottom: 16px;
    }

    .stock-empty h5 {
        color: #0f172a;
        font-weight: 700;
        font-size: 17px;
        margin-bottom: 6px;
    }

    .stock-empty p {
        color: #64748b;
        font-size: 13.5px;
        margin: 0 auto;
        max-width: 380px;
    }

    @media (max-width: 575.98px) {
        .hub-bar { align-items: flex-start; }
        .hub-pill { padding: 6px 13px; font-size: 13px; }
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 gap-3 flex-wrap">
        <h2 class="mb-0">Stock Levels</h2>
        <div class="hub-stats d-none" id="hubStats">
            <span class="stat-chip"><strong id="statParts">0</strong>parts</span>
            <span class="stat-chip"><strong id="statUnits">0</strong>on hand</span>
        </div>
    </div>

    <x-alert />

    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="hub-bar">
                <span class="hub-bar-label">Hub</span>
                <div class="hub-pills" role="group" aria-label="Select a hub">
                    @foreach ($hubs as $hub)
                        <button type="button"
                                class="hub-pill"
                                data-hub-id="{{ $hub->id }}"
                                data-hub-name="{{ $hub->hub_name }}"
                                aria-pressed="false">{{ $hub->hub_name }}</button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Shown until a hub is picked; the table card below is hidden meanwhile. --}}
    <div class="card" id="stockEmpty">
        <div class="card-body stock-empty">
            <div class="icon"><i class="fas fa-warehouse"></i></div>
            <h5>Select a hub to view stock</h5>
            <p>Stock is held per branch, so pick a hub above to see what it has on hand.</p>
        </div>
    </div>

    <div class="card d-none" id="stockTableCard">
        <div class="card-body">
            {{-- no-datatable keeps the layout's auto-init from claiming this table;
                 it is initialised below in server-side mode instead. --}}
            <table id="stocksTable" class="table no-datatable mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Part No</th>
                        <th>Description</th>
                        <th>Old Stock</th>
                        <th>Receiving</th>
                        <th>Return</th>
                        <th>Out</th>
                        <th>On Hand</th>
                        <th>Serial On Hand</th>
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

const num = (d) => '<div class="text-center">' + esc(d) + '</div>';

// Read-only on this screen. Stock Levels reports what is on hand; it is not a
// place to pick a unit, so every serial is rendered disabled — the select still
// opens to reveal the list, but nothing in it can be chosen or submitted.
const serialSelect = (serials) => {
    if (!serials || serials.length === 0) {
        return '<span class="text-muted">No serials</span>';
    }
    return '<select class="form-select form-select-sm no-select2 serial-list" aria-label="Serial numbers on hand">'
        + '<option value="" selected>Show Serial Number</option>'
        + serials.map(s => '<option value="' + esc(s) + '" disabled>' + esc(s) + '</option>').join('')
        + '</select>';
};

$(function () {
    let hubId = '';

    const table = $('#stocksTable').DataTable({
        serverSide: true,
        processing: true,
        // 0 tells DataTables the table starts empty and suppresses the initial
        // request, so no hub-less query is ever fired.
        deferLoading: 0,
        ajax: {
            url: '{{ route('inventory.stocks-data') }}',
            data: function (d) {
                d.hub_id = hubId;
            }
        },
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: [[0, 'asc']],
        pagingType: 'simple',
        dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rtip',
        language: {
            search: '',
            searchPlaceholder: 'Search parts...',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            processing: 'Loading...',
            emptyTable: 'No stock movement recorded for this hub yet.',
            zeroRecords: 'No parts match your search in this hub.',
            paginate: { previous: '← Prev', next: 'Next →' }
        },
        columns: [
            { data: 'part_no', render: (d) => '<strong>' + esc(d) + '</strong>' },
            { data: 'description', render: esc },
            { data: 'old_stock', orderable: false, searchable: false, render: num },
            { data: 'receiving_qty', searchable: false, render: num },
            { data: 'return_qty', searchable: false, render: num },
            { data: 'out_qty', searchable: false, render: num },
            { data: 'total_qty', searchable: false, render: (d) => '<div class="text-center"><strong>' + esc(d) + '</strong></div>' },
            { data: 'available_serials', orderable: false, searchable: false, render: serialSelect }
        ]
    });

    // The header figures come from the response, not the rows on screen, so
    // they cover the whole hub rather than the current page.
    table.on('xhr.dt', function (e, settings, json) {
        if (!json) return;
        $('#statParts').text(json.recordsTotal ?? 0);
        $('#statUnits').text(json.onHandTotal ?? 0);
    });

    $('.hub-pill').on('click', function () {
        const $pill = $(this);

        if ($pill.hasClass('is-active')) {
            return;   // already showing this hub
        }

        $('.hub-pill').removeClass('is-active').attr('aria-pressed', 'false');
        $pill.addClass('is-active').attr('aria-pressed', 'true');

        hubId = $pill.data('hub-id');

        $('#stockEmpty').addClass('d-none');
        $('#stockTableCard').removeClass('d-none');
        $('#hubStats').removeClass('d-none');

        // Back to page one: row 40 of Manila means nothing in Cebu.
        table.ajax.reload(null, true);

        // Columns were measured while the card was hidden, so widths are wrong
        // until the table is visible.
        table.columns.adjust();
    });
});
</script>
@endsection
