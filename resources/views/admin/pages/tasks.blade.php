@extends('admin.pages.master')
@section('title', 'Tasks')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Task Management</h4>
                <div class="page-title-right d-flex gap-2 align-items-center">
                    <a href="#" class="btn btn-primary btn-sm"><i class="ri-task-line"></i> Add new</a>
                </div>
            </div>
        </div>
    </div>

    @php
        $priorityColors = [
            'high'=>'danger','medium'=>'warning','low'=>'success',
        ];
        $taskStatusColors = [
            'pending'=>'warning','in_progress'=>'info','completed'=>'success',
        ];
    @endphp

    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Total Tasks</p>
                            <h4 class="mb-0">{{ $tasks->total() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-primary text-primary rounded fs-3"><i class="ri-task-line"></i></span>
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
                            <h4 class="mb-0">{{ collect($tasks->items())->where('status', 'completed')->count() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-success text-success rounded fs-3"><i class="ri-check-double-line"></i></span>
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
                            <p class="text-muted mb-2 text-truncate">In Progress</p>
                            <h4 class="mb-0">{{ collect($tasks->items())->where('status', 'in_progress')->count() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-info text-info rounded fs-3"><i class="ri-loader-line"></i></span>
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
                            <h4 class="mb-0">{{ collect($tasks->items())->where('status', 'pending')->count() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-warning text-warning rounded fs-3"><i class="ri-time-line"></i></span>
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
                    <h4 class="card-title mb-0 flex-grow-1">All Tasks</h4>
                    <span class="badge bg-soft-primary fs-12">{{ $tasks->total() }} total</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Task</th>
                                    <th>Household</th>
                                    <th>Assigned To</th>
                                    <th>Due Date</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($tasks as $task)
                                <tr>
                                    <td class="fw-medium">{{ $task->title }}</td>
                                    <td>{{ $task->household->name ?? '—' }}</td>
                                    <td>{{ $task->assignedUser->name ?? 'Unassigned' }}</td>
                                    <td class="text-muted">{{ $task->due_date ? $task->due_date->format('d M Y') : '—' }}</td>
                                    <td>
                                        <span class="badge bg-soft-{{ $priorityColors[$task->priority] ?? 'secondary' }} text-{{ $priorityColors[$task->priority] ?? 'secondary' }}">
                                            {{ ucfirst($task->priority) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-soft-{{ $taskStatusColors[$task->status] ?? 'secondary' }} text-{{ $taskStatusColors[$task->status] ?? 'secondary' }}">
                                            {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.tasks.show', $task->id) }}" class="btn btn-sm btn-soft-primary">View</a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="ri-task-line fs-24 d-block mb-2"></i>
                                        No records found
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $tasks->links() }}</div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
