<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\HouseholdMember;
use App\Models\Renewal;
use App\Models\SmartSuggestionState;
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
            ->get(['id', 'title', 'category', 'description', 'due_date', 'visibility', 'created_by_user_id']);

        $states = SmartSuggestionState::where('household_id', $householdId)
            ->where('user_id', $userId)
            ->pluck('status', 'suggestion_key');

        $suggestions = [];
        $today = Carbon::today();

        foreach ($documents as $document) {
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
