@extends('admin.pages.master')
@section('title', $user->name)
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">
                    <a href="{{ route('admin.users.index') }}" class="text-muted"><i class="ri-arrow-left-line"></i> Users</a>
                    &nbsp;/&nbsp;{{ $user->name }}
                </h4>
            </div>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate">
                <div class="card-body">
                    <p class="text-uppercase fw-medium text-muted text-truncate mb-0">Households</p>
                    <h4 class="fs-22 fw-semibold mt-3 mb-0">{{ $stats['households'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate">
                <div class="card-body">
                    <p class="text-uppercase fw-medium text-muted text-truncate mb-0">Tasks Created</p>
                    <h4 class="fs-22 fw-semibold mt-3 mb-0">{{ $stats['tasks_created'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate">
                <div class="card-body">
                    <p class="text-uppercase fw-medium text-muted text-truncate mb-0">Tasks Assigned</p>
                    <h4 class="fs-22 fw-semibold mt-3 mb-0">{{ $stats['tasks_assigned'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-animate">
                <div class="card-body">
                    <p class="text-uppercase fw-medium text-muted text-truncate mb-0">Total Payments</p>
                    <h4 class="fs-22 fw-semibold mt-3 mb-0">${{ number_format($stats['payments_total'], 2) }}</h4>
                    <span class="text-muted">{{ $stats['payments_count'] }} transactions</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- User Info --}}
        <div class="col-xl-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="avatar-xl mx-auto mb-3">
                        <div class="avatar-title rounded-circle bg-soft-primary text-primary fs-1 fw-bold">
                            {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
                        </div>
                    </div>
                    <h5 class="mb-1">{{ $user->name }}</h5>
                    <p class="text-muted mb-2">{{ $user->email }}</p>
                    <span class="badge badge-soft-{{ $user->status === 'active' ? 'success' : 'danger' }} fs-12">
                        {{ ucfirst($user->status) }}
                    </span>
                    @if($user->is_admin)
                        <span class="badge badge-soft-primary fs-12">Admin</span>
                    @endif
                    <div class="mt-3">
                        <p class="text-muted mb-1"><i class="ri-phone-line me-1"></i>{{ $user->phone ?? 'No phone' }}</p>
                        <p class="text-muted mb-0"><i class="ri-calendar-line me-1"></i>Joined {{ $user->created_at->format('d M Y') }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Households --}}
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Households ({{ $user->households->count() }})</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Household</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($user->households as $household)
                                <tr>
                                    <td><a href="{{ route('admin.households.show', $household) }}" class="fw-semibold text-body">{{ $household->name }}</a></td>
                                    <td>{{ ucfirst($household->pivot->role) }}</td>
                                    <td>
                                        <span class="badge badge-soft-{{ $household->pivot->status === 'active' ? 'success' : 'warning' }}">
                                            {{ ucfirst($household->pivot->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $household->pivot->joined_at ? $household->pivot->joined_at->format('d M Y') : '-' }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center text-muted">No households</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tasks Created --}}
    <div class="row">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Tasks Created ({{ $tasksAsCreator->count() }})</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Task</th>
                                    <th>Household</th>
                                    <th>Assigned To</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($tasksAsCreator as $task)
                                <tr>
                                    <td><a href="{{ route('admin.tasks.show', $task) }}" class="fw-semibold text-body">{{ $task->title }}</a></td>
                                    <td>
                                        @if($task->household)
                                            <a href="{{ route('admin.households.show', $task->household) }}" class="text-body">{{ $task->household->name }}</a>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td>
                                        @if($task->assignedUser)
                                            <a href="{{ route('admin.users.show', $task->assignedUser) }}" class="text-body">{{ $task->assignedUser->name }}</a>
                                        @else
                                            <span class="text-muted">Unassigned</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php $cls = match($task->status) { 'completed' => 'success', 'in_progress' => 'info', default => 'warning' }; @endphp
                                        <span class="badge badge-soft-{{ $cls }}">{{ ucfirst(str_replace('_', ' ', $task->status)) }}</span>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center text-muted">No tasks created</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tasks Assigned --}}
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Tasks Assigned ({{ $tasksAsAssignee->count() }})</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Task</th>
                                    <th>Household</th>
                                    <th>Created By</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($tasksAsAssignee as $task)
                                <tr>
                                    <td><a href="{{ route('admin.tasks.show', $task) }}" class="fw-semibold text-body">{{ $task->title }}</a></td>
                                    <td>
                                        @if($task->household)
                                            <a href="{{ route('admin.households.show', $task->household) }}" class="text-body">{{ $task->household->name }}</a>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td>
                                        @if($task->createdBy)
                                            <a href="{{ route('admin.users.show', $task->createdBy) }}" class="text-body">{{ $task->createdBy->name }}</a>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td>
                                        @php $cls = match($task->status) { 'completed' => 'success', 'in_progress' => 'info', default => 'warning' }; @endphp
                                        <span class="badge badge-soft-{{ $cls }}">{{ ucfirst(str_replace('_', ' ', $task->status)) }}</span>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center text-muted">No tasks assigned</td></tr>
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
                    <h4 class="card-title mb-0 flex-grow-1">Payments ({{ $user->payments->count() }})</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Household</th>
                                    <th>Amount</th>
                                    <th>Gateway</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="text-center" style="width:60px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($user->payments as $payment)
                                <tr>
                                    <td>
                                        @if($payment->household)
                                            <a href="{{ route('admin.households.show', $payment->household) }}" class="text-body">{{ $payment->household->name }}</a>
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

    {{-- Subscriptions --}}
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Subscriptions ({{ $user->subscriptions->count() }})</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Household</th>
                                    <th>Plan</th>
                                    <th>Status</th>
                                    <th>Period End</th>
                                    <th class="text-center" style="width:60px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($user->subscriptions as $sub)
                                <tr>
                                    <td>
                                        @if($sub->household)
                                            <a href="{{ route('admin.households.show', $sub->household) }}" class="text-body">{{ $sub->household->name }}</a>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td>{{ $sub->plan->name ?? 'N/A' }}</td>
                                    <td>
                                        @php $cls = match($sub->status) { 'active' => 'success', 'trial' => 'info', 'expired' => 'danger', default => 'warning' }; @endphp
                                        <span class="badge badge-soft-{{ $cls }}">{{ ucfirst($sub->status) }}</span>
                                    </td>
                                    <td>{{ $sub->current_period_end ? $sub->current_period_end->format('d M Y') : '-' }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('admin.subscriptions.show', $sub) }}" class="btn btn-sm btn-light"><i class="ri-eye-line"></i></a>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="text-center text-muted">No subscriptions</td></tr>
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
