@extends('admin.pages.master')
@section('title', $task->title)
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">
                    <a href="{{ route('admin.tasks.index') }}" class="text-muted"><i class="ri-arrow-left-line"></i> Tasks</a>
                    &nbsp;/&nbsp;{{ $task->title }}
                </h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Task Details</h5>
                    <div class="mt-3">
                        <p class="mb-2"><strong>Title:</strong> {{ $task->title }}</p>
                        <p class="mb-2"><strong>Household:</strong>
                            @if($task->household)
                                <a href="{{ route('admin.households.show', $task->household) }}">{{ $task->household->name }}</a>
                            @else
                                N/A
                            @endif
                        </p>
                        <p class="mb-2"><strong>Created By:</strong>
                            @if($task->createdBy)
                                <a href="{{ route('admin.users.show', $task->createdBy) }}">{{ $task->createdBy->name }}</a>
                            @else
                                N/A
                            @endif
                        </p>
                        <p class="mb-2"><strong>Assigned To:</strong>
                            @if($task->assignedUser)
                                <a href="{{ route('admin.users.show', $task->assignedUser) }}">{{ $task->assignedUser->name }}</a>
                            @else
                                <span class="text-muted">Unassigned</span>
                            @endif
                        </p>
                        <p class="mb-2"><strong>Status:</strong>
                            @php $cls = match($task->status) { 'completed' => 'success', 'in_progress' => 'info', default => 'warning' }; @endphp
                            <span class="badge badge-soft-{{ $cls }}">{{ ucfirst(str_replace('_', ' ', $task->status)) }}</span>
                        </p>
                        <p class="mb-2"><strong>Priority:</strong>
                            @php $pcl = match($task->priority) { 'high' => 'danger', 'medium' => 'warning', default => 'secondary' }; @endphp
                            <span class="badge badge-soft-{{ $pcl }}">{{ ucfirst($task->priority ?? 'Normal') }}</span>
                        </p>
                        <p class="mb-2"><strong>Due Date:</strong> {{ $task->due_date ? $task->due_date->format('d M Y') : '-' }}</p>
                        @if($task->completed_at)
                            <p class="mb-0"><strong>Completed:</strong> {{ $task->completed_at->format('d M Y H:i') }}</p>
                        @endif
                    </div>
                </div>
            </div>

            @if($task->parent)
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Parent Task</h5>
                    <a href="{{ route('admin.tasks.show', $task->parent) }}" class="fw-semibold">{{ $task->parent->title }}</a>
                </div>
            </div>
            @endif
        </div>

        <div class="col-xl-8">
            @if($task->children->count())
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Sub-Tasks ({{ $task->children->count() }})</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Task</th>
                                    <th>Assigned To</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($task->children as $child)
                                <tr>
                                    <td><a href="{{ route('admin.tasks.show', $child) }}" class="fw-semibold text-body">{{ $child->title }}</a></td>
                                    <td>
                                        @if($child->assignedUser)
                                            <a href="{{ route('admin.users.show', $child->assignedUser) }}" class="text-body">{{ $child->assignedUser->name }}</a>
                                        @else
                                            <span class="text-muted">Unassigned</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php $cls = match($child->status) { 'completed' => 'success', 'in_progress' => 'info', default => 'warning' }; @endphp
                                        <span class="badge badge-soft-{{ $cls }}">{{ ucfirst(str_replace('_', ' ', $child->status)) }}</span>
                                    </td>
                                    <td>{{ $child->due_date ? $child->due_date->format('d M Y') : '-' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            @if($siblings->count())
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Other Tasks in {{ $task->household->name ?? 'Household' }}</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Task</th>
                                    <th>Assigned To</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($siblings as $sibling)
                                <tr>
                                    <td><a href="{{ route('admin.tasks.show', $sibling) }}" class="fw-semibold text-body">{{ $sibling->title }}</a></td>
                                    <td>
                                        @if($sibling->assignedUser)
                                            <a href="{{ route('admin.users.show', $sibling->assignedUser) }}" class="text-body">{{ $sibling->assignedUser->name }}</a>
                                        @else
                                            <span class="text-muted">Unassigned</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php $cls = match($sibling->status) { 'completed' => 'success', 'in_progress' => 'info', default => 'warning' }; @endphp
                                        <span class="badge badge-soft-{{ $cls }}">{{ ucfirst(str_replace('_', ' ', $sibling->status)) }}</span>
                                    </td>
                                    <td>{{ $sibling->due_date ? $sibling->due_date->format('d M Y') : '-' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
