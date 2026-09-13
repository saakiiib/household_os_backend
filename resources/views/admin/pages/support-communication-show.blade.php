@extends('admin.pages.master')
@section('title', 'Support ' . $ticket->ticket_number)
@section('content')
<div class="container-fluid">
    <div class="row"><div class="col-12"><div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div><a href="{{ route('admin.support.index') }}" class="text-muted"><i class="ri-arrow-left-line"></i> Support Communications</a><h4 class="mt-2 mb-0">{{ $ticket->ticket_number }} — {{ $ticket->subject }}</h4></div>
    </div></div></div>

    <div class="row">
        <div class="col-xl-8">
            <div class="card"><div class="card-header"><h4 class="card-title mb-0">Conversation</h4></div><div class="card-body" style="max-height:650px;overflow:auto">
                @foreach($ticket->messages as $message)
                    @php $admin = $message->sender_type === 'admin'; @endphp
                    <div class="d-flex mb-4 {{ $admin ? 'justify-content-end' : '' }}">
                        <div style="max-width:80%">
                            <div class="small text-muted mb-1 {{ $admin ? 'text-end' : '' }}">
                                {{ $message->is_internal ? 'Internal note' : ($admin ? 'HouseholdOS Support' : ($ticket->user?->name ?? 'Customer')) }} · {{ $message->created_at->format('d M Y H:i') }}
                            </div>
                            <div class="rounded p-3 {{ $message->is_internal ? 'bg-warning-subtle border border-warning' : ($admin ? 'bg-primary text-white' : 'bg-light') }}">
                                @if($message->body)<div style="white-space:pre-wrap">{{ $message->body }}</div>@endif
                                @if($message->attachments->count())
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                    @foreach($message->attachments as $attachment)
                                        <a href="{{ route('admin.support.attachment',$attachment) }}" target="_blank" class="btn btn-sm {{ $admin && !$message->is_internal ? 'btn-light' : 'btn-soft-primary' }}"><i class="ri-image-line"></i> {{ \Illuminate\Support\Str::limit($attachment->original_name,25) }}</a>
                                    @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div></div>

            @if($ticket->status !== 'closed')
            <div class="card"><div class="card-header"><h4 class="card-title mb-0">Reply</h4></div><div class="card-body">
                <form method="POST" enctype="multipart/form-data" action="{{ route('admin.support.reply',$ticket) }}">
                    @csrf
                    <textarea name="message" class="form-control" rows="5" maxlength="4000" placeholder="Write a helpful reply..."></textarea>
                    <div class="row mt-3"><div class="col-md-7"><label class="form-label">Images (optional)</label><input type="file" name="attachments[]" class="form-control" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" multiple><small class="text-muted">Up to 3 images, 5 MB each.</small></div>
                    <div class="col-md-5 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="internal" value="1" id="internalNote"><label class="form-check-label" for="internalNote">Internal note (customer cannot see)</label></div></div></div>
                    <button class="btn btn-primary mt-3"><i class="ri-send-plane-line"></i> Send reply</button>
                </form>
            </div></div>
            @endif
        </div>

        <div class="col-xl-4">
            <div class="card"><div class="card-header"><h4 class="card-title mb-0">Ticket Details</h4></div><div class="card-body">
                <p><strong>Customer:</strong><br>{{ $ticket->user?->name ?? 'Deleted user' }}<br><span class="text-muted">{{ $ticket->user?->email }}</span></p>
                <p><strong>Household:</strong><br>{{ $ticket->household?->name ?? '—' }}</p>
                <p><strong>Category:</strong><br>{{ \App\Models\SupportTicket::CATEGORIES[$ticket->category] ?? $ticket->category }}</p>
                <p><strong>Created:</strong><br>{{ $ticket->created_at->format('d M Y H:i') }}</p>
                <hr>
                <form method="POST" action="{{ route('admin.support.update',$ticket) }}">@csrf @method('PATCH')
                    <label class="form-label">Status</label><select class="form-select mb-3" name="status">@foreach(\App\Models\SupportTicket::STATUSES as $v=>$l)<option value="{{ $v }}" @selected($ticket->status===$v)>{{ $l }}</option>@endforeach</select>
                    <label class="form-label">Priority</label><select class="form-select mb-3" name="priority">@foreach(\App\Models\SupportTicket::PRIORITIES as $v=>$l)<option value="{{ $v }}" @selected($ticket->priority===$v)>{{ $l }}</option>@endforeach</select>
                    <button class="btn btn-soft-primary w-100">Update ticket</button>
                </form>
            </div></div>
        </div>
    </div>
</div>
@endsection
