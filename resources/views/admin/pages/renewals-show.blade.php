@extends('admin.pages.adminmaster')
@section('title', 'Renewal Detail')
@section('content')
<div class="page-head">
    <div><h1>{{ $record->title }}</h1><p>Renewal detail</p></div>
    <div class="actions"><a class="btn" href="{{ route('admin.page', ['page' => 'renewals']) }}">← Back to Renewals</a></div>
</div>
<div class="card">
    <div class="card-head"><h3>Details</h3></div>
    <div class="table-wrap">
        <table class="table">
            <tbody>
                <tr><td>Title</td><td><b>{{ $record->title }}</b></td></tr>
                <tr><td>Category</td><td>{{ ucfirst(str_replace('_',' ',$record->category ?? '—')) }}</td></tr>
                <tr><td>Amount</td><td>${{ number_format($record->amount ?? 0, 2) }}</td></tr>
                <tr><td>Status</td><td><span class="badge {{ $record->status === 'completed' ? 'success' : 'warning' }}">{{ ucfirst($record->status) }}</span></td></tr>
                <tr><td>Household</td><td>@if($record->household)<a href="{{ route('admin.page.detail', ['page' => 'households', 'id' => $record->household->id]) }}">{{ $record->household->name }}</a>@else — @endif</td></tr>
                <tr><td>Due date</td><td>{{ $record->due_date ? $record->due_date->format('d M Y') : '—' }}</td></tr>
                <tr><td>Notes</td><td>{{ $record->notes ?? '—' }}</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
