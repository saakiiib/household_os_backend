@extends('admin.pages.master')
@section('title', 'Invitation Detail')
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">Invitation #{{ $record->id }}</h4>
                <div class="page-title-right"><ol class="breadcrumb m-0"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li><li class="breadcrumb-item"><a href="{{ route('admin.page', ['page' => 'invitations']) }}">Invitations</a></li><li class="breadcrumb-item active">Detail</li></ol></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h4 class="card-title mb-0">Details</h4></div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr><th style="width:200px">Email</th><td>{{ $record->invited_email }}</td></tr>
                            <tr><th>Household</th><td>@if($record->household)<a href="{{ route('admin.households.show', $record->household) }}">{{ $record->household->name }}</a>@else — @endif</td></tr>
                            <tr><th>Household Code</th><td><code>{{ $record->household->invite_code ?? '—' }}</code></td></tr>
                            <tr><th>Status</th><td>
                                @if($record->status === 'accepted')
                                    <span class="badge bg-soft-success">Accepted</span>
                                @elseif($record->status === 'expired')
                                    <span class="badge bg-soft-danger">Expired</span>
                                @else
                                    <span class="badge bg-soft-warning">Pending</span>
                                @endif
                            </td></tr>
                            <tr><th>Expires</th><td>{{ $record->expires_at ? $record->expires_at->format('d M Y H:i') : '—' }}</td></tr>
                            <tr><th>Accepted</th><td>{{ $record->accepted_at ? $record->accepted_at->format('d M Y H:i') : '—' }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
