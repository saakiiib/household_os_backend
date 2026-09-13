@forelse($ticket->messages as $message)
    @php $admin = $message->sender_type === 'admin'; @endphp
    <div class="d-flex mb-4 {{ $admin ? 'justify-content-end' : '' }}" data-message-id="{{ $message->id }}">
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
@empty
    <div class="text-center text-muted py-5">No messages yet.</div>
@endforelse
