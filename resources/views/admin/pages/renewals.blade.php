@extends('admin.pages.adminmaster')
@section('title', 'Renewal Management')
@section('content')
<div class="page-head">
    <div><h1>Renewals</h1><p>Upcoming and overdue renewals across all households.</p></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('admin.page', ['page' => 'renewals']) }}">+ New Renewal</a></div>
</div>

<div class="card">
    <div class="card-head"><h3>All Renewals</h3><span class="badge neutral">{{ $renewals->total() }} total</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Title</th><th>Household</th><th>Category</th><th>Amount</th><th>Due</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($renewals as $renewal)
                <tr>
                    <td>{{ $renewal->title ?? ucfirst(str_replace('_',' ',$renewal->category ?? '')) }}</td>
                    <td>{{ $renewal->household->name ?? '—' }}</td>
                    <td>{{ ucfirst(str_replace('_',' ',$renewal->category ?? '—')) }}</td>
                    <td>${{ number_format($renewal->amount ?? 0, 2) }}</td>
                    <td>{{ $renewal->due_date ? $renewal->due_date->format('d M Y') : '—' }}</td>
                    <td><span class="badge {{ $renewal->status === 'completed' ? 'success' : ($renewal->due_date && $renewal->due_date->isPast() ? 'danger' : 'warning') }}">{{ ucfirst($renewal->status) }}</span></td>
                    <td><a class="btn" href="{{ route('admin.page.detail', ['page' => 'renewals', 'id' => $renewal->id]) }}">View</a></td>
                </tr>
                @empty
                <tr><td colspan="6" class="empty">No renewals yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-foot">{{ $renewals->links() }}</div>
</div>
@endsection
