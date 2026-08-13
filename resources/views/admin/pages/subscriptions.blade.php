@extends('admin.pages.adminmaster')
@section('title', 'Subscription Management')
@section('content')
<div class="page-head">
    <div><h1>Subscriptions</h1><p>Active, trial and cancelled household subscriptions.</p></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('admin.page', ['page' => 'subscriptions']) }}">+ New Subscription</a></div>
</div>

<div class="card">
    <div class="card-head"><h3>All Subscriptions</h3><span class="badge neutral">{{ $subscriptions->total() }} total</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Household</th><th>Plan</th><th>Status</th><th>Period End</th><th>Method</th><th></th></tr></thead>
            <tbody>
                @forelse($subscriptions as $subscription)
                <tr>
                    <td>{{ $subscription->household->name ?? '—' }}</td>
                    <td>{{ $subscription->plan->name ?? '—' }}</td>
                    <td><span class="badge {{ $subscription->status === 'active' ? 'success' : ($subscription->status === 'cancelled' ? 'danger' : 'warning') }}">{{ ucfirst($subscription->status) }}</span></td>
                    <td>{{ $subscription->current_period_end ? $subscription->current_period_end->format('d M Y') : '—' }}</td>
                    <td>{{ ucfirst($subscription->payment_method ?? '—') }}</td>
                    <td><a class="btn" href="{{ route('admin.page.detail', ['page' => 'subscriptions', 'id' => $subscription->id]) }}">View</a></td>
                </tr>
                @empty
                <tr><td colspan="5" class="empty">No subscriptions yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-foot">{{ $subscriptions->links() }}</div>
</div>
@endsection
