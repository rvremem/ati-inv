@extends('layouts.app')

@section('title', 'Locations')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Storage Locations</h2>
        <div class="d-flex gap-2">
            <a href="{{ route('inventory.edit-parts-location') }}" class="btn btn-outline-secondary">
                <i class="fas fa-map-marker-alt"></i> Edit Parts Location
            </a>
            <a href="{{ route('inventory.location-batch-upload') }}" class="btn btn-outline-primary">
                <i class="fas fa-file-upload"></i> Batch Upload
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#locationModal" onclick="resetLocationForm()">
                <i class="fas fa-plus"></i> Add Location
            </button>
        </div>
    </div>

    <x-alert />

    <div class="card">
        <div class="card-body">
            <x-data-table :headers="['Code', 'Description', 'Hub', 'Status']" :rows="$locations">
                @foreach ($locations as $location)
                    <tr>
                        <td><strong>{{ $location->code }}</strong></td>
                        <td>{{ $location->description }}</td>
                        <td>
                            {{-- A location with no hub cannot be picked when receiving,
                                 so it is flagged rather than shown as a blank cell. --}}
                            @if ($location->hub)
                                <span class="badge bg-secondary">{{ $location->hub->hub_name }}</span>
                            @else
                                <span class="badge bg-warning text-dark">No hub</span>
                            @endif
                        </td>
                        <td>
                            @if ($location->is_active)
                                <x-status-badge status="Active" />
                            @else
                                <x-status-badge status="Inactive" />
                            @endif
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-warning edit-location-btn" data-bs-toggle="modal" data-bs-target="#locationModal" data-location="{{ $location->toJson() }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" action="{{ route('inventory.location-delete', $location) }}" style="display:inline;">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    </div>
</div>

<!-- Location Modal -->
<div class="modal fade" id="locationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="locationModalTitle">Add New Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="locationForm" method="POST" action="{{ route('inventory.location-store') }}" data-store-url="{{ route('inventory.location-store') }}" data-update-url-pattern="/inventory/locations/">
                @csrf
                <input type="hidden" id="locationId" name="location_id">
                <input type="hidden" id="locationFormMethod" name="_method" value="POST">
                <div class="modal-body">
                    <x-form-group
                        label="Location Code"
                        name="code"
                        type="text"
                        required
                        help="e.g., HO, CEBU, DAVAO"
                    />

                    <x-form-group
                        label="Description"
                        name="description"
                        type="text"
                        required
                        help="Full location name"
                    />

                    <x-form-group
                        label="Hub"
                        name="hub_id"
                        type="select"
                        required
                        help="The branch this location is at. Receiving only offers a hub's own locations."
                    >
                        <option value="">-- Select Hub --</option>
                        @foreach ($hubs as $hub)
                            <option value="{{ $hub->id }}">{{ $hub->hub_name }}</option>
                        @endforeach
                    </x-form-group>

                    <x-form-group
                        label="Status"
                        name="is_active"
                        type="select"
                    >
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
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

<script>
const locationForm = document.getElementById('locationForm');
const locationStoreUrl = locationForm.dataset.storeUrl;
const locationUpdateUrlPattern = locationForm.dataset.updateUrlPattern;

document.querySelectorAll('.edit-location-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const locationData = JSON.parse(this.dataset.location);
        editLocation(locationData);
    });
});

function resetLocationForm() {
    document.getElementById('locationForm').reset();
    document.getElementById('locationId').value = '';
    document.getElementById('locationFormMethod').value = 'POST';
    document.getElementById('locationModalTitle').textContent = 'Add New Location';
    locationForm.action = locationStoreUrl;

    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
}

function editLocation(location) {
    document.getElementById('locationId').value = location.id;
    document.getElementById('locationFormMethod').value = 'PUT';
    document.getElementById('locationModalTitle').textContent = 'Edit Location: ' + location.code;
    locationForm.action = locationUpdateUrlPattern + location.id;

    document.querySelector('input[name="code"]').value = location.code;
    document.querySelector('input[name="description"]').value = location.description;
    // Blank rather than a stale value when the row somehow has no hub, so
    // saving cannot silently move it to whichever option happened to be first.
    document.querySelector('select[name="hub_id"]').value = location.hub_id ?? '';
    document.querySelector('select[name="is_active"]').value = location.is_active ? '1' : '0';
}
</script>
@endsection
