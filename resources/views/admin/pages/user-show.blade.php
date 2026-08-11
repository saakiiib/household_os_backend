@extends('admin.pages.master')
@section('title', 'User Details')
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="mt-3">{{ $user->name }}</h5>
                    <p class="text-muted">{{ $user->email }}</p>
                    <span class="badge badge-soft-{{ $user->status === 'active' ? 'success' : 'danger' }} fs-12">
                        {{ ucfirst($user->status) }}
                    </span>
                    @if($user->is_admin)
                        <span class="badge badge-soft-primary fs-12">Admin</span>
                    @endif
                    <p class="text-muted mt-2 mb-0">Joined {{ $user->created_at->format('M d, Y') }}</p>
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Households</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($user->households as $household)
                                <tr>
                                    <td>{{ $household->name }}</td>
                                    <td>{{ ucfirst($household->pivot->role) }}</td>
                                    <td>
                                        <span class="badge badge-soft-{{ $household->pivot->status === 'active' ? 'success' : 'warning' }}">
                                            {{ ucfirst($household->pivot->status) }}
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="3" class="text-muted">No households</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Recent Payments</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Amount</th>
                                    <th>Gateway</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($user->payments->take(10) as $payment)
                                <tr>
                                    <td>${{ number_format($payment->amount, 2) }}</td>
                                    <td>{{ ucfirst($payment->gateway) }}</td>
                                    <td>
                                        <span class="badge badge-soft-{{ $payment->status === 'completed' ? 'success' : 'warning' }}">
                                            {{ ucfirst($payment->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $payment->created_at->format('M d, Y') }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-muted">No payments</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
