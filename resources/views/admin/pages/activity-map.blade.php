@extends('admin.pages.adminmaster')
@section('title', 'Live Activity')
@section('content')
<div class="page-head">
    <div><h1>Live Activity</h1><p>Real-time stream of platform events.</p></div>
</div>

<div class="card">
    <div class="card-head"><h3>Event Stream</h3><span class="badge neutral">{{ $activities->count() }} recent</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Event</th><th>Subject</th><th>By</th><th>When</th></tr></thead>
            <tbody>
                @forelse($activities as $activity)
                <tr>
                    <td>{{ $activity->description ?? '—' }}</td>
                    <td>{{ class_basename($activity->subject_type ?? '') }} #{{ $activity->subject_id ?? '' }}</td>
                    <td>{{ $activity->user->name ?? 'System' }}</td>
                    <td>{{ $activity->created_at->diffForHumans() }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="empty">No recent activity</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
