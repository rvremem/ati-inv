@extends('layouts.app')

@section('title', 'Machine Requests')

@section('content')
<div class="container-fluid">
    <h2 class="mb-4">Machine Requests</h2>

    <x-alert />

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Available Machines for Request</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th style="width: 150px;">Serial Number</th>
                            <th>Description</th>
                            <th>Model</th>
                            <th style="width: 120px;">Category</th>
                            <th style="width: 150px;">Customer</th>
                            <th style="width: 120px;">Location</th>
                            <th style="width: 150px;">Status</th>
                            <th style="width: 150px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($machines as $machine)
                            <tr>
                                <td><strong>{{ $machine->serial_no }}</strong></td>
                                <td>{{ $machine->description }}</td>
                                <td>{{ $machine->model ?? '-' }}</td>
                                <td>{{ $machine->category->code ?? '-' }}</td>
                                <td>{{ $machine->customer->name ?? '-' }}</td>
                                <td>{{ $machine->location->code ?? '-' }}</td>
                                <td>
                                    {{-- This list only contains machines whose status is
                                         on-hand, so every row here is available. --}}
                                    <span class="badge bg-success">Available</span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm add-request-btn" data-machine="{{ $machine->toJson() }}">
                                        <i class="fas fa-plus"></i> Add Request
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<!-- Add Request Modal -->
<div class="modal fade" id="addRequestModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Machine Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addRequestForm" method="POST" action="{{ route('requests.machine-store') }}">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Machine Serial</label>
                                <input type="text" class="form-control" id="serialNo" disabled>
                                <input type="hidden" name="machine_id" id="machineId">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Description</label>
                                <input type="text" class="form-control" id="description" disabled>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Location</label>
                                <input type="text" class="form-control" id="location" disabled>
                                <input type="hidden" name="location_id" id="locationId">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Customer</label>
                                <input type="text" class="form-control" id="customer" disabled>
                                <input type="hidden" name="customer_id" id="customerId">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Pick Up Date *</label>
                                <input type="date" name="pick_date" id="pickDate" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Purpose</label>
                                <input type="text" name="purpose" id="purpose" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Description (Request)</label>
                                <input type="text" name="description" id="requestDescription" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks" id="remarks" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
$(function () {
    const addRequestModalEl = document.getElementById('addRequestModal');
    const addRequestModal = new bootstrap.Modal(addRequestModalEl);

    $(document).on('click', '.add-request-btn', function () {
        const machine = JSON.parse($(this).attr('data-machine'));

        $('#machineId').val(machine.id);
        $('#serialNo').val(machine.serial_no);
        $('#description').val(machine.description);
        $('#location').val(machine.location ? machine.location.code : '-');
        $('#locationId').val(machine.location_id || '');
        $('#customer').val(machine.customer ? machine.customer.name : '-');
        $('#customerId').val(machine.customer_id || '');
        $('#pickDate').val('');
        $('#purpose').val('');
        $('#requestDescription').val(machine.description);
        $('#remarks').val('');

        addRequestModal.show();
    });

    $('#addRequestModal').on('click', '.btn-close, [data-bs-dismiss="modal"]', function () {
        addRequestModal.hide();
    });
});
</script>
@endsection
