@extends('admin.pages.master')
@section('title', 'Household Details')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <div>
                    <h4 class="mb-sm-0 font-size-18">{{ $household->name ?? 'Household' }}</h4>
                    <p class="text-muted mb-0">Complete household control centre for members, billing, documents, activity and administrative actions.</p>
                </div>
                <div class="page-title-right d-flex gap-2 align-items-center">
                    <a href="{{ route('admin.page', ['page' => 'households']) }}" class="btn btn-soft-secondary btn-sm"><i class="ri-arrow-left-line"></i> Back</a>
                    <button class="btn btn-soft-primary btn-sm"><i class="ri-edit-line"></i> Edit Household</button>
                </div>
            </div>
        </div>
    </div>

    @if (!$household)
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <div class="avatar-lg mx-auto mb-3">
                            <span class="avatar-title bg-soft-secondary text-secondary rounded-circle fs-1"><i class="ri-home-line"></i></span>
                        </div>
                        <h5 class="mb-1">No household selected</h5>
                        <p class="text-muted mb-0">Select a household to view its members, billing and activity.</p>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex">
                            <div class="flex-grow-1">
                                <p class="text-muted mb-2 text-truncate">Members</p>
                                <h4 class="mb-0">{{ $members->count() }}</h4>
                            </div>
                            <div class="avatar-sm">
                                <span class="avatar-title bg-soft-primary text-primary rounded fs-3"><i class="ri-group-line"></i></span>
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
                                <p class="text-muted mb-2 text-truncate">Current Plan</p>
                                <h4 class="mb-0">{{ $household->plan ?? '—' }}</h4>
                            </div>
                            <div class="avatar-sm">
                                <span class="avatar-title bg-soft-info text-info rounded fs-3"><i class="ri-vip-crown-line"></i></span>
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
                                <p class="text-muted mb-2 text-truncate">Lifetime Value</p>
                                <h4 class="mb-0">£{{ number_format($payments->sum('amount'), 2) }}</h4>
                            </div>
                            <div class="avatar-sm">
                                <span class="avatar-title bg-soft-success text-success rounded fs-3"><i class="ri-money-pound-circle-line"></i></span>
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
                                <p class="text-muted mb-2 text-truncate">Household Health</p>
                                <h4 class="mb-0">{{ $household->health ?? '—' }}</h4>
                            </div>
                            <div class="avatar-sm">
                                <span class="avatar-title bg-soft-warning text-warning rounded fs-3"><i class="ri-heart-pulse-line"></i></span>
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
                        <h4 class="card-title mb-0 flex-grow-1">Household Members</h4>
                        <button class="btn btn-soft-primary btn-sm"><i class="ri-user-add-line"></i> Invite member</button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-nowrap align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Member</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Activity</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($members as $member)
                                        <tr>
                                            <td class="fw-medium">{{ optional($member->user)->name ?? $member->name ?? '—' }}</td>
                                            <td>{{ optional($member->user)->email ?? $member->email ?? '—' }}</td>
                                            <td><span class="badge bg-soft-primary text-primary">{{ ucfirst($member->role ?? 'member') }}</span></td>
                                            <td><span class="badge bg-soft-success text-success">{{ ucfirst($member->status ?? 'active') }}</span></td>
                                            <td class="text-muted">{{ $member->last_active_at ? $member->last_active_at->diffForHumans() : '—' }}</td>
                                            <td><a href="#" class="btn btn-sm btn-soft-primary">View</a></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">No members found</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header align-items-center d-flex">
                        <h4 class="card-title mb-0 flex-grow-1">Payment Breakdown</h4>
                        <button class="btn btn-soft-primary btn-sm"><i class="ri-file-list-line"></i> Generate invoice</button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-nowrap align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Reference</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($payments as $payment)
                                        <tr>
                                            <td class="fw-medium">{{ $payment->reference ?? '—' }}</td>
                                            <td>{{ $payment->description ?? '—' }}</td>
                                            <td>£{{ number_format($payment->amount ?? 0, 2) }}</td>
                                            <td>{{ $payment->method ?? '—' }}</td>
                                            <td class="text-muted">{{ $payment->created_at ? $payment->created_at->format('d M Y') : '—' }}</td>
                                            <td><span class="badge bg-soft-secondary text-secondary">{{ ucfirst($payment->status ?? '—') }}</span></td>
                                            <td><a href="#" class="btn btn-sm btn-soft-primary">Download</a></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">No payments found</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4 class="card-title mb-0">Household Timeline</h4>
                    </div>
                    <div class="card-body">
                        <div class="timeline timeline-primary">
                            @forelse ($timeline as $item)
                                <div class="timeline-item">
                                    <div class="timeline-marker"></div>
                                    <div class="timeline-content">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-medium">{{ $item->description ?? '—' }}</span>
                                            <small class="text-muted">{{ $item->created_at ? $item->created_at->diffForHumans() : ($item->time ?? '') }}</small>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-center text-muted mb-0">No timeline events</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4 class="card-title mb-0">Admin Controls</h4>
                    </div>
                    <div class="card-body">
                        <div class="vstack gap-2">
                            <a href="#" class="btn btn-soft-danger btn-sm"><i class="ri-forbid-line"></i> Suspend Household</a>
                            <a href="#" class="btn btn-soft-warning btn-sm"><i class="ri-refresh-line"></i> Reset Access</a>
                            <a href="#" class="btn btn-soft-primary btn-sm"><i class="ri-mail-send-line"></i> Send Notice</a>
                            <a href="#" class="btn btn-soft-secondary btn-sm"><i class="ri-lock-line"></i> Lock Account</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
@endsection
