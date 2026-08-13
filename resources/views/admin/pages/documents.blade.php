@extends('admin.pages.master')
@section('title', 'Documents')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Document Management</h4>
                <div class="page-title-right d-flex gap-2 align-items-center">
                    <a href="#" class="btn btn-primary btn-sm"><i class="ri-upload-2-line"></i> Add new</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Total Documents</p>
                            <h4 class="mb-0">{{ $documents->total() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-primary text-primary rounded fs-3"><i class="ri-folder-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Due Soon</p>
                            <h4 class="mb-0">{{ $documents->filter(function($d){ return $d->due_date && $d->due_date->isFuture(); })->count() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-warning text-warning rounded fs-3"><i class="ri-calendar-event-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Overdue</p>
                            <h4 class="mb-0">{{ $documents->filter(function($d){ return $d->due_date && $d->due_date->isPast(); })->count() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-danger text-danger rounded fs-3"><i class="ri-alarm-warning-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">On This Page</p>
                            <h4 class="mb-0">{{ $documents->count() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-info text-info rounded fs-3"><i class="ri-pages-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">All Documents</h4>
                    <span class="badge bg-soft-primary fs-12">{{ $documents->total() }} total</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Document</th>
                                    <th>Household</th>
                                    <th>Type</th>
                                    <th>Due Date</th>
                                    <th>Uploaded</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($documents as $document)
                                <tr>
                                    <td class="fw-medium">{{ $document->title }}</td>
                                    <td>{{ $document->household->name ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-soft-secondary text-secondary text-capitalize">
                                            {{ str_replace('_', ' ', $document->category) }}
                                        </span>
                                    </td>
                                    <td class="text-muted">
                                        @if($document->due_date)
                                            {{ $document->due_date->format('d M Y') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-muted">{{ $document->created_at->format('d M Y') }}</td>
                                    <td>
                                        <a href="{{ route('admin.documents.show', $document->id) }}" class="btn btn-sm btn-soft-primary">View</a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="ri-folder-open-line fs-24 d-block mb-2"></i>
                                        No records found
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $documents->links() }}</div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
