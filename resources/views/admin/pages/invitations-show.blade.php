@extends('admin.pages.adminmaster')
@section('title', 'Invitation Detail')
@section('content')
<div class="page-head">
    <div><h1>Invitation #{{ $record->id }}</h1><p>{{ $record->invited_email }}</p></div>
    <div class="actions"><a class="btn" href="{{ route('admin.page', ['page' => 'invitations']) }}">← Back to Invitations</a></div>
</div>
<div class="card">
    <div class="card-head"><h3>Details</h3></div>
    <div class="table-wrap">
        <table class="table">
            <tbody>
                <tr><td>Email</td><td><b>{{ $record->invited_email }}</b></td></tr>
                <tr><td>Household</td><td>@if($record->household)<a href="{{ route('admin.page.detail', ['page' => 'households', 'id' => $record->household->id]) }}">{{ $record->household->name }}</a>@else — @endif</td></tr>
                <tr><td>Household code</td><td><code>{{ $record->household->invite_code ?? '—' }}</code></td></tr>
                <tr><td>Status</td><td><span class="badge {{ $record->status === 'accepted' ? 'success' : ($record->status === 'expired' ? 'danger' : 'warning') }}">{{ ucfirst($record->status) }}</span></td></tr>
                <tr><td>Expires</td><td>{{ $record->expires_at ? $record->expires_at->format('d M Y H:i') : '—' }}</td></tr>
                <tr><td>Accepted</td><td>{{ $record->accepted_at ? $record->accepted_at->format('d M Y H:i') : '—' }}</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
