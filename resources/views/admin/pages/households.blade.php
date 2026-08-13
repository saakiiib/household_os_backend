@extends('admin.pages.adminmaster')
@section('title', 'Household Management')
@section('content')
<div class="page-head">
    <div><h1>Households</h1><p>Every household, its members and associated subscription.</p></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('admin.page', ['page' => 'households']) }}">+ New Household</a></div>
</div>

<div class="card">
    <div class="card-head"><h3>All Households</h3><span class="badge neutral">{{ $households->total() }} total</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Name</th><th>Invite Code</th><th>Members</th><th>Created</th><th></th></tr></thead>
            <tbody>
                @forelse($households as $household)
                <tr>
                    <td>{{ $household->name }}</td>
                    <td><code>{{ $household->invite_code }}</code></td>
                    <td>{{ $household->members_count }}</td>
                    <td>{{ $household->created_at->format('d M Y') }}</td>
                    <td><a class="btn" href="{{ route('admin.page.detail', ['page' => 'households', 'id' => $household->id]) }}">Details</a></td>
                </tr>
                @empty
                <tr><td colspan="5" class="empty">No households yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-foot">{{ $households->links() }}</div>
</div>
@endsection
