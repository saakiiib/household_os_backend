<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Renewal;
use App\Services\EntitlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RenewalsController extends Controller
{
    /**
     * GET /api/households/{household_id}/renewals
     */
    public function index(Request $request, $household_id)
    {
        $userId = Auth::id();

        $query = Renewal::with(['createdBy:id,first_name,last_name,email,avatar', 'vehicle:id,title', 'vehicleServices'])
            ->where('household_id', $household_id);

        // Visibility: non-admins only see renewals they created or are assigned to.
        if (!$this->isHouseholdAdmin($household_id, $userId)) {
            $query->where(function ($q) use ($userId) {
                $q->where('created_by_user_id', $userId)
                  ->orWhere('assigned_user_id', $userId);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhereHas('vehicle', function ($vq) use ($search) {
                      $vq->where('title', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('renewal_type')) {
            $query->where('renewal_type', $request->renewal_type);
        }

        $renewals = $query->orderByRaw("FIELD(status, 'pending', 'completed')")
            ->orderBy('due_date', 'asc')->get()
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
        $baseRules = [
            'title'             => 'required|string|max:255',
            'renewal_type'      => 'required|in:standard,vehicle',
            'vehicle_id'        => 'required_if:renewal_type,vehicle|nullable|exists:vehicles,id',
            'category'          => 'nullable|string|max:100',
            'assigned_user_id'  => 'nullable|exists:users,id',
            'frequency'         => 'required|in:monthly,quarterly,annual',
            'due_date'          => 'required_if:renewal_type,standard|nullable|date',
            'amount'            => 'nullable|numeric|min:0',
            'reminder_before'   => 'nullable|in:30_days,14_days,7_days,3_days',
            'notes'             => 'nullable|string|max:2000',
            'vehicle_services'  => 'required_if:renewal_type,vehicle|nullable|array',
            'vehicle_services.*.service_type' => 'required_with:vehicle_services|in:mot,road_tax,insurance,annual_service',
            'vehicle_services.*.service_date' => 'required_with:vehicle_services|date',
            'vehicle_services.*.service_amount' => 'nullable|numeric|min:0',
            'document'          => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx',
        ];

        $validator = Validator::make($request->all(), $baseRules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Entitlement gate: free plan is limited to a number of active renewals.
        $household = Household::findOrFail($household_id);
        if (!(new EntitlementService())->canCreateRenewal($household)) {
            return response()->json([
                'success' => false,
                'message' => 'You have reached your Free plan Renewal limit (' . EntitlementService::FREE_RENEWALS . ' active). Upgrade to unlock unlimited Renewals.',
                'code' => 'ENTITLEMENT_LIMIT_RENEWALS',
            ], 403);
        }

        // Check if vehicle already has a pending renewal
        if ($request->renewal_type === 'vehicle' && $request->vehicle_id) {
            $existingPending = Renewal::where('household_id', $household_id)
                ->where('vehicle_id', $request->vehicle_id)
                ->where('renewal_type', 'vehicle')
                ->where('status', 'pending')
                ->exists();

            if ($existingPending) {
                return response()->json([
                    'success' => false,
                    'message' => 'This vehicle already has a pending renewal. Complete or delete it first.',
                ], 422);
            }
        }

        DB::beginTransaction();

        try {
            $renewal = Renewal::create([
                'household_id'       => $household_id,
                'created_by_user_id' => Auth::id(),
                'renewal_type'       => $request->renewal_type,
                'vehicle_id'         => $request->vehicle_id,
                'title'              => $request->title,
                'category'           => $request->category,
                'assigned_user_id'   => $request->assigned_user_id,
                'frequency'          => $request->frequency,
                'due_date'           => $request->due_date,
                'amount'             => $request->amount,
                'reminder_before'   => $request->reminder_before,
                'notes'             => $request->notes,
                'status'             => 'pending',
            ]);

            if ($request->hasFile('document')) {
                $file = $request->file('document');
                $filename = Str::random(32) . '.' . $file->getClientOriginalExtension();
                $directory = public_path('uploads/renewals');

                if (!File::isDirectory($directory)) {
                    File::makeDirectory($directory, 0755, true);
                }

                $file->move($directory, $filename);

                $renewal->update([
                    'document_file_path'     => '/uploads/renewals/' . $filename,
                    'document_original_name' => $file->getClientOriginalName(),
                    'document_mime_type'     => $file->getMimeType(),
                ]);
            }

            if ($request->renewal_type === 'vehicle' && $request->has('vehicle_services')) {
                foreach ($request->vehicle_services as $service) {
                    $renewal->vehicleServices()->create([
                        'service_type'   => $service['service_type'],
                        'service_date'   => $service['service_date'],
                        'service_amount' => $service['service_amount'] ?? null,
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create renewal: ' . $e->getMessage(),
            ], 500);
        }

        // Rule 7: do not notify the creator for an action they just performed.
        // Only the assignee (when different from the creator) is notified.
        // Rule 8: verify household membership before sending.
        try {
            $recipients = [];
            if ($renewal->assigned_user_id && $renewal->assigned_user_id !== $renewal->created_by_user_id) {
                $assigneeMembership = HouseholdMember::where('household_id', $household_id)
                    ->where('user_id', $renewal->assigned_user_id)
                    ->where('status', 'active')
                    ->exists();
                if ($assigneeMembership) {
                    $recipients[] = $renewal->assigned_user_id;
                }
            }
            $recipients = array_unique($recipients);

            if (!empty($recipients)) {
                app(\App\Services\NotificationService::class)->sendToUsers(
                    $recipients,
                    'New Renewal Added',
                    "'{$renewal->title}' has been added — due " . ($renewal->due_date ? $renewal->due_date->format('d M Y') : 'soon'),
                    'renewal_created',
                    [
                        'module' => 'renewal',
                        'action_type' => 'renewal',
                        'action_id' => $renewal->id,
                        'type' => 'renewal',
                        'id' => $renewal->id,
                        'household_id' => $household_id,
                        'title' => $renewal->title,
                    ],
                    'normal'
                );
            }
        } catch (\Throwable $e) {
            \Log::error('Failed to send renewal creation notification: ' . $e->getMessage());
        }

        $renewal->load(['createdBy:id,first_name,last_name,email,avatar', 'vehicle:id,title', 'vehicleServices']);

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
                'vehicle:id,title',
                'vehicleServices',
                'parent:id,title,due_date,status,amount',
                'children:id,title,due_date,status,amount',
            ])
            ->where('household_id', $household_id)
            ->findOrFail($renewal_id);

        if (!$this->canAccessRenewal($household_id, $renewal)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this renewal.',
            ], 403);
        }

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
        $oldRenewal = clone $renewal;

        if (!$this->canAccessRenewal($household_id, $renewal)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to modify this renewal.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title'             => 'sometimes|string|max:255',
            'category'          => 'nullable|string|max:100',
            'assigned_user_id'  => 'nullable|exists:users,id',
            'frequency'         => 'sometimes|in:monthly,quarterly,annual',
            'due_date'          => 'sometimes|date',
            'amount'            => 'nullable|numeric|min:0',
            'reminder_before'   => 'nullable|in:30_days,14_days,7_days,3_days',
            'notes'             => 'nullable|string|max:2000',
            'status'            => 'sometimes|in:pending,completed',
            'vehicle_services'  => 'nullable|array',
            'vehicle_services.*.service_type' => 'required_with:vehicle_services|in:mot,road_tax,insurance,annual_service',
            'vehicle_services.*.service_date' => 'required_with:vehicle_services|date',
            'vehicle_services.*.service_amount' => 'nullable|numeric|min:0',
            'document'          => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx',
            'remove_document'   => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $renewal->update($request->only([
                'title', 'category', 'assigned_user_id', 'frequency', 'due_date', 'amount', 'reminder_before', 'notes', 'status',
            ]));

            // Rule 10: If assigned_user_id changed, notify the new assignee (Rule 3: Creator + Assignee).
            $oldAssigned = $oldRenewal->assigned_user_id;
            $newAssigned = $request->input('assigned_user_id', $oldAssigned);
            if ($newAssigned !== null && $newAssigned !== $oldAssigned) {
                // Verify new assignee is still an active member of this household.
                $isMember = HouseholdMember::where('household_id', $household_id)
                    ->where('user_id', $newAssigned)
                    ->where('status', 'active')
                    ->exists();

                if ($isMember && $newAssigned !== Auth::id()) {
                    app(\App\Services\NotificationService::class)->sendToUser(
                        $newAssigned,
                        'Renewal updated',
                        'You have been assigned: ' . $renewal->title,
                        'renewal_updated',
                        [
                            'module' => 'renewal',
                            'action_type' => 'renewal',
                            'action_id' => $renewal->id,
                            'type' => 'renewal',
                            'id' => $renewal->id,
                            'household_id' => $household_id,
                        ],
                        'high'
                    );
                }
            }

            if ($request->boolean('remove_document') && $renewal->document_file_path) {
                $fullPath = $renewal->documentFullPath();
                if ($fullPath) {
                    @unlink($fullPath);
                }
                $renewal->update([
                    'document_file_path'     => null,
                    'document_original_name' => null,
                    'document_mime_type'     => null,
                ]);
            }

            if ($request->hasFile('document')) {
                if ($renewal->document_file_path) {
                    $oldFull = $renewal->documentFullPath();
                    if ($oldFull) {
                        @unlink($oldFull);
                    }
                }

                $file = $request->file('document');
                $filename = Str::random(32) . '.' . $file->getClientOriginalExtension();
                $directory = public_path('uploads/renewals');

                if (!File::isDirectory($directory)) {
                    File::makeDirectory($directory, 0755, true);
                }

                $file->move($directory, $filename);

                $renewal->update([
                    'document_file_path'     => '/uploads/renewals/' . $filename,
                    'document_original_name' => $file->getClientOriginalName(),
                    'document_mime_type'     => $file->getMimeType(),
                ]);
            }

            if ($request->has('vehicle_services')) {
                $renewal->vehicleServices()->delete();
                foreach ($request->vehicle_services as $service) {
                    $renewal->vehicleServices()->create([
                        'service_type'   => $service['service_type'],
                        'service_date'   => $service['service_date'],
                        'service_amount' => $service['service_amount'] ?? null,
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update renewal: ' . $e->getMessage(),
            ], 500);
        }

        $renewal->load(['createdBy:id,first_name,last_name,email,avatar', 'vehicle:id,title', 'vehicleServices']);

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

        if (!$this->canAccessRenewal($household_id, $renewal)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this renewal.',
            ], 403);
        }

        $renewal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Renewal deleted successfully',
        ]);
    }

    /**
     * GET /api/households/{household_id}/renewals/{renewal_id}/download
     */
    public function download($household_id, $renewal_id)
    {
        $renewal = Renewal::where('household_id', $household_id)->findOrFail($renewal_id);

        if (!$this->canAccessRenewal($household_id, $renewal)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this renewal.',
            ], 403);
        }

        if (!$renewal->document_file_path) {
            return response()->json([
                'success' => false,
                'message' => 'No document attached to this renewal',
            ], 404);
        }

        $fullPath = $renewal->documentFullPath();

        if (!$fullPath) {
            return response()->json([
                'success' => false,
                'message' => 'Document file not found on server',
            ], 404);
        }

        return response()->download($fullPath, $renewal->document_original_name);
    }

    /**
     * PATCH /api/households/{household_id}/renewals/{renewal_id}/complete
     */
    public function complete($household_id, $renewal_id)
    {
        $renewal = Renewal::where('household_id', $household_id)->findOrFail($renewal_id);

        if (!$this->canAccessRenewal($household_id, $renewal)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to modify this renewal.',
            ], 403);
        }

        if ($renewal->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Renewal is already completed.',
            ], 409);
        }

        $renewal->update(['status' => 'completed']);

        $renewal->load(['createdBy:id,first_name,last_name,email,avatar', 'vehicle:id,title', 'vehicleServices']);

        return response()->json([
            'success' => true,
            'message' => 'Renewal marked as complete',
            'data' => $this->formatRenewal($renewal),
        ]);
    }

    /**
     * POST /api/households/{household_id}/renewals/{renewal_id}/renew
     */
    public function renew(Request $request, $household_id, $renewal_id)
    {
        $renewal = Renewal::with('vehicleServices')->where('household_id', $household_id)->findOrFail($renewal_id);

        if (!$this->canAccessRenewal($household_id, $renewal)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to renew this renewal.',
            ], 403);
        }

        if (!$renewal->is_renewable) {
            return response()->json([
                'success' => false,
                'message' => 'This renewal cannot be renewed yet.',
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

        DB::beginTransaction();

        try {
            $newRenewal = Renewal::create([
                'household_id'       => $household_id,
                'created_by_user_id' => Auth::id(),
                'assigned_user_id'   => $renewal->assigned_user_id,
                'parent_renewal_id'  => $renewal->id,
                'renewal_type'       => $renewal->renewal_type,
                'vehicle_id'         => $renewal->vehicle_id,
                'title'              => $renewal->title,
                'category'           => $renewal->category,
                'frequency'          => $renewal->frequency,
                'due_date'           => $request->due_date,
                'amount'             => $renewal->amount,
                'reminder_before'   => $renewal->reminder_before,
                'notes'             => $renewal->notes,
                'status'             => 'pending',
            ]);

            if ($renewal->renewal_type === 'vehicle' && $renewal->vehicleServices->isNotEmpty()) {
                foreach ($renewal->vehicleServices as $service) {
                    $newRenewal->vehicleServices()->create([
                        'service_type'   => $service->service_type,
                        'service_date'   => $service->service_date,
                        'service_amount' => $service->service_amount,
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to renew: ' . $e->getMessage(),
            ], 500);
        }

        $newRenewal->load(['createdBy:id,first_name,last_name,email,avatar', 'vehicle:id,title', 'vehicleServices']);

        return response()->json([
            'success' => true,
            'message' => 'Renewal renewed successfully',
            'data' => $this->formatRenewal($newRenewal),
        ], 201);
    }

    private function isHouseholdAdmin(int|string $householdId, int|string|null $userId): bool
    {
        $membership = HouseholdMember::where('household_id', (int) $householdId)
            ->where('user_id', (int) $userId)
            ->where('status', 'active')
            ->first();

        return $membership && $membership->isAdmin();
    }

    private function canAccessRenewal($household_id, Renewal $renewal): bool
    {
        $userId = Auth::id();

        if ($this->isHouseholdAdmin($household_id, $userId)) {
            return true;
        }

        return $renewal->created_by_user_id === $userId
            || $renewal->assigned_user_id === $userId;
    }

    private function formatRenewal(Renewal $renewal): array
    {
        return [
            'id'                => $renewal->id,
            'household_id'      => $renewal->household_id,
            'renewal_type'      => $renewal->renewal_type,
            'vehicle_id'        => $renewal->vehicle_id,
            'title'             => $renewal->title,
            'category'          => $renewal->category,
            'frequency'         => $renewal->frequency,
            'due_date'          => $renewal->due_date instanceof \DateTimeInterface ? $renewal->due_date->format('Y-m-d') : $renewal->due_date,
            'amount'            => $renewal->amount,
            'reminder_before'   => $renewal->reminder_before,
            'notes'             => $renewal->notes,
            'status'            => $renewal->status,
            'is_overdue'        => $renewal->is_overdue,
            'is_renewable'      => $renewal->is_renewable,
            'days_until_due'    => $renewal->days_until_due,
            'parent_renewal_id' => $renewal->parent_renewal_id,
            'created_by_user_id'=> $renewal->created_by_user_id,
            'created_by'        => $renewal->createdBy ? [
                'id'    => $renewal->createdBy->id,
                'name'  => $renewal->createdBy->name,
                'email' => $renewal->createdBy->email,
            ] : null,
            'assigned_user_id'  => $renewal->assigned_user_id,
            'assigned_user'     => $renewal->assignedUser ? [
                'id'    => $renewal->assignedUser->id,
                'name'  => $renewal->assignedUser->name,
                'email' => $renewal->assignedUser->email,
            ] : null,
            'vehicle'           => $renewal->vehicle ? [
                'id'    => $renewal->vehicle->id,
                'title' => $renewal->vehicle->title,
            ] : null,
            'vehicle_services'  => $renewal->vehicleServices->map(fn($s) => [
                'id'             => $s->id,
                'service_type'   => $s->service_type,
                'service_date'   => $s->service_date instanceof \DateTimeInterface ? $s->service_date->format('Y-m-d') : $s->service_date,
                'service_amount' => $s->service_amount,
            ]),
            'has_document'      => $renewal->has_document,
            'document_name'     => $renewal->document_original_name,
            'document_type'     => $renewal->document_mime_type,
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
            'created_at'        => $renewal->created_at?->toIso8601String(),
            'updated_at'        => $renewal->updated_at?->toIso8601String(),
        ];
    }
}
