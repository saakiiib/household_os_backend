<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Renewal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RenewalsController extends Controller
{
    /**
     * GET /api/households/{household_id}/renewals
     */
    public function index(Request $request, $household_id)
    {
        $query = Renewal::with(['createdBy:id,first_name,last_name,email,avatar'])
            ->where('household_id', $household_id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('frequency')) {
            $query->where('frequency', $request->frequency);
        }

        $renewals = $query->orderBy('due_date', 'asc')->get()
            ->map(fn($renewal) => $this->formatRenewal($renewal));

        return response()->json([
            'success' => true,
            'data' => $renewals,
        ]);
    }

    /**
     * POST /api/households/{household_id}/renewals
     */
    public function store(Request $request, $household_id)
    {
        $validator = Validator::make($request->all(), [
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string|max:2000',
            'due_date'     => 'required|date',
            'frequency'    => 'sometimes|in:monthly,quarterly,annual,one-time',
            'amount'       => 'nullable|numeric|min:0',
            'category'     => 'nullable|string|max:255',
            'notes'        => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $renewal = Renewal::create([
            'household_id'       => $household_id,
            'created_by_user_id' => Auth::id(),
            'title'              => $request->title,
            'description'        => $request->description,
            'due_date'           => $request->due_date,
            'frequency'          => $request->frequency ?? 'annual',
            'amount'             => $request->amount,
            'category'           => $request->category,
            'notes'              => $request->notes,
            'status'             => 'pending',
        ]);

        $renewal->load(['createdBy:id,first_name,last_name,email,avatar']);

        return response()->json([
            'success' => true,
            'message' => 'Renewal created successfully',
            'data' => $this->formatRenewal($renewal),
        ], 201);
    }

    /**
     * GET /api/households/{household_id}/renewals/{renewal_id}
     */
    public function show($household_id, $renewal_id)
    {
        $renewal = Renewal::with([
                'createdBy:id,first_name,last_name,email,avatar',
                'parent:id,title,due_date,status,amount',
                'children:id,title,due_date,status,amount',
                'document:id,title,category',
            ])
            ->where('household_id', $household_id)
            ->findOrFail($renewal_id);

        return response()->json([
            'success' => true,
            'data' => $this->formatRenewal($renewal),
        ]);
    }

    /**
     * PATCH /api/households/{household_id}/renewals/{renewal_id}
     */
    public function update(Request $request, $household_id, $renewal_id)
    {
        $renewal = Renewal::where('household_id', $household_id)->findOrFail($renewal_id);

        $validator = Validator::make($request->all(), [
            'title'        => 'sometimes|string|max:255',
            'description'  => 'nullable|string|max:2000',
            'due_date'     => 'sometimes|date',
            'frequency'    => 'sometimes|in:monthly,quarterly,annual,one-time',
            'amount'       => 'nullable|numeric|min:0',
            'category'     => 'nullable|string|max:255',
            'status'       => 'sometimes|in:pending,completed',
            'notes'        => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $renewal->update($request->only([
            'title', 'description', 'due_date', 'frequency', 'amount', 'category', 'status', 'notes',
        ]));

        $renewal->load(['createdBy:id,first_name,last_name,email,avatar']);

        return response()->json([
            'success' => true,
            'message' => 'Renewal updated successfully',
            'data' => $this->formatRenewal($renewal),
        ]);
    }

    /**
     * DELETE /api/households/{household_id}/renewals/{renewal_id}
     */
    public function destroy($household_id, $renewal_id)
    {
        $renewal = Renewal::where('household_id', $household_id)->findOrFail($renewal_id);
        $renewal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Renewal deleted successfully',
        ]);
    }

    /**
     * PATCH /api/households/{household_id}/renewals/{renewal_id}/complete
     */
    public function complete($household_id, $renewal_id)
    {
        $renewal = Renewal::where('household_id', $household_id)->findOrFail($renewal_id);

        if ($renewal->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Renewal is already completed.',
            ], 409);
        }

        $renewal->update(['status' => 'completed']);

        $renewal->load(['createdBy:id,first_name,last_name,email,avatar']);

        return response()->json([
            'success' => true,
            'message' => 'Renewal marked as complete',
            'data' => $this->formatRenewal($renewal),
        ]);
    }

    /**
     * POST /api/households/{household_id}/renewals/{renewal_id}/renew
     * Create a new renewal chained from the current one.
     */
    public function renew(Request $request, $household_id, $renewal_id)
    {
        $renewal = Renewal::where('household_id', $household_id)->findOrFail($renewal_id);

        // Can only renew if completed or overdue
        if (!$renewal->is_renewable) {
            return response()->json([
                'success' => false,
                'message' => 'This renewal cannot be renewed yet. It must be completed or overdue.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'due_date' => 'required|date|after:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $newRenewal = Renewal::create([
            'household_id'       => $household_id,
            'created_by_user_id' => Auth::id(),
            'parent_renewal_id'  => $renewal->id,
            'document_id'        => $renewal->document_id,
            'title'              => $renewal->title,
            'description'        => $renewal->description,
            'due_date'           => $request->due_date,
            'frequency'          => $renewal->frequency,
            'amount'             => $renewal->amount,
            'category'           => $renewal->category,
            'notes'              => $renewal->notes,
            'status'             => 'pending',
        ]);

        $newRenewal->load(['createdBy:id,first_name,last_name,email,avatar']);

        return response()->json([
            'success' => true,
            'message' => 'Renewal renewed successfully',
            'data' => $this->formatRenewal($newRenewal),
        ], 201);
    }

    /**
     * POST /api/households/{household_id}/renewals/from-document/{document_id}
     * Create a new renewal linked to a document.
     */
    public function createFromDocument(Request $request, $household_id, $document_id)
    {
        $document = \App\Models\Document::where('household_id', $household_id)->findOrFail($document_id);

        $validator = Validator::make($request->all(), [
            'due_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $renewal = Renewal::create([
            'household_id'       => $household_id,
            'created_by_user_id' => Auth::id(),
            'document_id'        => $document->id,
            'title'              => $document->title,
            'description'        => $document->description,
            'due_date'           => $request->due_date,
            'frequency'          => 'annual',
            'category'           => $document->category,
            'notes'              => null,
            'status'             => 'pending',
        ]);

        $renewal->load(['createdBy:id,first_name,last_name,email,avatar', 'document:id,title,category']);

        return response()->json([
            'success' => true,
            'message' => 'Renewal created from document',
            'data' => $this->formatRenewal($renewal),
        ], 201);
    }

    private function formatRenewal(Renewal $renewal): array
    {
        return [
            'id'                => $renewal->id,
            'household_id'      => $renewal->household_id,
            'title'             => $renewal->title,
            'description'       => $renewal->description,
            'due_date'          => $renewal->due_date instanceof \DateTimeInterface ? $renewal->due_date->format('Y-m-d') : $renewal->due_date,
            'frequency'         => $renewal->frequency,
            'amount'            => $renewal->amount,
            'category'          => $renewal->category,
            'status'            => $renewal->status,
            'notes'             => $renewal->notes,
            'is_overdue'        => $renewal->is_overdue,
            'is_renewable'      => $renewal->is_renewable,
            'days_until_due'    => $renewal->days_until_due,
            'parent_renewal_id' => $renewal->parent_renewal_id,
            'document_id'       => $renewal->document_id,
            'created_by_user_id'=> $renewal->created_by_user_id,
            'created_by'        => $renewal->createdBy ? [
                'id'    => $renewal->createdBy->id,
                'name'  => $renewal->createdBy->name,
                'email' => $renewal->createdBy->email,
                'avatar'=> $renewal->createdBy->avatar,
            ] : null,
            'parent'            => $renewal->parent ? [
                'id'       => $renewal->parent->id,
                'title'    => $renewal->parent->title,
                'due_date' => $renewal->parent->due_date instanceof \DateTimeInterface ? $renewal->parent->due_date->format('Y-m-d') : $renewal->parent->due_date,
                'status'   => $renewal->parent->status,
                'amount'   => $renewal->parent->amount,
            ] : null,
            'children'          => $renewal->children->map(fn($child) => [
                'id'       => $child->id,
                'title'    => $child->title,
                'due_date' => $child->due_date instanceof \DateTimeInterface ? $child->due_date->format('Y-m-d') : $child->due_date,
                'status'   => $child->status,
                'amount'   => $child->amount,
            ]),
            'document'          => $renewal->document ? [
                'id'       => $renewal->document->id,
                'title'    => $renewal->document->title,
                'category' => $renewal->document->category,
            ] : null,
            'created_at'        => $renewal->created_at?->toIso8601String(),
            'updated_at'        => $renewal->updated_at?->toIso8601String(),
        ];
    }
}
