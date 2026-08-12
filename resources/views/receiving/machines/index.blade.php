@extends('layouts.app')

@section('title', 'Receive Machines')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Add Machine Items</h2>
        <!-- <a href="{{ route('receiving.machine-create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Machine
        </a> -->
    </div>

    <x-alert />

    <div class="card">
        <div class="card-body">
            <x-data-table :headers="['Serial No', 'Description', 'Category', 'Customer', 'Location']" :rows="$machines">
                @foreach ($machines as $machine)
                    <tr>
                        <td><strong>{{ $machine->serial_no }}</strong></td>
                        <td>{{ $machine->description }}</td>
                        <td>{{ $machine->category->code ?? '-' }}</td>
                        <td>{{ $machine->customer->name ?? '-' }}</td>
                        <td>{{ $machine->location->code ?? '-' }}</td>
                        <td>
                            <a href="{{ route('machines.add-part', $machine) }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Add Parts
                            </a>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    </div>
</div>
@endsection
