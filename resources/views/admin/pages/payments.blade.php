@extends('admin.pages.master')
@section('title', 'Payments')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Payment Management</h4>
                <div class="page-title-right d-flex gap-2 align-items-center">
                    <a href="#" class="btn btn-primary btn-sm"><i class="ri-bank-card-line"></i> Add new</a>
                </div>
            </div>
        </div>
    </div>

    @php
        $payStatusColors = [
            'paid'=>'success','succeeded'=>'success','completed'=>'success',
            'pending'=>'warning','processing'=>'info',
            'failed'=>'danger','refunded'=>'secondary','cancelled'=>'danger',
        ];
    @endphp

    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-2 text-truncate">Total Payments</p>
                            <h4 class="mb-0">{{ $payments->total() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-primary text-primary rounded fs-3"><i class="ri-bank-card-line"></i></span>
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
                            <p class="text-muted mb-2 text-truncate">Paid</p>
                            <h4 class="mb-0">{{ $payments->where('status', 'paid')->count() }}</h4>
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
                            <p class="text-muted mb-2 text-truncate">Failed</p>
                            <h4 class="mb-0">{{ $payments->where('status', 'failed')->count() }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-danger text-danger rounded fs-3"><i class="ri-close-circle-line"></i></span>
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
                            <p class="text-muted mb-2 text-truncate">Total Collected</p>
                            <h4 class="mb-0">£{{ number_format($payments->where('status', 'paid')->sum('amount'), 2) }}</h4>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title bg-soft-info text-info rounded fs-3"><i class="ri-money-pound-circle-line"></i></span>
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
                    <h4 class="card-title mb-0 flex-grow-1">All Payments</h4>
                    <span class="badge bg-soft-primary fs-12">{{ $payments->total() }} total</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Customer</th>
                                    <th>Household</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($payments as $payment)
                                <tr>
                                    <td class="fw-medium">{{ $payment->user->name ?? '—' }}</td>
                                    <td>{{ $payment->household->name ?? '—' }}</td>
                                    <td class="fw-medium">£{{ number_format($payment->amount ?? 0, 2) }}</td>
                                    <td>
                                        <span class="text-capitalize">{{ $payment->payment_method ?? $payment->gateway ?? '—' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-soft-{{ $payStatusColors[$payment->status] ?? 'secondary' }} text-{{ $payStatusColors[$payment->status] ?? 'secondary' }}">
                                            {{ ucfirst($payment->status) }}
                                        </span>
                                    </td>
                                    <td class="text-muted">{{ $payment->created_at->format('d M Y') }}</td>
                                    <td>
                                        <a href="{{ route('admin.payments.show', $payment->id) }}" class="btn btn-sm btn-soft-primary">View</a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="ri-bank-card-line fs-24 d-block mb-2"></i>
                                        No records found
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $payments->links() }}</div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
