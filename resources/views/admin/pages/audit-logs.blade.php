@extends('admin.pages.master')
@section('title', 'Audit Logs')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <div>
                    <h4 class="mb-sm-0 font-size-18">Audit Logs</h4>
                    <p class="text-muted mb-0">Immutable history of sensitive admin, user and system actions.</p>
                </div>
                <div class="page-title-right d-flex gap-2 align-items-center">
                    <button class="btn btn-soft-primary btn-sm"><i class="ri-download-line"></i> Export</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Activity</h4>
                    <span class="badge bg-soft-primary fs-12">{{ $logs->total() ?? $logs->count() }} entries</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-nowrap align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Time</th>
                                    <th>Actor</th>
                                    <th>Action</th>
                                    <th>Target</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($logs as $log)
                                    <tr>
                                        <td class="text-muted">{{ $log->created_at ? $log->created_at->format('d M Y H:i') : '—' }}</td>
                                        <td class="fw-medium">{{ optional($log->causer)->name ?? 'System' }}</td>
                                        <td>{{ $log->description ?? '—' }}</td>
                                        <td>{{ class_basename($log->subject_type ?? '') }} #{{ $log->subject_id ?? '' }}</td>
                                        <td>
                                            <a href="{{ route('admin.page.detail', ['page' => 'audit-logs', 'id' => $log->id]) }}" class="btn btn-sm btn-soft-primary">View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No audit events recorded yet</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if (method_exists($logs, 'links'))
                        <div class="mt-3">{{ $logs->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
