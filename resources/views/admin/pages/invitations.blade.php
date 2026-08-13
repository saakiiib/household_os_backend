@extends('admin.pages.adminmaster')
@section('title', 'Invitations')
@section('content')
<div class="page-head">
    <div><h1>Invitations</h1><p>Track household invite codes and pending acceptances.</p></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('admin.page', ['page' => 'invitations']) }}">+ Send Invite</a></div>
</div>

<div class="card">
    <div class="card-head"><h3>All Invitations</h3><span class="badge neutral">{{ $invitations->total() }} total</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Email</th><th>Household</th><th>Code</th><th>Status</th><th>Expires</th><th></th></tr></thead>
            <tbody>
                @forelse($invitations as $invitation)
                <tr>
                    <td>{{ $invitation->invited_email ?? '—' }}</td>
                    <td>{{ $invitation->household->name ?? '—' }}</td>
                    <td><code>{{ $invitation->household->invite_code ?? '—' }}</code></td>
                    <td>
                        @if($invitation->status === 'accepted' || $invitation->accepted_at)
                            <span class="badge success">Accepted</span>
                        @elseif($invitation->status === 'expired' || ($invitation->expires_at && $invitation->expires_at->isPast()))
                            <span class="badge danger">Expired</span>
                        @else
                            <span class="badge warning">Pending</span>
                        @endif
                    </td>
                    <td>{{ $invitation->expires_at ? $invitation->expires_at->format('d M Y') : '—' }}</td>
                    <td><a class="btn" href="{{ route('admin.page.detail', ['page' => 'invitations', 'id' => $invitation->id]) }}">View</a></td>
                </tr>
                @empty
                <tr><td colspan="5" class="empty">No invitations yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-foot">{{ $invitations->links() }}</div>
</div>
@endsection
