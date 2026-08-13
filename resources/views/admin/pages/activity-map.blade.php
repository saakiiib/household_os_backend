@extends('admin.pages.master')
@section('title', 'Live Activity Map')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <div>
                    <h4 class="mb-sm-0 font-size-18">Live Activity Map</h4>
                    <p class="text-muted mb-0">Current online users, countries, devices and activity volumes.</p>
                </div>
                <div class="page-title-right d-flex gap-2 align-items-center">
                    <span class="badge bg-soft-success text-success fs-12"><i class="ri-record-circle-line"></i> Live</span>
                    <button class="btn btn-soft-primary btn-sm"><i class="ri-download-line"></i> Export</button>
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
                            <p class="text-muted mb-2 text-truncate">Total Events</p>
                            <h4 class="mb-0">{{ $activities->count() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-primary text-primary rounded fs-3"><i class="ri-pulse-line"></i></span>
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
                            <p class="text-muted mb-2 text-truncate">Unique Actors</p>
                            <h4 class="mb-0">{{ $activities->pluck('causer_id')->filter()->unique()->count() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-info text-info rounded fs-3"><i class="ri-user-line"></i></span>
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
                            <p class="text-muted mb-2 text-truncate">Latest Event</p>
                            <h4 class="mb-0">{{ $activities->max('created_at') ? $activities->max('created_at')->diffForHumans() : '—' }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-success text-success rounded fs-3"><i class="ri-time-line"></i></span>
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
                            <p class="text-muted mb-2 text-truncate">System Events</p>
                            <h4 class="mb-0">{{ $activities->whereNull('causer_id')->count() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-warning text-warning rounded fs-3"><i class="ri-settings-3-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <div>
                        <h4 class="card-title mb-0">Live Activity</h4>
                        <p class="text-muted mb-0 fs-13">Latest platform events</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="timeline timeline-primary">
                        @forelse ($activities as $activity)
                            <div class="timeline-item">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-medium">{{ $activity->description ?? '—' }}</span>
                                        <small class="text-muted">{{ $activity->created_at ? $activity->created_at->diffForHumans() : '' }}</small>
                                    </div>
                                    <p class="text-muted mb-0 fs-13">
                                        {{ optional($activity->causer)->name ?? 'System' }} ·
                                        {{ class_basename($activity->subject_type ?? '') }} #{{ $activity->subject_id ?? '' }}
                                    </p>
                                </div>
                            </div>
                        @empty
                            <p class="text-center text-muted mb-0">No activity recorded yet</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <h4 class="card-title mb-0">Priority Findings</h4>
                    <span class="badge bg-soft-danger text-danger ms-auto">Attention</span>
                </div>
                <div class="card-body">
                    <div class="vstack gap-3">
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2">
                            <span class="text-muted">Households with no emergency contact</span>
                            <span class="fw-medium">42</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2">
                            <span class="text-muted">Passports expiring within 12 months</span>
                            <span class="fw-medium">210</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-2">
                            <span class="text-muted">Premium trials likely to convert</span>
                            <span class="fw-medium">68%</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="text-muted">Users inactive for 60+ days</span>
                            <span class="fw-medium">120</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
