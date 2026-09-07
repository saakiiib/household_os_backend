@extends('admin.pages.master')
@section('title', 'Subscription #' . $subscription->id)
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">
                    <a href="{{ route('admin.subscriptions.index') }}" class="text-muted"><i class="ri-arrow-left-line"></i> Subscriptions</a>
                    &nbsp;/&nbsp;Subscription #{{ $subscription->id }}
                </h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Subscription Details</h5>
                    <div class="mt-3">
                        <p class="mb-2"><strong>User:</strong>
                            @if($subscription->user)
                                <a href="{{ route('admin.users.show', $subscription->user) }}">{{ $subscription->user->name }}</a>
                            @else
                                N/A
                            @endif
                        </p>
                        <p class="mb-2"><strong>Household:</strong>
                            @if($subscription->household)
                                <a href="{{ route('admin.households.show', $subscription->household) }}">{{ $subscription->household->name }}</a>
                            @else
                                N/A
                            @endif
                        </p>
                        <p class="mb-2"><strong>Plan:</strong> {{ $subscription->plan->name ?? 'N/A' }}</p>
                        <p class="mb-2"><strong>Status:</strong>
                            @php $cls = match($subscription->status) { 'active' => 'success', 'trial' => 'info', 'expired' => 'danger', default => 'warning' }; @endphp
                            <span class="badge badge-soft-{{ $cls }}">{{ ucfirst($subscription->status) }}</span>
                        </p>
                        <p class="mb-2"><strong>Period Start:</strong> {{ $subscription->current_period_start ? $subscription->current_period_start->format('d M Y') : '-' }}</p>
                        <p class="mb-2"><strong>Period End:</strong> {{ $subscription->current_period_end ? $subscription->current_period_end->format('d M Y') : '-' }}</p>
                        @if($subscription->trial_started_at)
                            <p class="mb-2"><strong>Trial Started:</strong> {{ $subscription->trial_started_at->format('d M Y') }}</p>
                        @endif
                        @if($subscription->trial_ends_at)
                            <p class="mb-2"><strong>Trial Ends:</strong> {{ $subscription->trial_ends_at->format('d M Y') }}</p>
                        @endif
                        @if($subscription->cancelled_at)
                            <p class="mb-0"><strong>Cancelled:</strong> {{ $subscription->cancelled_at->format('d M Y') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1">Payments ({{ $subscription->payments->count() }})</h4>
                </div>
                <div class="card-body">
                    @if($subscription->payments->count())
                    <div class="table-responsive">
                        <table class="table table-centered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Amount</th>
                                    <th>Gateway</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="text-center" style="width:60px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($subscription->payments as $payment)
                                <tr>
                                    <td>${{ number_format($payment->amount, 2) }}</td>
                                    <td>{{ ucfirst($payment->gateway) }}</td>
                                    <td>
                                        @php $cls = match($payment->status) { 'completed' => 'success', 'failed' => 'danger', default => 'warning' }; @endphp
                                        <span class="badge badge-soft-{{ $cls }}">{{ ucfirst($payment->status) }}</span>
                                    </td>
                                    <td>{{ $payment->created_at->format('d M Y') }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('admin.payments.show', $payment) }}" class="btn btn-sm btn-light"><i class="ri-eye-line"></i></a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-center text-muted mb-0">No payments for this subscription</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
