@extends('layouts.app')

@section('title', 'Parts Location')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0">Parts Location</h2>
            <!-- <small class="text-muted">Where each part is, and how much of it is on hand</small> -->
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('inventory.parts-location-batch-upload') }}" class="btn btn-outline-primary">
                <i class="fas fa-file-upload"></i> Batch Upload
            </a>
        </div>
    </div>

    <x-alert />

    <div class="card">
        <div class="card-body">
            {{-- One row per (part number, location) that still holds stock. The
                 quantity is the same on-hand figure Stock Levels shows for the
                 part, split across the locations it sits in. --}}
            <table id="partsLocationTable" class="table no-datatable mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>Part No</th>
                        <th>Description</th>
                        <th>Location</th>
                        <th class="text-end" style="width:100px;">On Hand</th>
                        <th style="width:90px;">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Move Modal -->
<div class="modal fade" id="moveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="moveModalTitle">Change Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('inventory.parts-location-move') }}">
                @csrf
                <input type="hidden" name="part_no" id="movePartNo">
                <input type="hidden" name="from_location" id="moveFromLocation">
                <div class="modal-body">
                    {{-- Same field stack as the Item Parts edit modal: x-form-group
                         throughout, so the two dialogs read identically. --}}
                    <x-form-group
                        label="Part Number"
                        name="display_part_no"
                        type="text"
                    />

                    <x-form-group
                        label="Current Location"
                        name="display_current_location"
                        type="text"
                    />

                    <x-form-group
                        label="Quantity to Move"
                        name="display_quantity"
                        type="text"
                        help="The whole on-hand quantity moves. Units already issued stay where they are — only available stock moves."
                    />

                    <x-form-group
                        label="New Location"
                        name="to_location"
                        type="select"
                        required
                    >
                        <option value="">-- Select Location --</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->code }}">{{ $location->code }} — {{ $location->description }}</option>
                        @endforeach
                    </x-form-group>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const esc = s => (s ?? '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

$(function () {
    $('#partsLocationTable').DataTable({
        serverSide: true,
        processing: true,
        ajax: '{{ route('inventory.parts-location-data') }}',
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        order: [[0, 'asc']],
        pagingType: 'simple',
        dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rtip',
        language: {
            search: '', searchPlaceholder: 'Search...',
            lengthMenu: 'Show _MENU_ entries',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            processing: 'Loading...',
            paginate: { previous: '← Prev', next: 'Next →' }
        },
        columns: [
            { data: 'part_no', render: (d) => '<strong>' + esc(d) + '</strong>' },
            { data: 'description', render: (d) => esc(d) || '<span class="text-muted">—</span>' },
            { data: null, render: (data, type, row) =>
                '<span class="badge bg-secondary">' + esc(row.location) + '</span>'
                + (row.location_desc
                    ? '<small class="text-muted ms-1">' + esc(row.location_desc) + '</small>'
                    // Legacy receiving rows reference location codes that were
                    // never added to the Locations list — flag them so they can
                    // be moved somewhere real.
                    : '<small class="text-warning ms-1" title="Not in the Locations list">unregistered</small>')
            },
            { data: 'on_hand', className: 'text-end', render: (d) => '<strong>' + Number(d).toLocaleString() + '</strong>' },
            { data: null, orderable: false, searchable: false, render: (data, type, row) =>
                '<div class="btn-group btn-group-sm" role="group">'
                + '<button type="button" class="btn btn-warning move-btn"'
                + ' data-bs-toggle="modal" data-bs-target="#moveModal"'
                + ' data-part=\'' + esc(JSON.stringify(row)) + '\''
                + ' title="Change location"><i class="fas fa-edit"></i></button>'
                + '</div>'
            }
        ]
    });

    // x-form-group gives each field an id matching its name, so the display
    // fields are addressed as display_*. They are disabled rather than readonly
    // so the browser leaves them out of the submission entirely — only part_no,
    // from_location (both hidden) and to_location are posted.
    $('#partsLocationTable tbody').on('click', '.move-btn', function () {
        const row = JSON.parse(this.dataset.part);

        document.getElementById('movePartNo').value = row.part_no;
        document.getElementById('moveFromLocation').value = row.location;

        setDisplay('display_part_no', row.part_no + (row.description ? ' — ' + row.description : ''));
        setDisplay('display_current_location', row.location + (row.location_desc ? ' — ' + row.location_desc : ''));
        setDisplay('display_quantity', row.on_hand);

        document.getElementById('moveModalTitle').textContent = 'Change Location: ' + row.part_no;
        $('#to_location').val('').trigger('change');
    });

    // The layout inits Select2 without a dropdownParent, so its search box
    // isn't typeable inside a modal (Bootstrap's focus trap steals focus).
    const $to = $('#to_location');
    if ($to.hasClass('select2-hidden-accessible')) {
        $to.select2('destroy');
    }
    $to.select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $('#moveModal'),
        placeholder: '-- Select Location --',
        allowClear: true
    });
});

function setDisplay(id, value) {
    const el = document.getElementById(id);
    el.value = value;
    el.disabled = true;
}
</script>
@endsection
