@extends('layouts.app')

@section('title', 'Reports')

@section('content')
<div class="container-fluid">
    <h2 class="mb-4">Reports & Analytics</h2>

    <div class="row">
        <div class="col-md-3 mb-4">
            <a href="{{ route('reports.receiving') }}" class="card text-decoration-none h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-sign-in-alt fa-3x text-primary mb-3"></i>
                    <h5 class="card-title">Item Inbound</h5>
                    <p class="card-text text-muted">Inbound parts and machines</p>
                </div>
            </a>
        </div>

        <div class="col-md-3 mb-4">
            <a href="{{ route('reports.outbound') }}" class="card text-decoration-none h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-sign-out-alt fa-3x text-success mb-3"></i>
                    <h5 class="card-title">Item Outbound</h5>
                    <p class="card-text text-muted">Issued part requests</p>
                </div>
            </a>
        </div>

        <div class="col-md-3 mb-4">
            <a href="{{ route('reports.defective') }}" class="card text-decoration-none h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <h5 class="card-title">Defective Items</h5>
                    <p class="card-text text-muted">Damaged and warranty items</p>
                </div>
            </a>
        </div>

        <div class="col-md-3 mb-4">
            <a href="{{ route('reports.stock') }}" class="card text-decoration-none h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-boxes fa-3x text-info mb-3"></i>
                    <h5 class="card-title">Stock Report</h5>
                    <p class="card-text text-muted">Current inventory levels</p>
                </div>
            </a>
        </div>

        {{-- Costs are Super Admin / Developer only; the controller refuses
             anyone else, and hiding the card keeps it off the menu too. --}}
        @if (auth()->user()?->isSA())
        <div class="col-md-3 mb-4">
            <a href="{{ route('reports.cost') }}" class="card text-decoration-none h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-peso-sign fa-3x text-success mb-3"></i>
                    <h5 class="card-title">Cost Report</h5>
                    <p class="card-text text-muted">Inventory value by part</p>
                </div>
            </a>
        </div>
        @endif

        <div class="col-md-3 mb-4">
            <a href="{{ route('reports.machines') }}" class="card text-decoration-none h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-server fa-3x text-warning mb-3"></i>
                    <h5 class="card-title">Machines Report</h5>
                    <p class="card-text text-muted">Equipment inventory</p>
                </div>
            </a>
        </div>

        <div class="col-md-3 mb-4">
            <a href="{{ route('reports.machine-inbound') }}" class="card text-decoration-none h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-truck-loading fa-3x text-primary mb-3"></i>
                    <h5 class="card-title">Machine Inbound</h5>
                    <p class="card-text text-muted">Machines received and returned</p>
                </div>
            </a>
        </div>

        <div class="col-md-3 mb-4">
            <a href="{{ route('reports.machine-outbound') }}" class="card text-decoration-none h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-dolly fa-3x text-success mb-3"></i>
                    <h5 class="card-title">Machine Outbound</h5>
                    <p class="card-text text-muted">Machines issued to clients</p>
                </div>
            </a>
        </div>

        <div class="col-md-3 mb-4">
            <a href="{{ route('reports.machine-parts') }}" class="card text-decoration-none h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-screwdriver-wrench fa-3x text-secondary mb-3"></i>
                    <h5 class="card-title">Machine Parts</h5>
                    <p class="card-text text-muted">Assembly and disassembly history</p>
                </div>
            </a>
        </div>

        <div class="col-md-3 mb-4">
            <a href="{{ route('reports.maintenance') }}" class="card text-decoration-none h-100 shadow-sm">
                <div class="card-body text-center">
                    {{-- fa-file-contract, matching the sidebar's Contracts entry. Not
                         fa-tools: in Font Awesome 6 that is an alias for
                         fa-screwdriver-wrench, so it rendered the exact same glyph as
                         the Machine Parts card above. --}}
                    <i class="fas fa-file-contract fa-3x text-info mb-3"></i>
                    <h5 class="card-title">Maintenance</h5>
                    <p class="card-text text-muted">Service contracts</p>
                </div>
            </a>
        </div>

        <div class="col-md-3 mb-4">
            <a href="{{ route('reports.request-status') }}" class="card text-decoration-none h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-tasks fa-3x text-primary mb-3"></i>
                    <h5 class="card-title">Request Status</h5>
                    <p class="card-text text-muted">Pending and approved requests</p>
                </div>
            </a>
        </div>

        <div class="col-md-3 mb-4">
            <a href="{{ route('reports.activity-log') }}" class="card text-decoration-none h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-history fa-3x text-dark mb-3"></i>
                    <h5 class="card-title">Activity Log</h5>
                    <p class="card-text text-muted">User actions and audit trail</p>
                </div>
            </a>
        </div>
    </div>
</div>
@endsection
