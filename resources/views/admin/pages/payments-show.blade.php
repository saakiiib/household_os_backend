@extends('admin.pages.adminmaster')
@section('title', 'Payment Detail')
@section('content')
<div class="page-head">
    <div><h1>Payment #{{ $record->id }}</h1><p>${{ number_format($record->amount, 2) }}</p></div>
    <div class="actions"><a class="btn" href="{{ route('admin.page', ['page' => 'payments']) }}">← Back to Payments</a></div>
</div>
<div class="card">
    <div class="card-head"><h3>Details</h3></div>
    <div class="table-wrap">
        <table class="table">
            <tbody>
                <tr><td>Amount</td><td><b>${{ number_format($record->amount, 2) }}</b></td></tr>
                <tr><td>Currency</td><td>{{ $record->currency ?? 'USD' }}</td></tr>
                <tr><td>Gateway</td><td>{{ ucfirst($record->gateway ?? '—') }}</td></tr>
                <tr><td>Payment method</td><td>{{ ucfirst($record->payment_method ?? '—') }}</td></tr>
                <tr><td>Status</td><td><span class="badge {{ $record->status === 'completed' ? 'success' : 'warning' }}">{{ ucfirst($record->status) }}</span></td></tr>
                <tr><td>User</td><td>@if($record->user)<a href="{{ route('admin.page.detail', ['page' => 'users', 'id' => $record->user->id]) }}">{{ $record->user->name }}</a>@else N/A @endif</td></tr>
                <tr><td>Household</td><td>@if($record->household)<a href="{{ route('admin.page.detail', ['page' => 'households', 'id' => $record->household->id]) }}">{{ $record->household->name }}</a>@else — @endif</td></tr>
                <tr><td>Subscription</td><td>@if($record->subscription)#{{ $record->subscription->id }}@else — @endif</td></tr>
                <tr><td>Failure reason</td><td>{{ $record->failure_reason ?? '—' }}</td></tr>
                <tr><td>Date</td><td>{{ $record->created_at->format('d M Y H:i') }}</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
