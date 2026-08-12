@extends('layouts.app')

@section('title', 'Defective Parts')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Defective Parts</h2>
        <a href="{{ route('defective-parts.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Defective Part
        </a>
    </div>

    <x-alert />

    <div class="card">
        <div class="card-body">
            <x-data-table :headers="['Part Number', 'Description', 'Quantity', 'Reason', 'Location', 'Status']" :rows="$defectiveParts">
                @foreach ($defectiveParts as $defectivePart)
                    <tr>
                        <td><strong>{{ $defectivePart->itemPart->part_no }}</strong></td>
                        <td>{{ $defectivePart->itemPart->description }}</td>
                        <td><span class="badge bg-info">{{ $defectivePart->quantity }}</span></td>
                        <td>{{ $defectivePart->reason }}</td>
                        <td>{{ $defectivePart->location->code }}</td>
                        <td>
                            <span class="badge bg-danger">{{ $defectivePart->status }}</span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="{{ route('defective-parts.edit', $defectivePart) }}" class="btn btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" action="{{ route('defective-parts.destroy', $defectivePart) }}" style="display:inline;">
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
@endsection
