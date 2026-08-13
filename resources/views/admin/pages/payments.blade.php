@extends('admin.pages.adminmaster')
@section('title', 'Payment Management')
@section('content')
<div class="page-head">
    <div><h1>Payments</h1><p>All transactions, gateways and statuses.</p></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('admin.page', ['page' => 'payments']) }}">Export</a></div>
</div>

<div class="card">
    <div class="card-head"><h3>All Payments</h3><span class="badge neutral">{{ $payments->total() }} total</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>User</th><th>Household</th><th>Amount</th><th>Gateway</th><th>Status</th><th>Date</th><th></th></tr></thead>
            <tbody>
                @forelse($payments as $payment)
                <tr>
                    <td>{{ $payment->user->name ?? 'N/A' }}</td>
                    <td>{{ $payment->household->name ?? '—' }}</td>
                    <td>${{ number_format($payment->amount, 2) }}</td>
                    <td>{{ ucfirst($payment->gateway ?? '—') }}</td>
                    <td><span class="badge {{ $payment->status === 'completed' ? 'success' : 'warning' }}">{{ ucfirst($payment->status) }}</span></td>
                    <td>{{ $payment->created_at->format('d M Y') }}</td>
                    <td><a class="btn" href="{{ route('admin.page.detail', ['page' => 'payments', 'id' => $payment->id]) }}">View</a></td>
                </tr>
                @empty
                <tr><td colspan="6" class="empty">No payments yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-foot">{{ $payments->links() }}</div>
</div>
@endsection
