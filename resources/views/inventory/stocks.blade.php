@extends('layouts.app')

@section('title', 'Stock Levels')

@section('content')
<div class="container-fluid">
    <h2 class="mb-4">Stock Levels</h2>

    <x-alert />

    <div class="card">
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

const serialSelect = (serials) => {
    if (!serials || serials.length === 0) {
        return '<span class="text-muted">No serials</span>';
    }
    return '<select class="form-select form-select-sm no-select2">'
        + '<option value="">-- Select Serial --</option>'
        + serials.map(s => '<option value="' + esc(s) + '">' + esc(s) + '</option>').join('')
        + '</select>';
};

$(function () {
    $('#stocksTable').DataTable({
        serverSide: true,
        processing: true,
        ajax: '{{ route('inventory.stocks-data') }}',
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: [[0, 'asc']],
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
});
</script>
@endsection
