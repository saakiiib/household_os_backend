@extends('admin.pages.adminmaster')
@section('title', 'Subscription Detail')
@section('content')
<div class="page-head">
    <div><h1>Subscription #{{ $record->id }}</h1><p>{{ $record->plan->name ?? 'Plan' }}</p></div>
    <div class="actions"><a class="btn" href="{{ route('admin.page', ['page' => 'subscriptions']) }}">← Back to Subscriptions</a></div>
</div>
<div class="card">
    <div class="card-head"><h3>Details</h3></div>
    <div class="table-wrap">
        <table class="table">
            <tbody>
                <tr><td>Plan</td><td><b>{{ $record->plan->name ?? '—' }}</b></td></tr>
                <tr><td>Status</td><td><span class="badge {{ $record->status === 'active' ? 'success' : ($record->status === 'cancelled' ? 'danger' : 'warning') }}">{{ ucfirst($record->status) }}</span></td></tr>
                <tr><td>Household</td><td>@if($record->household)<a href="{{ route('admin.page.detail', ['page' => 'households', 'id' => $record->household->id]) }}">{{ $record->household->name }}</a>@else — @endif</td></tr>
                <tr><td>User</td><td>@if($record->user)<a href="{{ route('admin.page.detail', ['page' => 'users', 'id' => $record->user->id]) }}">{{ $record->user->name }}</a>@else — @endif</td></tr>
                <tr><td>Payment method</td><td>{{ ucfirst($record->payment_method ?? '—') }}</td></tr>
                <tr><td>Current period</td><td>{{ $record->current_period_start ? $record->current_period_start->format('d M Y') : '—' }} → {{ $record->current_period_end ? $record->current_period_end->format('d M Y') : '—' }}</td></tr>
                <tr><td>Cancelled</td><td>{{ $record->cancelled_at ? $record->cancelled_at->format('d M Y') : '—' }}</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
