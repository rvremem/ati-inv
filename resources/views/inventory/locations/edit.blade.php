@extends('layouts.app')

@section('title', 'Edit Location')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">Edit Location: {{ $location->code }}</h5>
                </div>
                <div class="card-body">
                    <x-alert />

                    <form method="POST" action="{{ route('inventory.location-update', $location) }}">
                        @csrf @method('PUT')

                        <x-form-group
                            label="Location Code"
                            name="code"
                            type="text"
                            :value="$location->code"
                            required
                        />

                        <x-form-group
                            label="Description"
                            name="description"
                            type="text"
                            :value="$location->description"
                            required
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
                                <option value="{{ $hub->id }}" @selected(old('hub_id', $location->hub_id) == $hub->id)>
                                    {{ $hub->hub_name }}
                                </option>
                            @endforeach
                        </x-form-group>

                        <x-form-group
                            label="Status"
                            name="is_active"
                            type="checkbox"
                            :value="$location->is_active"
                        />

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update
                            </button>
                            <a href="{{ route('inventory.locations') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
