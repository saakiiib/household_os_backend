@extends('admin.pages.master')
@section('title', 'Invitations')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Invitations</h4>
                <div class="page-title-right d-flex gap-2 align-items-center">
                    <a href="#" class="btn btn-primary btn-sm"><i class="ri-mail-add-line"></i> Add new</a>
                </div>
            </div>
        </div>
    </div>

    @php
        $inviteStatusColors = [
            'pending'=>'warning','accepted'=>'success','rejected'=>'danger',
            'expired'=>'danger','cancelled'=>'secondary',
        ];
        $roleColors = [
            'admin'=>'primary','co-admin'=>'info','member'=>'secondary',
        ];
    @endphp

    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Total Invitations</p>
                            <h4 class="mb-0">{{ $invitations->total() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-primary text-primary rounded fs-3"><i class="ri-mail-send-line"></i></span>
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
                            <h4 class="mb-0">{{ collect($invitations->items())->where('status', 'pending')->count() }}</h4>
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
                            <p class="text-muted mb-2 text-truncate">Accepted</p>
                            <h4 class="mb-0">{{ collect($invitations->items())->where('status', 'accepted')->count() }}</h4>
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
                            <p class="text-muted mb-2 text-truncate">Expired / Rejected</p>
                            <h4 class="mb-0">{{ collect($invitations->items())->filter(function($i){ return in_array($i->status, ['expired','rejected','cancelled']); })->count() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-danger text-danger rounded fs-3"><i class="ri-close-circle-line"></i></span>
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
                    <h4 class="card-title mb-0 flex-grow-1">All Invitations</h4>
                    <span class="badge bg-soft-primary fs-12">{{ $invitations->total() }} total</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Invitee</th>
                                    <th>Household</th>
                                    <th>Role</th>
                                    <th>Invite Code</th>
                                    <th>Sent</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($invitations as $invitation)
                                <tr>
                                    <td class="fw-medium">{{ $invitation->invited_email }}</td>
                                    <td>{{ $invitation->household->name ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-soft-{{ $roleColors[$invitation->role] ?? 'secondary' }} text-{{ $roleColors[$invitation->role] ?? 'secondary' }} text-capitalize">
                                            {{ str_replace('-', ' ', $invitation->role) }}
                                        </span>
                                    </td>
                                    <td><code>{{ $invitation->household->invite_code ?? '—' }}</code></td>
                                    <td class="text-muted">{{ $invitation->created_at->format('d M Y') }}</td>
                                    <td>
                                        <span class="badge bg-soft-{{ $inviteStatusColors[$invitation->status] ?? 'secondary' }} text-{{ $inviteStatusColors[$invitation->status] ?? 'secondary' }}">
                                            {{ ucfirst($invitation->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.page.detail', ['page' => 'invitations', 'id' => $invitation->id]) }}" class="btn btn-sm btn-soft-primary">View</a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="ri-mail-close-line fs-24 d-block mb-2"></i>
                                        No records found
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $invitations->links() }}</div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
