@extends('admin.pages.adminmaster')
@section('title', 'Document Detail')
@section('content')
<div class="page-head">
    <div><h1>{{ $record->title }}</h1><p>Document detail</p></div>
    <div class="actions"><a class="btn" href="{{ route('admin.page', ['page' => 'documents']) }}">← Back to Documents</a></div>
</div>
<div class="card">
    <div class="card-head"><h3>Details</h3></div>
    <div class="table-wrap">
        <table class="table">
            <tbody>
                <tr><td>Title</td><td><b>{{ $record->title }}</b></td></tr>
                <tr><td>Description</td><td>{{ $record->description ?? '—' }}</td></tr>
                <tr><td>Category</td><td>{{ ucfirst(str_replace('_',' ',$record->category ?? '—')) }}</td></tr>
                <tr><td>Status</td><td><span class="badge neutral">{{ ucfirst($record->status ?? 'active') }}</span></td></tr>
                <tr><td>Household</td><td>@if($record->household)<a href="{{ route('admin.page.detail', ['page' => 'households', 'id' => $record->household->id]) }}">{{ $record->household->name }}</a>@else — @endif</td></tr>
                <tr><td>Due date</td><td>{{ $record->due_date ? $record->due_date->format('d M Y') : '—' }}</td></tr>
                <tr><td>Files</td><td>{{ $record->files->count() }}</td></tr>
            </tbody>
        </table>
    </div>
</div>
@if($record->files->count())
<div class="card" style="margin-top:16px">
    <div class="card-head"><h3>Attached Files</h3></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>File</th><th>Type</th><th>Uploaded</th></tr></thead>
            <tbody>
                @foreach($record->files as $file)
                <tr><td>{{ $file->original_filename ?? basename($file->file_path ?? '') }}</td><td>{{ $file->mime_type ?? '—' }}</td><td>{{ $file->created_at->format('d M Y') }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
