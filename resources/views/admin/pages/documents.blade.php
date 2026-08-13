@extends('admin.pages.adminmaster')
@section('title', 'Document Management')
@section('content')
<div class="page-head">
    <div><h1>Documents</h1><p>Stored documents across all households.</p></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('admin.page', ['page' => 'documents']) }}">+ Upload</a></div>
</div>

<div class="card">
    <div class="card-head"><h3>All Documents</h3><span class="badge neutral">{{ $documents->total() }} total</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Title</th><th>Household</th><th>Category</th><th>Status</th><th>Created</th><th></th></tr></thead>
            <tbody>
                @forelse($documents as $document)
                <tr>
                    <td>{{ $document->title }}</td>
                    <td>{{ $document->household->name ?? '—' }}</td>
                    <td>{{ ucfirst(str_replace('_',' ',$document->category ?? '—')) }}</td>
                    <td><span class="badge neutral">{{ ucfirst($document->status ?? 'active') }}</span></td>
                    <td>{{ $document->created_at->format('d M Y') }}</td>
                    <td><a class="btn" href="{{ route('admin.page.detail', ['page' => 'documents', 'id' => $document->id]) }}">View</a></td>
                </tr>
                @empty
                <tr><td colspan="5" class="empty">No documents yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-foot">{{ $documents->links() }}</div>
</div>
@endsection
