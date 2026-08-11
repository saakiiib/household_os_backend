@extends('admin.pages.master')
@section('title', $household->name)
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">
                    <a href="{{ route('admin.households.index') }}" class="text-muted"><i class="ri-arrow-left-line"></i> Households</a>
                    &nbsp;/&nbsp;{{ $household->name }}
                </h4>
            </div>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate">
                <div class="card-body">
                    <p class="text-uppercase fw-medium text-muted text-truncate mb-0">Members</p>
                    <h4 class="fs-22 fw-semibold mt-3 mb-0">{{ $stats['members'] }}</h4>
                    <a href="#members-section" class="text-muted">View all</a>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate">
                <div class="card-body">
                    <p class="text-uppercase fw-medium text-muted text-truncate mb-0">Tasks</p>
                    <h4 class="fs-22 fw-semibold mt-3 mb-0">{{ $stats['tasks_total'] }}</h4>
                    <span class="text-muted">{{ $stats['tasks_pending'] }} pending</span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate">
                <div class="card-body">
                    <p class="text-uppercase fw-medium text-muted text-truncate mb-0">Renewals</p>
                    <h4 class="fs-22 fw-semibold mt-3 mb-0">{{ $stats['renewals_total'] }}</h4>
                    @if($stats['renewals_overdue'] > 0)
                        <span class="text-danger">{{ $stats['renewals_overdue'] }} overdue</span>
                    @else
                        <span class="text-success">All on track</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate">
                <div class="card-body">
                    <p class="text-uppercase fw-medium text-muted text-truncate mb-0">Revenue</p>
                    <h4 class="fs-22 fw-semibold mt-3 mb-0">${{ number_format($stats['payments_total'], 2) }}</h4>
                    <span class="text-muted">{{ $stats['documents_total'] }} documents</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Household Info --}}
    <div class="row">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Household Info</h5>
                    <div class="mt-3">
                        <p class="mb-2"><strong>Name:</strong> {{ $household->name }}</p>
                        <p class="mb-2"><strong>Created By:</strong>
                            @if($household->creator)
                                <a href="{{ route('admin.users.show', $household->creator) }}">{{ $household->creator->name }}</a>
                            @else
                                N/A
                            @endif
                        </p>
                        <p class="mb-2"><strong>Invite Code:</strong> <code>{{ $household->invite_code }}</code></p>
                        <p class="mb-2"><strong>Created:</strong> {{ $household->created_at->format('d M Y') }}</p>
                        @if($household->subscription)
                            <p class="mb-0"><strong>Plan:</strong>
                                <span class="badge badge-soft-{{ $household->subscription->status === 'active' ? 'success' : 'warning' }}">
                                    {{ $household->subscription->plan->name ?? 'Unknown' }} ({{ ucfirst($household->subscription->status) }})
                                </span>
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            {{-- Members --}}
            <div class="card" id="members-section">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Members ({{ $household->members->count() }})</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($household->members as $member)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm rounded-circle bg-soft-primary d-flex align-items-center justify-content-center me-2">
                                                <span class="fw-semibold text-primary">{{ strtoupper(substr($member->first_name, 0, 1)) }}{{ strtoupper(substr($member->last_name, 0, 1)) }}</span>
                                            </div>
                                            <div>
                                                <a href="{{ route('admin.users.show', $member) }}" class="fw-semibold text-body">{{ $member->name }}</a>
                                                <div class="text-muted fs-12">{{ $member->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ ucfirst($member->pivot->role) }}</td>
                                    <td><span class="badge badge-soft-{{ $member->pivot->status === 'active' ? 'success' : 'warning' }}">{{ ucfirst($member->pivot->status) }}</span></td>
                                    <td>{{ $member->pivot->joined_at ? $member->pivot->joined_at->format('d M Y') : '-' }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center text-muted">No members</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tasks --}}
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Tasks ({{ $tasks->count() }})</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Task</th>
                                    <th>Assigned To</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th class="text-center" style="width:60px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($tasks as $task)
                                <tr>
                                    <td><a href="{{ route('admin.tasks.show', $task) }}" class="fw-semibold text-body">{{ $task->title }}</a></td>
                                    <td>
                                        @if($task->assignedUser)
                                            <a href="{{ route('admin.users.show', $task->assignedUser) }}" class="text-body">{{ $task->assignedUser->name }}</a>
                                        @else
                                            <span class="text-muted">Unassigned</span>
                                        @endif
                                    </td>
                                    <td>{{ $task->due_date ? $task->due_date->format('d M Y') : '-' }}</td>
                                    <td>
                                        @php $cls = match($task->status) { 'completed' => 'success', 'in_progress' => 'info', default => 'warning' }; @endphp
                                        <span class="badge badge-soft-{{ $cls }}">{{ ucfirst(str_replace('_', ' ', $task->status)) }}</span>
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('admin.tasks.show', $task) }}" class="btn btn-sm btn-light"><i class="ri-eye-line"></i></a>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-center text-muted">No tasks</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Renewals --}}
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Renewals ({{ $renewals->count() }})</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Renewal</th>
                                    <th>Category</th>
                                    <th>Due Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th class="text-center" style="width:60px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($renewals as $renewal)
                                <tr>
                                    <td><a href="{{ route('admin.renewals.show', $renewal) }}" class="fw-semibold text-body">{{ $renewal->title }}</a></td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $renewal->category)) }}</td>
                                    <td>{{ $renewal->due_date ? $renewal->due_date->format('d M Y') : '-' }}</td>
                                    <td>{{ $renewal->amount ? '$' . number_format($renewal->amount, 2) : '-' }}</td>
                                    <td>
                                        @php $cls = match($renewal->status) { 'completed' => 'success', default => 'warning' }; @endphp
                                        <span class="badge badge-soft-{{ $cls }}">{{ ucfirst($renewal->status) }}</span>
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('admin.renewals.show', $renewal) }}" class="btn btn-sm btn-light"><i class="ri-eye-line"></i></a>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="6" class="text-center text-muted">No renewals</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Documents --}}
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Documents ({{ $documents->count() }})</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Document</th>
                                    <th>Category</th>
                                    <th>Files</th>
                                    <th>Due Date</th>
                                    <th class="text-center" style="width:60px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($documents as $doc)
                                <tr>
                                    <td><a href="{{ route('admin.documents.show', $doc) }}" class="fw-semibold text-body">{{ $doc->title }}</a></td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $doc->category)) }}</td>
                                    <td>{{ $doc->files->count() }}</td>
                                    <td>{{ $doc->due_date ? $doc->due_date->format('d M Y') : '-' }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('admin.documents.show', $doc) }}" class="btn btn-sm btn-light"><i class="ri-eye-line"></i></a>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-center text-muted">No documents</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Payments --}}
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Payments ({{ $household->payments->count() }})</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Gateway</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="text-center" style="width:60px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($household->payments as $payment)
                                <tr>
                                    <td>
                                        @if($payment->user)
                                            <a href="{{ route('admin.users.show', $payment->user) }}" class="text-body">{{ $payment->user->name }}</a>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td>${{ number_format($payment->amount, 2) }}</td>
                                    <td>{{ ucfirst($payment->gateway) }}</td>
                                    <td>
                                        @php $cls = match($payment->status) { 'completed' => 'success', 'failed' => 'danger', default => 'warning' }; @endphp
                                        <span class="badge badge-soft-{{ $cls }}">{{ ucfirst($payment->status) }}</span>
                                    </td>
                                    <td>{{ $payment->created_at->format('d M Y') }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('admin.payments.show', $payment) }}" class="btn btn-sm btn-light"><i class="ri-eye-line"></i></a>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="6" class="text-center text-muted">No payments</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
