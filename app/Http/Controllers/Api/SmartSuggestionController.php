<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\HouseholdMember;
use App\Models\Renewal;
use App\Models\SmartSuggestionState;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SmartSuggestionController extends Controller
{
    public function index(Request $request, $household_id)
    {
        $userId = Auth::id();
        if (!$this->isActiveMember((int) $household_id, $userId)) {
            return response()->json(['success' => false, 'message' => 'You do not have access to this household.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->buildSuggestions((int) $household_id, $userId),
        ]);
    }

    public function state(Request $request, $household_id)
    {
        $userId = Auth::id();
        if (!$this->isActiveMember((int) $household_id, $userId)) {
            return response()->json(['success' => false, 'message' => 'You do not have access to this household.'], 403);
        }

        $validated = $request->validate([
            'suggestion_key' => 'required|string|max:190',
            'status' => ['required', Rule::in(['dismissed', 'accepted'])],
        ]);

        // Fail closed: a user may only alter state for a suggestion that is
        // currently derivable from data they are authorised to view.
        $available = collect($this->buildSuggestions((int) $household_id, $userId, includeHidden: true))
            ->firstWhere('key', $validated['suggestion_key']);

        if (!$available) {
            return response()->json(['success' => false, 'message' => 'Suggestion is no longer available.'], 404);
        }

        SmartSuggestionState::updateOrCreate(
            [
                'household_id' => (int) $household_id,
                'user_id' => $userId,
                'suggestion_key' => $validated['suggestion_key'],
            ],
            ['status' => $validated['status']]
        );

        return response()->json(['success' => true]);
    }

    private function buildSuggestions(int $householdId, int $userId, bool $includeHidden = false): array
    {
        $documents = Document::query()
            ->where('household_id', $householdId)
            ->whereNotNull('due_date')
            ->where(function ($q) use ($userId) {
                // EXACTLY mirror DocumentsController visibility semantics.
                $q->where('visibility', 'all')
                    ->orWhere('created_by_user_id', $userId)
                    ->orWhereHas('allowedMembers', fn ($uq) => $uq->where('users.id', $userId));
            })
            ->orderBy('due_date')
            ->limit(100)
            ->get(['id', 'title', 'category', 'description', 'due_date', 'important_date_type', 'visibility', 'created_by_user_id']);

        $states = SmartSuggestionState::where('household_id', $householdId)
            ->where('user_id', $userId)
            ->pluck('status', 'suggestion_key');

        $suggestions = [];
        $today = Carbon::today();

        foreach ($documents as $document) {
            // Some extracted dates are useful document facts but are not renewal
            // dates (for example a pension retirement date). Explicitly typed
            // non-renewal dates must never generate a Create Renewal suggestion.
            $dateType = $document->important_date_type;
            if (in_array($dateType, ['retirement', 'review', 'other'], true)) {
                continue;
            }

            $due = Carbon::parse($document->due_date)->startOfDay();
            $days = (int) $today->diffInDays($due, false);

            // Keep the dashboard useful: show recently expired documents and
            // upcoming dates within one year, not every historical record.
            if ($days < -90 || $days > 365) {
                continue;
            }

            $key = 'document_due_date:' . $document->id . ':' . $due->format('Y-m-d');
            if (!$includeHidden && in_array($states[$key] ?? null, ['dismissed', 'accepted'], true)) {
                continue;
            }

            // Once a matching household renewal exists, the suggestion has
            // already achieved its purpose and disappears for everybody.
            $normalTitle = Str::lower(trim($document->title));
            $hasMatchingRenewal = Renewal::where('household_id', $householdId)
                ->where(function ($rq) use ($document, $due, $normalTitle) {
                    // Phase 2: explicit source-document links are authoritative.
                    // Keep the title/date fallback for renewals created before this migration.
                    $rq->where('source_document_id', $document->id)
                        ->orWhere(function ($legacy) use ($due, $normalTitle) {
                            $legacy->whereDate('due_date', $due->format('Y-m-d'))
                                ->whereRaw('LOWER(TRIM(title)) = ?', [$normalTitle]);
                        });
                })
                ->exists();
            if ($hasMatchingRenewal) {
                continue;
            }

            if ($days < 0) {
                $message = $document->title . ' passed its document date ' . $due->format('j M Y') . '.';
                $priority = 10;
                $tone = 'urgent';
            } elseif ($days === 0) {
                $message = $document->title . ' has an important date today.';
                $priority = 9;
                $tone = 'urgent';
            } elseif ($days <= 30) {
                $message = $document->title . ' has an important date in ' . $days . ' day' . ($days === 1 ? '' : 's') . '.';
                $priority = 8;
                $tone = 'upcoming';
            } else {
                $message = 'Important date found for ' . $document->title . ': ' . $due->format('j M Y') . '.';
                $priority = 6;
                $tone = 'document';
            }

            $suggestions[] = [
                'key' => $key,
                'type' => 'document_due_date',
                'source_type' => 'document',
                'source_id' => $document->id,
                'message' => $message,
                'action_label' => 'Create renewal',
                'tone' => $tone,
                'priority' => $priority,
                'document' => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'category' => $document->category,
                    'due_date' => $due->format('Y-m-d'),
                ],
                'prefill' => [
                    'title' => $document->title,
                    'category' => $document->category,
                    'due_date' => $due->format('Y-m-d'),
                    'notes' => 'Created from HouseholdOS Smart Suggestions based on the important date saved with "' . $document->title . '".',
                ],
            ];
        }

        // Phase 3: help the relevant renewal owner/assignee prepare before a due date.
        // This is confirm-first: the suggestion opens a pre-filled Task form; it never
        // creates a task automatically.
        $renewals = Renewal::query()
            ->where('household_id', $householdId)
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->where(function ($q) use ($userId) {
                $q->where('created_by_user_id', $userId)
                    ->orWhere('assigned_user_id', $userId);
            })
            ->whereDate('due_date', '>=', $today->copy()->addDay()->toDateString())
            ->whereDate('due_date', '<=', $today->copy()->addDays(30)->toDateString())
            ->orderBy('due_date')
            ->limit(50)
            ->get(['id', 'title', 'category', 'due_date', 'created_by_user_id', 'assigned_user_id']);

        foreach ($renewals as $renewal) {
            $due = Carbon::parse($renewal->due_date)->startOfDay();
            $days = (int) $today->diffInDays($due, false);
            $key = 'renewal_prepare_task:' . $renewal->id . ':' . $due->format('Y-m-d');

            if (!$includeHidden && in_array($states[$key] ?? null, ['dismissed', 'accepted'], true)) {
                continue;
            }

            $taskDue = $due->copy()->subDays(7);
            if ($taskDue->lte($today)) {
                $taskDue = $today->copy()->addDay();
            }

            $taskTitle = 'Prepare for ' . $renewal->title;
            $alreadyHasTask = Task::query()
                ->where('household_id', $householdId)
                ->where('status', '!=', 'completed')
                ->whereRaw('LOWER(TRIM(title)) = ?', [Str::lower(trim($taskTitle))])
                ->whereDate('due_date', $taskDue->format('Y-m-d'))
                ->exists();
            if ($alreadyHasTask) {
                continue;
            }

            $suggestions[] = [
                'key' => $key,
                'type' => 'renewal_prepare_task',
                'source_type' => 'renewal',
                'source_id' => $renewal->id,
                'message' => $renewal->title . ' is due in ' . $days . ' day' . ($days === 1 ? '' : 's') . '. Create a preparation task?',
                'action_label' => 'Create task',
                'tone' => $days <= 7 ? 'urgent' : 'upcoming',
                'priority' => $days <= 7 ? 9 : 7,
                'document' => [
                    // Keep the common sorting shape without exposing unrelated document data.
                    'due_date' => $due->format('Y-m-d'),
                ],
                'prefill' => [
                    'title' => $taskTitle,
                    'description' => 'Prepare for the upcoming ' . $renewal->title . ' renewal.',
                    'due_date' => $taskDue->format('Y-m-d'),
                    'notes' => 'Created from HouseholdOS Smart Suggestions for renewal #' . $renewal->id . ' due ' . $due->format('j M Y') . '.',
                ],
            ];
        }

        usort($suggestions, function ($a, $b) {
            if ($a['priority'] === $b['priority']) {
                return strcmp($a['document']['due_date'], $b['document']['due_date']);
            }
            return $b['priority'] <=> $a['priority'];
        });

        return array_slice($suggestions, 0, 5);
    }

    private function isActiveMember(int $householdId, int $userId): bool
    {
        return HouseholdMember::where('household_id', $householdId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }
}
