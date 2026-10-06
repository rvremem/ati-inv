@extends('layouts.app')

@section('title', 'Cost Report')

@section('styles')
<style>
    .kpi-row { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 20px; }

    .kpi {
        flex: 1 1 200px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px 18px;
    }

    .kpi-label {
        font-size: 11.5px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: #94a3b8;
        margin-bottom: 6px;
    }

    .kpi-value { font-size: 24px; font-weight: 700; color: #0f172a; line-height: 1.1; }
    .kpi-value.money { color: #059669; }
    .kpi-note { font-size: 11.5px; color: #94a3b8; margin-top: 4px; }

    .cost-table td, .cost-table th { vertical-align: middle; }
    .num { text-align: right; white-space: nowrap; }
    .cost-table tfoot td { border-top: 2px solid #e2e8f0; font-weight: 700; color: #0f172a; }
    .zero { color: #cbd5e1; }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="mb-0">Cost Report</h2>
        {{-- Carries the same as-of date the table is showing, so the file can
             never cover a different period than what is on screen. --}}
        <a href="{{ route('reports.cost-csv', request()->only('as_of_date')) }}"
           class="btn btn-outline-primary">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.cost') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">As of Date</label>
                    <input type="date" name="as_of_date" class="form-control" value="{{ request('as_of_date') }}">
                </div>
                <x-hub-filter />
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="kpi-row">
        <div class="kpi">
            <div class="kpi-label">Total Inventory Value</div>
            <div class="kpi-value money">${{ number_format($grandTotal, 2) }}</div>
            <div class="kpi-note">unit cost × units on hand</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Units On Hand</div>
            <div class="kpi-value">{{ number_format($totalUnits) }}</div>
            <div class="kpi-note">across {{ $items->count() }} active part{{ $items->count() === 1 ? '' : 's' }}</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Unpriced With Stock</div>
            <div class="kpi-value">{{ number_format($unpriced) }}</div>
            {{-- Called out rather than hidden: these hold stock but carry no
                 cost, so the total above understates by however much they are
                 really worth. --}}
            <div class="kpi-note">
                {{ $unpriced ? 'these parts hold stock but have no unit cost' : 'every part with stock is priced' }}
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table cost-table mb-0">
                <thead>
                    <tr>
                        <th>Part No</th>
                        <th>Description</th>
                        <th>Type</th>
                        <th class="num">Unit Cost</th>
                        <th class="num">On Hand</th>
                        <th class="num">Total Value</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td><strong>{{ $item->part_no }}</strong></td>
                            <td>{{ $item->description }}</td>
                            <td>{{ $item->type ?? '-' }}</td>
                            <td class="num">${{ number_format($item->unit_cost, 2) }}</td>
                            <td class="num {{ $item->on_hand <= 0 ? 'zero' : '' }}">{{ number_format($item->on_hand) }}</td>
                            <td class="num {{ $item->total_value <= 0 ? 'zero' : '' }}">
                                <strong>${{ number_format($item->total_value, 2) }}</strong>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No active parts to value.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($items->isNotEmpty())
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end">Total</td>
                        <td class="num">{{ number_format($totalUnits) }}</td>
                        <td class="num">${{ number_format($grandTotal, 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
    <x-back-link :href="route('reports.index')" />
</div>
@endsection
