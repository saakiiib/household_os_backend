@extends('admin.pages.master')
@section('title', 'Household Details')
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h5>{{ $household->name }}</h5>
                    <p class="text-muted">Created by {{ $household->creator->name ?? 'N/A' }}</p>
                    <p class="text-muted mb-0">Invite Code: <code>{{ $household->invite_code }}</code></p>
                    <p class="text-muted">Created: {{ $household->created_at->format('M d, Y') }}</p>
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Members ({{ $household->members->count() }})</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered align-middle mb-0">
                            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr></thead>
                            <tbody>
                                @forelse($household->members as $member)
                                <tr>
                                    <td>{{ $member->name }}</td>
                                    <td>{{ $member->email }}</td>
                                    <td>{{ ucfirst($member->pivot->role) }}</td>
                                    <td><span class="badge badge-soft-success">{{ ucfirst($member->pivot->status) }}</span></td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-muted">No members</td></tr>
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
