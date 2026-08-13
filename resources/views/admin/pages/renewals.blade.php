@extends('admin.pages.master')
@section('title', 'Renewals')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Renewal Management</h4>
                <div class="page-title-right d-flex gap-2 align-items-center">
                    <a href="#" class="btn btn-primary btn-sm"><i class="ri-refresh-line"></i> Add new</a>
                </div>
            </div>
        </div>
    </div>

    @php
        $renewalStatusColors = [
            'pending'=>'warning','completed'=>'success',
        ];
    @endphp

    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Total Renewals</p>
                            <h4 class="mb-0">{{ $renewals->total() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-primary text-primary rounded fs-3"><i class="ri-refresh-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Pending</p>
                            <h4 class="mb-0">{{ $renewals->where('status', 'pending')->count() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-warning text-warning rounded fs-3"><i class="ri-time-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Completed</p>
                            <h4 class="mb-0">{{ $renewals->where('status', 'completed')->count() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-success text-success rounded fs-3"><i class="ri-checkbox-circle-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Total Value</p>
                            <h4 class="mb-0">£{{ number_format($renewals->sum('amount'), 2) }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-info text-info rounded fs-3"><i class="ri-money-pound-circle-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">All Renewals</h4>
                    <span class="badge bg-soft-primary fs-12">{{ $renewals->total() }} total</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Renewal</th>
                                    <th>Household</th>
                                    <th>Category</th>
                                    <th>Expiry</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($renewals as $renewal)
                                <tr>
                                    <td class="fw-medium">{{ $renewal->title }}</td>
                                    <td>{{ $renewal->household->name ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-soft-secondary text-secondary text-capitalize">
                                            {{ $renewal->category ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="text-muted">{{ $renewal->due_date ? $renewal->due_date->format('d M Y') : '—' }}</td>
                                    <td class="fw-medium">£{{ number_format($renewal->amount ?? 0, 2) }}</td>
                                    <td>
                                        <span class="badge bg-soft-{{ $renewalStatusColors[$renewal->status] ?? 'secondary' }} text-{{ $renewalStatusColors[$renewal->status] ?? 'secondary' }}">
                                            {{ ucfirst($renewal->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.renewals.show', $renewal->id) }}" class="btn btn-sm btn-soft-primary">View</a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="ri-refresh-line fs-24 d-block mb-2"></i>
                                        No records found
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $renewals->links() }}</div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
