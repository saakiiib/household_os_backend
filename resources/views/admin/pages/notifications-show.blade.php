@extends('admin.pages.adminmaster')
@section('title', 'Notification Detail')
@section('content')
<div class="page-head">
    <div><h1>{{ $record->title }}</h1><p>Notification detail</p></div>
    <div class="actions"><a class="btn" href="{{ route('admin.page', ['page' => 'notifications']) }}">← Back to Notifications</a></div>
</div>
<div class="card">
    <div class="card-head"><h3>Details</h3></div>
    <div class="table-wrap">
        <table class="table">
            <tbody>
                <tr><td>Title</td><td><b>{{ $record->title }}</b></td></tr>
                <tr><td>Body</td><td>{{ $record->body ?? '—' }}</td></tr>
                <tr><td>Type</td><td>{{ $record->type ?? '—' }}</td></tr>
                <tr><td>User</td><td>@if($record->user)<a href="{{ route('admin.page.detail', ['page' => 'users', 'id' => $record->user->id]) }}">{{ $record->user->name }}</a>@else — @endif</td></tr>
                <tr><td>Read</td><td><span class="badge {{ $record->read_at ? 'success' : 'warning' }}">{{ $record->read_at ? 'Read' : 'Unread' }}</span></td></tr>
                <tr><td>Data</td><td><code>{{ json_encode($record->data) }}</code></td></tr>
                <tr><td>Created</td><td>{{ $record->created_at->format('d M Y H:i') }}</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
