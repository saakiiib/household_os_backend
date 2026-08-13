@extends('admin.pages.adminmaster')
@section('title', 'Task Detail')
@section('content')
<div class="page-head">
    <div><h1>{{ $record->title }}</h1><p>Task detail</p></div>
    <div class="actions"><a class="btn" href="{{ route('admin.page', ['page' => 'tasks']) }}">← Back to Tasks</a></div>
</div>
<div class="card">
    <div class="card-head"><h3>Details</h3></div>
    <div class="table-wrap">
        <table class="table">
            <tbody>
                <tr><td>Title</td><td><b>{{ $record->title }}</b></td></tr>
                <tr><td>Description</td><td>{{ $record->description ?? '—' }}</td></tr>
                <tr><td>Status</td><td><span class="badge {{ $record->status === 'completed' ? 'success' : ($record->status === 'in_progress' ? 'warning' : 'neutral') }}">{{ ucfirst(str_replace('_',' ',$record->status)) }}</span></td></tr>
                <tr><td>Household</td><td>@if($record->household)<a href="{{ route('admin.page.detail', ['page' => 'households', 'id' => $record->household->id]) }}">{{ $record->household->name }}</a>@else — @endif</td></tr>
                <tr><td>Due</td><td>{{ $record->due_date ? $record->due_date->format('d M Y') : '—' }} @if($record->due_time) {{ $record->due_time->format('H:i') }} @endif</td></tr>
                <tr><td>Notes</td><td>{{ $record->notes ?? '—' }}</td></tr>
                <tr><td>Completed</td><td>{{ $record->completed_at ? $record->completed_at->format('d M Y H:i') : '—' }}</td></tr>
                <tr><td>Created</td><td>{{ $record->created_at->format('d M Y H:i') }}</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
