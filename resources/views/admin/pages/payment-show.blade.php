@extends('admin.pages.master')
@section('title', 'Payment #' . $payment->id)
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">
                    <a href="{{ route('admin.payments.index') }}" class="text-muted"><i class="ri-arrow-left-line"></i> Payments</a>
                    &nbsp;/&nbsp;Payment #{{ $payment->id }}
                </h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Payment Details</h5>
                    <div class="mt-3">
                        <p class="mb-2"><strong>Amount:</strong> <span class="fs-18 fw-bold">${{ number_format($payment->amount, 2) }}</span></p>
                        <p class="mb-2"><strong>Status:</strong>
                            @php $cls = match($payment->status) { 'completed' => 'success', 'failed' => 'danger', default => 'warning' }; @endphp
                            <span class="badge badge-soft-{{ $cls }}">{{ ucfirst($payment->status) }}</span>
                        </p>
                        <p class="mb-2"><strong>Gateway:</strong> {{ ucfirst($payment->gateway) }}</p>
                        <p class="mb-2"><strong>Currency:</strong> {{ strtoupper($payment->currency ?? 'usd') }}</p>
                        @if($payment->payment_method)
                            <p class="mb-2"><strong>Payment Method:</strong> {{ $payment->payment_method }}</p>
                        @endif
                        @if($payment->gateway_payment_id)
                            <p class="mb-2"><strong>Gateway ID:</strong> <code>{{ $payment->gateway_payment_id }}</code></p>
                        @endif
                        <p class="mb-2"><strong>Date:</strong> {{ $payment->created_at->format('d M Y H:i') }}</p>
                        @if($payment->failure_reason)
                            <p class="mb-0"><strong>Failure Reason:</strong> <span class="text-danger">{{ $payment->failure_reason }}</span></p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Related Info</h5>
                    <div class="mt-3">
                        <p class="mb-2"><strong>User:</strong>
                            @if($payment->user)
                                <a href="{{ route('admin.users.show', $payment->user) }}">{{ $payment->user->name }}</a>
                                <span class="text-muted">({{ $payment->user->email }})</span>
                            @else
                                N/A
                            @endif
                        </p>
                        <p class="mb-2"><strong>Household:</strong>
                            @if($payment->household)
                                <a href="{{ route('admin.households.show', $payment->household) }}">{{ $payment->household->name }}</a>
                            @else
                                N/A
                            @endif
                        </p>
                        <p class="mb-2"><strong>Plan:</strong> {{ $payment->plan->name ?? 'N/A' }}</p>
                        @if($payment->subscription)
                            <p class="mb-0"><strong>Subscription:</strong>
                                <a href="{{ route('admin.subscriptions.show', $payment->subscription) }}">Subscription #{{ $payment->subscription->id }}</a>
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
