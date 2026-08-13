@extends('admin.pages.master')
@section('title', 'Subscriptions')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Subscription Management</h4>
                <div class="page-title-right d-flex gap-2 align-items-center">
                    <a href="#" class="btn btn-primary btn-sm"><i class="ri-vip-crown-line"></i> Add new</a>
                </div>
            </div>
        </div>
    </div>

    @php
        $subStatusColors = [
            'active'=>'success','trialing'=>'info','trial'=>'info','past_due'=>'warning',
            'grace_period'=>'warning','cancelled'=>'danger','expired'=>'danger','pending'=>'warning',
        ];
    @endphp

    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Total Subscriptions</p>
                            <h4 class="mb-0">{{ $subscriptions->total() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-primary text-primary rounded fs-3"><i class="ri-vip-crown-line"></i></span>
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
                            <p class="text-muted mb-2 text-truncate">Active</p>
                            <h4 class="mb-0">{{ collect($subscriptions->items())->where('status', 'active')->count() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-success text-success rounded fs-3"><i class="ri-checkbox-circle-line"></i></span>
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
                            <p class="text-muted mb-2 text-truncate">Trialing</p>
                            <h4 class="mb-0">{{ collect($subscriptions->items())->filter(function($s){ return in_array($s->status, ['trialing','trial']); })->count() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-info text-info rounded fs-3"><i class="ri-timer-line"></i></span>
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
                            <p class="text-muted mb-2 text-truncate">Cancelled / Expired</p>
                            <h4 class="mb-0">{{ collect($subscriptions->items())->filter(function($s){ return in_array($s->status, ['cancelled','expired']); })->count() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-danger text-danger rounded fs-3"><i class="ri-close-circle-line"></i></span>
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
                    <h4 class="card-title mb-0 flex-grow-1">All Subscriptions</h4>
                    <span class="badge bg-soft-primary fs-12">{{ $subscriptions->total() }} total</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Household</th>
                                    <th>Plan</th>
                                    <th>Status</th>
                                    <th>Price</th>
                                    <th>Renews At</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($subscriptions as $subscription)
                                <tr>
                                    <td class="fw-medium">{{ $subscription->household->name ?? '—' }}</td>
                                    <td>{{ $subscription->plan->name ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-soft-{{ $subStatusColors[$subscription->status] ?? 'secondary' }} text-{{ $subStatusColors[$subscription->status] ?? 'secondary' }}">
                                            {{ ucfirst(str_replace('_', ' ', $subscription->status)) }}
                                        </span>
                                    </td>
                                    <td class="fw-medium">
                                        @if($subscription->plan)
                                            £{{ number_format($subscription->plan->monthly_price, 2) }}<span class="text-muted">/mo</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-muted">
                                        {{ ($subscription->current_period_end ?? $subscription->expires_at) ? ($subscription->current_period_end ?? $subscription->expires_at)->format('d M Y') : '—' }}
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.subscriptions.show', $subscription->id) }}" class="btn btn-sm btn-soft-primary">View</a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="ri-vip-crown-line fs-24 d-block mb-2"></i>
                                        No records found
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $subscriptions->links() }}</div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
