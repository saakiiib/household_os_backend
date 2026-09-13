@extends('admin.pages.master')
@section('title', 'Support Communications')
@section('content')
<div class="container-fluid">
    <div class="row"><div class="col-12"><div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div><h4 class="mb-sm-0">Support Communications</h4><p class="text-muted mb-0">Customer queries and ongoing conversations from HouseholdOS.</p></div>
    </div></div></div>

    <div class="row">
        @foreach([
            ['Total', $stats['total'], 'primary', 'ri-customer-service-2-line'],
            ['Waiting for Support', $stats['waiting_support'], 'warning', 'ri-time-line'],
            ['Waiting for Customer', $stats['waiting_customer'], 'info', 'ri-chat-3-line'],
            ['Resolved / Closed', $stats['resolved'], 'success', 'ri-checkbox-circle-line'],
        ] as [$label,$value,$color,$icon])
        <div class="col-xl-3 col-md-6"><div class="card"><div class="card-body d-flex justify-content-between align-items-center">
            <div><p class="text-muted mb-2">{{ $label }}</p><h4 class="mb-0">{{ number_format($value) }}</h4></div>
            <span class="avatar-title bg-soft-{{ $color }} text-{{ $color }} rounded fs-3" style="width:42px;height:42px"><i class="{{ $icon }}"></i></span>
        </div></div></div>
        @endforeach
    </div>

    <div class="card"><div class="card-body">
        <form method="GET" class="row g-2 mb-4">
            <div class="col-lg-4"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search ticket, subject, customer..."></div>
            <div class="col-lg-2"><select name="status" class="form-select"><option value="">All statuses</option>@foreach(\App\Models\SupportTicket::STATUSES as $v=>$l)<option value="{{ $v }}" @selected(request('status')===$v)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-lg-2"><select name="category" class="form-select"><option value="">All categories</option>@foreach(\App\Models\SupportTicket::CATEGORIES as $v=>$l)<option value="{{ $v }}" @selected(request('category')===$v)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-lg-2"><select name="priority" class="form-select"><option value="">All priorities</option>@foreach(\App\Models\SupportTicket::PRIORITIES as $v=>$l)<option value="{{ $v }}" @selected(request('priority')===$v)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-lg-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1">Filter</button><a href="{{ route('admin.support.index') }}" class="btn btn-light">Reset</a></div>
        </form>
        <div class="table-responsive"><table class="table table-hover align-middle">
            <thead class="table-light"><tr><th>Ticket</th><th>Customer</th><th>Category</th><th>Subject</th><th>Priority</th><th>Status</th><th>Last activity</th><th></th></tr></thead>
            <tbody>
            @forelse($tickets as $ticket)
                @php
                    $statusColor = match($ticket->status){'open','waiting_support'=>'warning','waiting_customer'=>'info','resolved'=>'success','closed'=>'secondary',default=>'secondary'};
                    $priorityColor = match($ticket->priority){'urgent'=>'danger','high'=>'warning','low'=>'secondary',default=>'primary'};
                    $unread = $ticket->unreadForAdminCount();
                @endphp
                <tr class="{{ $unread ? 'table-warning' : '' }}">
                    <td><a class="fw-medium" href="{{ route('admin.support.show',$ticket) }}">{{ $ticket->ticket_number }}</a>@if($unread)<span class="badge bg-danger ms-1">{{ $unread }} new</span>@endif</td>
                    <td><div>{{ $ticket->user?->name ?? 'Deleted user' }}</div><small class="text-muted">{{ $ticket->user?->email }}</small></td>
                    <td>{{ \App\Models\SupportTicket::CATEGORIES[$ticket->category] ?? $ticket->category }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($ticket->subject, 45) }}</td>
                    <td><span class="badge bg-soft-{{ $priorityColor }} text-{{ $priorityColor }}">{{ ucfirst($ticket->priority) }}</span></td>
                    <td><span class="badge bg-soft-{{ $statusColor }} text-{{ $statusColor }}">{{ \App\Models\SupportTicket::STATUSES[$ticket->status] ?? $ticket->status }}</span></td>
                    <td>{{ $ticket->last_message_at?->format('d M Y H:i') ?? $ticket->created_at->format('d M Y H:i') }}</td>
                    <td><a href="{{ route('admin.support.show',$ticket) }}" class="btn btn-sm btn-soft-primary"><i class="ri-eye-line"></i> Open</a></td>
                </tr>
            @empty<tr><td colspan="8" class="text-center text-muted py-5">No support conversations found.</td></tr>@endforelse
            </tbody>
        </table></div>
        <div class="mt-3">{{ $tickets->links() }}</div>
    </div></div>
</div>
@endsection
