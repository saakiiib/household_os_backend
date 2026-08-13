@extends('admin.pages.master')
@section('title', 'Households')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Household Management</h4>
                <div class="page-title-right d-flex gap-2 align-items-center">
                    <a href="#" class="btn btn-primary btn-sm"><i class="ri-home-add-line"></i> Add new</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Total Households</p>
                            <h4 class="mb-0">{{ $households->total() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-primary text-primary rounded fs-3"><i class="ri-home-line"></i></span>
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
                            <p class="text-muted mb-2 text-truncate">Total Members</p>
                            <h4 class="mb-0">{{ collect($households->items())->sum('members_count') }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-info text-info rounded fs-3"><i class="ri-group-line"></i></span>
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
                            <p class="text-muted mb-2 text-truncate">Active</p>
                            <h4 class="mb-0">{{ collect($households->items())->where('status', 'active')->count() }}</h4>
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
                            <p class="text-muted mb-2 text-truncate">Archived</p>
                            <h4 class="mb-0">{{ collect($households->items())->where('status', 'archived')->count() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-warning text-warning rounded fs-3"><i class="ri-archive-line"></i></span>
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
                    <h4 class="card-title mb-0 flex-grow-1">All Households</h4>
                    <span class="badge bg-soft-primary fs-12">{{ $households->total() }} total</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Household</th>
                                    <th>Owner</th>
                                    <th>Members</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($households as $household)
                                <tr>
                                    <td class="fw-medium">{{ $household->name }}</td>
                                    <td>{{ $household->creator->name ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-soft-secondary text-secondary">{{ $household->members_count }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-soft-{{ $household->status == 'active' ? 'success' : 'warning' }} text-{{ $household->status == 'active' ? 'success' : 'warning' }}">
                                            {{ ucfirst($household->status) }}
                                        </span>
                                    </td>
                                    <td class="text-muted">{{ $household->created_at->format('d M Y') }}</td>
                                    <td>
                                        <a href="{{ route('admin.households.show', $household->id) }}" class="btn btn-sm btn-soft-primary">View</a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="ri-home-heart-line fs-24 d-block mb-2"></i>
                                        No records found
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $households->links() }}</div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
