@extends('admin.pages.master')
@section('title', 'Audit Log Detail')
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">Log #{{ $record->id }}</h4>
                <div class="page-title-right"><ol class="breadcrumb m-0"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li><li class="breadcrumb-item"><a href="{{ route('admin.page', ['page' => 'audit-logs']) }}">Audit Logs</a></li><li class="breadcrumb-item active">Detail</li></ol></div>
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
                            <tr><th style="width:200px">Description</th><td>{{ $record->description ?? '—' }}</td></tr>
                            <tr><th>Subject</th><td>{{ class_basename($record->subject_type ?? '') }} #{{ $record->subject_id ?? '' }}</td></tr>
                            <tr><th>Causer</th><td>{{ $record->user->name ?? 'System' }}</td></tr>
                            <tr><th>Created</th><td>{{ $record->created_at->format('d M Y H:i') }}</td></tr>
                            @if($record->properties)
                            <tr><th>Properties</th><td><code>{{ json_encode($record->properties) }}</code></td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
