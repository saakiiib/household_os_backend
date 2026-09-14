@extends('admin.pages.master')
@section('title', 'Support ' . $ticket->ticket_number)
@section('content')
<div class="container-fluid">
    <div class="row"><div class="col-12"><div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div><a href="{{ route('admin.support.index') }}" class="text-muted"><i class="ri-arrow-left-line"></i> Support Communications</a><h4 class="mt-2 mb-0">{{ $ticket->ticket_number }} — {{ $ticket->subject }}</h4></div>
    </div></div></div>

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0">Conversation</h4>
                    <small id="supportLiveState" class="text-muted"><i class="ri-refresh-line"></i> Live updates on</small>
                </div>
                <div id="supportConversationScroll" class="card-body" style="max-height:650px;overflow:auto">
                    <div id="supportMessageList">
                        @include('admin.pages._support-message-list', ['ticket' => $ticket])
                    </div>
                </div>
            </div>

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
                @if(!empty($ticket->diagnostic_context))
                <div class="alert alert-light border py-2 px-3">
                    <strong>App diagnostics (customer approved)</strong><br>
                    @foreach($ticket->diagnostic_context as $key => $value)
                        <small><span class="text-muted">{{ ucwords(str_replace('_',' ', $key)) }}:</span> {{ $value }}</small><br>
                    @endforeach
                </div>
                @endif
                <p><strong>Created:</strong><br>{{ $ticket->created_at->format('d M Y H:i') }}</p>
                <p><strong>Live status:</strong><br><span id="supportLiveStatus">{{ \App\Models\SupportTicket::STATUSES[$ticket->status] ?? $ticket->status }}</span></p>
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

<script>
(function () {
    const pollUrl = @json(route('admin.support.poll', $ticket));
    const list = document.getElementById('supportMessageList');
    const scroller = document.getElementById('supportConversationScroll');
    const liveState = document.getElementById('supportLiveState');
    const liveStatus = document.getElementById('supportLiveStatus');
    let latestMessageId = {{ (int) optional($ticket->messages->last())->id }};
    let inFlight = false;

    function nearBottom() {
        if (!scroller) return true;
        return (scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight) < 140;
    }

    function scrollToBottom() {
        if (scroller) scroller.scrollTop = scroller.scrollHeight;
    }

    async function pollSupport() {
        if (inFlight || document.hidden) return;
        inFlight = true;
        try {
            const response = await fetch(pollUrl, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();
            const nextId = Number(data.latest_message_id || 0);
            const shouldStick = nearBottom();

            if (nextId !== latestMessageId && typeof data.html === 'string') {
                list.innerHTML = data.html;
                latestMessageId = nextId;
                if (shouldStick) scrollToBottom();
            }

            if (liveStatus && data.status_label) liveStatus.textContent = data.status_label;
            if (liveState) {
                liveState.className = 'text-success';
                liveState.innerHTML = '<i class="ri-checkbox-circle-line"></i> Live';
            }
        } catch (e) {
            if (liveState) {
                liveState.className = 'text-muted';
                liveState.innerHTML = '<i class="ri-wifi-off-line"></i> Reconnecting…';
            }
        } finally {
            inFlight = false;
        }
    }

    scrollToBottom();
    const timer = setInterval(pollSupport, 4000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) pollSupport();
    });
    window.addEventListener('focus', pollSupport);
    window.addEventListener('beforeunload', function () { clearInterval(timer); });
})();
</script>
@endsection
