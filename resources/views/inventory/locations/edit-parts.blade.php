@extends('layouts.app')

@section('title', 'Edit Parts Location')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Edit Parts Location</h2>
            <small class="text-muted">Reassign storage locations for received parts</small>
        </div>
        <a href="{{ route('inventory.locations') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Locations
        </a>
    </div>

    <x-alert />

    <form method="POST" action="{{ route('inventory.update-parts-location') }}" id="editPartsForm">
        @csrf
        <div class="card">
            <div class="card-header">
                <span><i class="fas fa-boxes me-2"></i>Received Parts ({{ $receivings->count() }} record/s)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 no-datatable">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Part No.</th>
                                <th>Description</th>
                                <th>Serial No.</th>
                                <th>Qty</th>
                                <th>Status</th>
                                <th>Current Location</th>
                                <th>New Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($receivings as $i => $row)
                                <tr>
                                    <td class="text-muted">{{ $i + 1 }}</td>
                                    <td><strong>{{ $row->part_no }}</strong></td>
                                    <td>{{ $row->description }}</td>
                                    <td>{{ $row->serial_no ?? '—' }}</td>
                                    <td>{{ $row->quantity }}</td>
                                    <td>
                                        <span class="badge bg-{{ $row->status === 'Returned' ? 'danger' : ($row->status === 'Pending' ? 'warning' : 'primary') }}">
                                            {{ $row->status }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($row->location_code)
                                            <span class="badge bg-secondary">{{ $row->location_code }}</span>
                                            <small class="text-muted ms-1">{{ $row->location_name }}</small>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td style="min-width: 200px;">
                                        <input type="hidden" name="updates[{{ $i }}][receiving_id]" value="{{ $row->id }}">
                                        <select class="form-select form-select-sm location-select"
                                                name="updates[{{ $i }}][location_id]"
                                                data-original="{{ $row->location_id }}">
                                            @foreach ($locations as $loc)
                                                <option value="{{ $loc->id }}"
                                                    {{ $loc->id == $row->location_id ? 'selected' : '' }}>
                                                    {{ $loc->code }} — {{ $loc->description }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        No receiving records found. Add parts via <a href="{{ route('receiving.items') }}">Receiving</a>.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($receivings->count() > 0)
                <div class="card-footer d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            @endif
        </div>
    </form>
</div>

<script>
    document.querySelectorAll('.location-select').forEach(function (sel) {
        sel.addEventListener('change', function () {
            const row = this.closest('tr');
            row.classList.toggle('table-warning', this.value !== this.dataset.original);
        });
    });
</script>
@endsection
