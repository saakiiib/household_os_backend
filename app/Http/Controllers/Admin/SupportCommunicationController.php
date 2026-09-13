<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportAttachment;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SupportCommunicationController extends Controller
{
    public function index(Request $request)
    {
        $query = SupportTicket::with(['user', 'household'])->latest('last_message_at');

        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where(function ($builder) use ($q) {
                $builder->where('ticket_number', 'like', "%{$q}%")
                    ->orWhere('subject', 'like', "%{$q}%")
                    ->orWhereHas('user', fn($u) => $u->where('email', 'like', "%{$q}%")
                        ->orWhere('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%"));
            });
        }
        if ($request->filled('status') && array_key_exists($request->status, SupportTicket::STATUSES)) $query->where('status', $request->status);
        if ($request->filled('category') && array_key_exists($request->category, SupportTicket::CATEGORIES)) $query->where('category', $request->category);
        if ($request->filled('priority') && array_key_exists($request->priority, SupportTicket::PRIORITIES)) $query->where('priority', $request->priority);

        $tickets = $query->paginate(25)->withQueryString();
        $stats = [
            'total' => SupportTicket::count(),
            'waiting_support' => SupportTicket::whereIn('status', ['open', 'waiting_support'])->count(),
            'waiting_customer' => SupportTicket::where('status', 'waiting_customer')->count(),
            'resolved' => SupportTicket::whereIn('status', ['resolved', 'closed'])->count(),
        ];

        $active = 'support-communications';
        return view('admin.pages.support-communications', compact('tickets', 'stats', 'active'));
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->update(['admin_last_read_at' => now()]);
        $ticket->load(['user', 'household', 'messages.sender', 'messages.attachments']);
        $active = 'support-communications';
        return view('admin.pages.support-communication-show', compact('ticket', 'active'));
    }

    public function poll(SupportTicket $ticket)
    {
        // The admin is actively viewing this ticket, so newly-arrived customer
        // messages can safely be marked as read for the admin.
        $ticket->update(['admin_last_read_at' => now()]);
        $ticket->load(['user', 'household', 'messages.sender', 'messages.attachments']);

        $latestMessage = $ticket->messages->last();

        return response()->json([
            'ok' => true,
            'ticket_id' => $ticket->id,
            'message_count' => $ticket->messages->count(),
            'latest_message_id' => $latestMessage?->id,
            'last_message_at' => optional($ticket->last_message_at)->toIso8601String(),
            'status' => $ticket->status,
            'status_label' => SupportTicket::STATUSES[$ticket->status] ?? $ticket->status,
            'html' => view('admin.pages._support-message-list', compact('ticket'))->render(),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket, NotificationService $notifications)
    {
        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:4000', 'required_without:attachments'],
            'internal' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:5120'],
        ]);

        $isInternal = $request->boolean('internal');

        DB::transaction(function () use ($request, $validated, $ticket, $isInternal) {
            $message = SupportMessage::create([
                'support_ticket_id' => $ticket->id,
                'sender_user_id' => $request->user()->id,
                'sender_type' => 'admin',
                'body' => trim((string)($validated['message'] ?? '')) ?: null,
                'is_internal' => $isInternal,
            ]);

            foreach ($request->file('attachments', []) as $file) {
                if (!$file || !$file->isValid()) continue;
                $path = $file->store('support/' . $ticket->id, 'local');
                SupportAttachment::create([
                    'support_ticket_id' => $ticket->id,
                    'support_message_id' => $message->id,
                    'uploaded_by_user_id' => $request->user()->id,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => $file->getSize() ?: 0,
                ]);
            }

            $updates = ['last_message_at' => now(), 'admin_last_read_at' => now()];
            if (!$isInternal) {
                $updates['status'] = 'waiting_customer';
                $updates['resolved_at'] = null;
                $updates['closed_at'] = null;
            }
            $ticket->update($updates);
        });

        if (!$isInternal && $ticket->user_id) {
            $notifications->sendToUser(
                $ticket->user_id,
                'HouseholdOS Support replied',
                'There is a new reply to ' . $ticket->ticket_number . '.',
                'support_reply',
                [
                    'module' => 'support',
                    'action_id' => (string)$ticket->id,
                    'ticket_id' => (string)$ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                ],
                'normal'
            );
        }

        return back()->with('success', $isInternal ? 'Internal note added.' : 'Reply sent to customer.');
    }

    public function update(Request $request, SupportTicket $ticket)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(SupportTicket::STATUSES))],
            'priority' => ['required', Rule::in(array_keys(SupportTicket::PRIORITIES))],
        ]);

        $updates = $validated;
        if ($validated['status'] === 'resolved') $updates['resolved_at'] = now();
        elseif ($ticket->status === 'resolved') $updates['resolved_at'] = null;
        if ($validated['status'] === 'closed') $updates['closed_at'] = now();
        elseif ($ticket->status === 'closed') $updates['closed_at'] = null;
        $ticket->update($updates);

        return back()->with('success', 'Support ticket updated.');
    }

    public function attachment(SupportAttachment $attachment)
    {
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);
        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream', 'Cache-Control' => 'private, max-age=300']
        );
    }
}
