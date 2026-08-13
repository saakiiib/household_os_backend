@extends('admin.pages.adminmaster')
@section('title', 'Audit Log Detail')
@section('content')
<div class="page-head">
    <div><h1>Log #{{ $record->id }}</h1><p>{{ $record->description ?? 'Activity' }}</p></div>
    <div class="actions"><a class="btn" href="{{ route('admin.page', ['page' => 'audit-logs']) }}">← Back to Audit Logs</a></div>
</div>
<div class="card">
    <div class="card-head"><h3>Details</h3></div>
    <div class="table-wrap">
        <table class="table">
            <tbody>
                <tr><td>Description</td><td><b>{{ $record->description ?? '—' }}</b></td></tr>
                <tr><td>Subject</td><td>{{ class_basename($record->subject_type ?? '') }} #{{ $record->subject_id ?? '' }}</td></tr>
                <tr><td>Causer</td><td>{{ $record->user->name ?? 'System' }}</td></tr>
                <tr><td>Created</td><td>{{ $record->created_at->format('d M Y H:i') }}</td></tr>
                @if($record->properties)
                <tr><td>Properties</td><td><code>{{ json_encode($record->properties) }}</code></td></tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
