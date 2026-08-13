@extends('admin.pages.adminmaster')
@section('title', 'Task Management')
@section('content')
<div class="page-head">
    <div><h1>Tasks</h1><p>Household tasks, assignments and completion status.</p></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('admin.page', ['page' => 'tasks']) }}">+ New Task</a></div>
</div>

<div class="card">
    <div class="card-head"><h3>All Tasks</h3><span class="badge neutral">{{ $tasks->total() }} total</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Title</th><th>Household</th><th>Status</th><th>Due</th><th>Created</th><th></th></tr></thead>
            <tbody>
                @forelse($tasks as $task)
                <tr>
                    <td>{{ $task->title }}</td>
                    <td>{{ $task->household->name ?? '—' }}</td>
                    <td><span class="badge {{ $task->status === 'completed' ? 'success' : ($task->status === 'in_progress' ? 'warning' : 'neutral') }}">{{ ucfirst(str_replace('_',' ',$task->status)) }}</span></td>
                    <td>{{ $task->due_date ? $task->due_date->format('d M Y') : '—' }}</td>
                    <td>{{ $task->created_at->format('d M Y') }}</td>
                    <td><a class="btn" href="{{ route('admin.page.detail', ['page' => 'tasks', 'id' => $task->id]) }}">View</a></td>
                </tr>
                @empty
                <tr><td colspan="5" class="empty">No tasks yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-foot">{{ $tasks->links() }}</div>
</div>
@endsection
