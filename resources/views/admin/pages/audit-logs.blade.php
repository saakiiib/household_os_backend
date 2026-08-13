@extends('admin.pages.adminmaster')
@section('title', 'Audit Logs')
@section('content')
<div class="page-head">
    <div><h1>Audit Logs</h1><p>Chronological record of administrative and system actions.</p></div>
    <div class="actions"><a class="btn" href="{{ route('admin.page', ['page' => 'audit-logs']) }}">Export</a></div>
</div>

<div class="card">
    <div class="card-head"><h3>Activity Log</h3><span class="badge neutral">{{ $logs->total() }} entries</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Action</th><th>Subject</th><th>By</th><th>When</th><th></th></tr></thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td>{{ $log->description ?? '—' }}</td>
                    <td>{{ class_basename($log->subject_type ?? '') }} #{{ $log->subject_id ?? '' }}</td>
                    <td>{{ $log->user->name ?? 'System' }}</td>
                    <td>{{ $log->created_at->format('d M Y H:i') }}</td>
                    <td><a class="btn" href="{{ route('admin.page.detail', ['page' => 'audit-logs', 'id' => $log->id]) }}">View</a></td>
                </tr>
                @empty
                <tr><td colspan="4" class="empty">No activity logged yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-foot">{{ $logs->links() }}</div>
</div>
@endsection
