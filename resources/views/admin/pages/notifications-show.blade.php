@extends('admin.pages.master')
@section('title', 'Notification Detail')
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">{{ $record->title }}</h4>
                <div class="page-title-right"><ol class="breadcrumb m-0"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li><li class="breadcrumb-item"><a href="{{ route('admin.page', ['page' => 'notifications']) }}">Notifications</a></li><li class="breadcrumb-item active">Detail</li></ol></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h4 class="card-title mb-0">Details</h4></div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr><th style="width:200px">Title</th><td>{{ $record->title }}</td></tr>
                            <tr><th>Body</th><td>{{ $record->body ?? '—' }}</td></tr>
                            <tr><th>Type</th><td>{{ $record->type ?? '—' }}</td></tr>
                            <tr><th>User</th><td>@if($record->user)<a href="{{ route('admin.users.show', $record->user) }}">{{ $record->user->name }}</a>@else — @endif</td></tr>
                            <tr><th>Read</th><td>@if($record->read_at)<span class="badge bg-soft-success">Read</span>@else<span class="badge bg-soft-warning">Unread</span>@endif</td></tr>
                            <tr><th>Data</th><td><code>{{ json_encode($record->data) }}</code></td></tr>
                            <tr><th>Created</th><td>{{ $record->created_at->format('d M Y H:i') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
