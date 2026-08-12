@extends('layouts.app')

@section('title', 'Stock Report')

@section('content')
<div class="container-fluid">
    <div class="mb-3">
        <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    <h2 class="mb-4">Stock Report</h2>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.stock') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">As of Date</label>
                    <input type="date" name="as_of_date" class="form-control" value="{{ request('as_of_date') }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <x-data-table
                :headers="['Part No', 'Description', 'Type', 'Min Stock', 'On Hand']"
                :rows="$items"
                :actions="false"
            >
                @foreach ($items as $item)
                    <tr>
                        <td><strong>{{ $item->part_no }}</strong></td>
                        <td>{{ $item->description }}</td>
                        <td>{{ $item->type ?? '-' }}</td>
                        <td>{{ $item->min_stock }}</td>
                        <td>
                            <span class="badge {{ $item->on_hand < $item->min_stock ? 'bg-danger' : 'bg-success' }}">
                                {{ $item->on_hand }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>
        </div>
    </div>
</div>
@endsection
