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
                    <h5 class="card-title">Receiving Report</h5>
                    <p class="card-text text-muted">Inbound parts and machines</p>
                </div>
            </a>
        </div>

        <div class="col-md-3 mb-4">
            <a href="{{ route('reports.outbound') }}" class="card text-decoration-none h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-sign-out-alt fa-3x text-success mb-3"></i>
                    <h5 class="card-title">Outbound Report</h5>
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
            <a href="{{ route('reports.maintenance') }}" class="card text-decoration-none h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-tools fa-3x text-secondary mb-3"></i>
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
