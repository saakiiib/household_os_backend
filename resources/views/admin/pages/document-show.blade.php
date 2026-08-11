@extends('admin.pages.master')
@section('title', $document->title)
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">
                    <a href="{{ route('admin.documents.index') }}" class="text-muted"><i class="ri-arrow-left-line"></i> Documents</a>
                    &nbsp;/&nbsp;{{ $document->title }}
                </h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Document Details</h5>
                    <div class="mt-3">
                        <p class="mb-2"><strong>Title:</strong> {{ $document->title }}</p>
                        <p class="mb-2"><strong>Category:</strong> {{ ucfirst(str_replace('_', ' ', $document->category)) }}</p>
                        <p class="mb-2"><strong>Household:</strong>
                            @if($document->household)
                                <a href="{{ route('admin.households.show', $document->household) }}">{{ $document->household->name }}</a>
                            @else
                                N/A
                            @endif
                        </p>
                        <p class="mb-2"><strong>Created By:</strong>
                            @if($document->createdBy)
                                <a href="{{ route('admin.users.show', $document->createdBy) }}">{{ $document->createdBy->name }}</a>
                            @else
                                N/A
                            @endif
                        </p>
                        <p class="mb-2"><strong>Due Date:</strong> {{ $document->due_date ? $document->due_date->format('d M Y') : '-' }}</p>
                        <p class="mb-0"><strong>Created:</strong> {{ $document->created_at->format('d M Y') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Files ({{ $document->files->count() }})</h4>
                </div>
                <div class="card-body">
                    @if($document->files->count())
                    <div class="table-responsive">
                        <table class="table table-centered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>File Name</th>
                                    <th>Size</th>
                                    <th>Type</th>
                                    <th>Uploaded</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($document->files as $file)
                                <tr>
                                    <td><i class="ri-file-text-line me-2 text-primary"></i>{{ $file->file_name ?? $file->original_name ?? 'File' }}</td>
                                    <td>{{ $file->size ? number_format($file->size / 1024 / 1024, 2) . ' MB' : '-' }}</td>
                                    <td>{{ $file->mime_type ?? '-' }}</td>
                                    <td>{{ $file->created_at->format('d M Y') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-center text-muted mb-0">No files uploaded</p>
                    @endif
                </div>
            </div>

            @if($siblingDocs->count())
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Other Documents in {{ $document->household->name ?? 'Household' }}</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Document</th>
                                    <th>Category</th>
                                    <th>Files</th>
                                    <th>Due Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($siblingDocs as $sibling)
                                <tr>
                                    <td><a href="{{ route('admin.documents.show', $sibling) }}" class="fw-semibold text-body">{{ $sibling->title }}</a></td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $sibling->category)) }}</td>
                                    <td>{{ $sibling->files->count() }}</td>
                                    <td>{{ $sibling->due_date ? $sibling->due_date->format('d M Y') : '-' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
