<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Renewal;
use App\Models\RenewalVehicleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RenewalsController extends Controller
{
    /**
     * GET /api/households/{household_id}/renewals
     */
    public function index(Request $request, $household_id)
    {
        $query = Renewal::with(['createdBy:id,first_name,last_name,email,avatar', 'vehicle:id,title', 'vehicleServices'])
            ->where('household_id', $household_id);

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
            'category'          => 'nullable|in:' . implode(',', Renewal::CATEGORIES),
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
                'frequency'          => $request->frequency,
                'due_date'           => $request->due_date,
                'amount'             => $request->amount,
                'reminder_before'   => $request->reminder_before,
                'notes'             => $request->notes,
                'status'             => 'pending',
            ]);

            // Handle single document upload (no encryption)
            if ($request->hasFile('document')) {
                $file = $request->file('document');
                $filename = Str::random(32) . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('uploads/renewals', $filename);

                $renewal->update([
                    'document_file_path'     => $path,
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

        // Notify all household members about the new renewal
        try {
            $members = \App\Models\HouseholdMember::where('household_id', $household_id)
                ->where('status', 'active')
                ->where('user_id', '!=', Auth::id())
                ->pluck('user_id')
                ->all();

            foreach ($members as $memberId) {
                app(\App\Services\NotificationService::class)->sendToUser(
                    $memberId,
                    'New Renewal Added',
                    "'{$renewal->title}' has been added — due " . ($renewal->due_date ? $renewal->due_date->format('d M Y') : 'soon'),
                    'renewal_created',
                    ['type' => 'renewal', 'id' => $renewal->id]
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
            'title'             => 'sometimes|string|max:255',
            'category'          => 'nullable|in:' . implode(',', Renewal::CATEGORIES),
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
                'title', 'category', 'frequency', 'due_date', 'amount', 'reminder_before', 'notes', 'status',
            ]));

            // Handle document removal
            if ($request->boolean('remove_document') && $renewal->document_file_path) {
                $fullPath = storage_path('app/' . $renewal->document_file_path);
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
                $renewal->update([
                    'document_file_path'     => null,
                    'document_original_name' => null,
                    'document_mime_type'     => null,
                ]);
            }

            // Handle single document upload (no encryption)
            if ($request->hasFile('document')) {
                // Delete old file if replacing
                if ($renewal->document_file_path) {
                    $oldPath = storage_path('app/' . $renewal->document_file_path);
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }

                $file = $request->file('document');
                $filename = Str::random(32) . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('uploads/renewals', $filename);

                $renewal->update([
                    'document_file_path'     => $path,
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

        // Delete the file from disk
        if ($renewal->document_file_path) {
            $fullPath = storage_path('app/' . $renewal->document_file_path);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
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

        if (!$renewal->document_file_path) {
            return response()->json([
                'success' => false,
                'message' => 'No document attached to this renewal',
            ], 404);
        }

        $fullPath = storage_path('app/' . $renewal->document_file_path);

        if (!file_exists($fullPath)) {
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
