<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportAttachment;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SupportController extends Controller
{
    private const MAX_ATTACHMENTS = 3;
    private const MAX_ATTACHMENT_KB = 5120; // 5 MB each

    public function meta()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'categories' => collect(SupportTicket::CATEGORIES)->map(fn($label, $value) => ['value' => $value, 'label' => $label])->values(),
                'statuses' => collect(SupportTicket::STATUSES)->map(fn($label, $value) => ['value' => $value, 'label' => $label])->values(),
                'max_attachments' => self::MAX_ATTACHMENTS,
                'max_attachment_mb' => 5,
            ],
        ]);
    }

    public function index(Request $request)
    {
        $query = SupportTicket::where('user_id', $request->user()->id)
            ->with(['messages' => fn($q) => $q->where('is_internal', false)->latest('id')->limit(1)])
            ->latest('last_message_at');

        if ($request->filled('category') && array_key_exists($request->category, SupportTicket::CATEGORIES)) {
            $query->where('category', $request->category);
        }
        if ($request->filled('status') && array_key_exists($request->status, SupportTicket::STATUSES)) {
            $query->where('status', $request->status);
        }

        $tickets = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => collect($tickets->items())->map(fn($ticket) => $this->ticketData($ticket, false))->values(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(SupportTicket::CATEGORIES))],
            'subject' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:4000'],
            'attachments' => ['nullable', 'array', 'max:' . self::MAX_ATTACHMENTS],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:' . self::MAX_ATTACHMENT_KB],
        ]);

        $user = $request->user();
        $household = $user->activeHousehold();

        $ticket = DB::transaction(function () use ($request, $validated, $user, $household) {
            $ticket = SupportTicket::create([
                'user_id' => $user->id,
                'household_id' => $household?->id,
                'category' => $validated['category'],
                'subject' => trim($validated['subject']),
                'status' => 'open',
                'priority' => 'normal',
                'last_message_at' => now(),
                'user_last_read_at' => now(),
            ]);

            $message = SupportMessage::create([
                'support_ticket_id' => $ticket->id,
                'sender_user_id' => $user->id,
                'sender_type' => 'user',
                'body' => trim($validated['message']),
                'is_internal' => false,
            ]);

            $this->storeAttachments($request, $ticket, $message, $user->id);
            return $ticket;
        });

        return response()->json([
            'success' => true,
            'message' => 'Your support query has been sent.',
            'data' => $this->ticketData($ticket->fresh(['messages.attachments']), true),
        ], 201);
    }

    public function show(Request $request, SupportTicket $ticket)
    {
        $this->ensureOwner($request, $ticket);
        $ticket->update(['user_last_read_at' => now()]);
        $ticket->load(['messages' => fn($q) => $q->where('is_internal', false)->with('attachments')->orderBy('id')]);

        return response()->json(['success' => true, 'data' => $this->ticketData($ticket, true)]);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $this->ensureOwner($request, $ticket);
        abort_if($ticket->status === 'closed', 422, 'This support conversation is closed.');

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:4000', 'required_without:attachments'],
            'attachments' => ['nullable', 'array', 'max:' . self::MAX_ATTACHMENTS],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:' . self::MAX_ATTACHMENT_KB],
        ]);

        DB::transaction(function () use ($request, $validated, $ticket) {
            $message = SupportMessage::create([
                'support_ticket_id' => $ticket->id,
                'sender_user_id' => $request->user()->id,
                'sender_type' => 'user',
                'body' => trim((string)($validated['message'] ?? '')) ?: null,
                'is_internal' => false,
            ]);
            $this->storeAttachments($request, $ticket, $message, $request->user()->id);
            $ticket->update([
                'status' => 'waiting_support',
                'last_message_at' => now(),
                'user_last_read_at' => now(),
                'resolved_at' => null,
                'closed_at' => null,
            ]);
        });

        return $this->show($request, $ticket->fresh());
    }

    public function close(Request $request, SupportTicket $ticket)
    {
        $this->ensureOwner($request, $ticket);
        $ticket->update(['status' => 'closed', 'closed_at' => now(), 'last_message_at' => now()]);
        return response()->json(['success' => true, 'message' => 'Conversation closed.']);
    }

    public function reopen(Request $request, SupportTicket $ticket)
    {
        $this->ensureOwner($request, $ticket);
        $ticket->update(['status' => 'waiting_support', 'closed_at' => null, 'resolved_at' => null, 'last_message_at' => now()]);
        return response()->json(['success' => true, 'message' => 'Conversation reopened.']);
    }

    public function attachment(Request $request, SupportAttachment $attachment)
    {
        $attachment->loadMissing('message');
        $ticket = $attachment->ticket;
        abort_unless($ticket && $ticket->user_id === $request->user()->id, 403);
        // Internal admin-note attachments must never be exposed to customers,
        // even if they guess an attachment ID belonging to their own ticket.
        abort_if($attachment->message?->is_internal, 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream', 'Cache-Control' => 'private, max-age=300']
        );
    }

    private function ensureOwner(Request $request, SupportTicket $ticket): void
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
    }

    private function storeAttachments(Request $request, SupportTicket $ticket, SupportMessage $message, int $userId): void
    {
        foreach ($request->file('attachments', []) as $file) {
            if (!$file || !$file->isValid()) continue;
            $path = $file->store('support/' . $ticket->id, 'local');
            SupportAttachment::create([
                'support_ticket_id' => $ticket->id,
                'support_message_id' => $message->id,
                'uploaded_by_user_id' => $userId,
                'disk' => 'local',
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize() ?: 0,
            ]);
        }
    }

    private function ticketData(SupportTicket $ticket, bool $withMessages): array
    {
        $data = [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'category' => $ticket->category,
            'category_label' => SupportTicket::CATEGORIES[$ticket->category] ?? ucfirst($ticket->category),
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'status_label' => SupportTicket::STATUSES[$ticket->status] ?? ucfirst(str_replace('_', ' ', $ticket->status)),
            'priority' => $ticket->priority,
            'last_message_at' => optional($ticket->last_message_at)->toIso8601String(),
            'created_at' => optional($ticket->created_at)->toIso8601String(),
            'unread_count' => $ticket->unreadForUserCount(),
        ];

        if ($withMessages) {
            $data['messages'] = $ticket->messages->map(fn($message) => [
                'id' => $message->id,
                'sender_type' => $message->sender_type,
                'body' => $message->body,
                'created_at' => optional($message->created_at)->toIso8601String(),
                'attachments' => $message->attachments->map(fn($a) => [
                    'id' => $a->id,
                    'name' => $a->original_name,
                    'mime_type' => $a->mime_type,
                    'size_bytes' => $a->size_bytes,
                    'url' => '/support/attachments/' . $a->id,
                ])->values(),
            ])->values();
        }
        return $data;
    }
}
