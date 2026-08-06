<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\Renewal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class VehiclesController extends Controller
{
    /**
     * GET /api/households/{household_id}/vehicles
     */
    public function index(Request $request, $household_id)
    {
        $query = Vehicle::with(['createdBy:id,first_name,last_name,email,avatar'])
            ->where('household_id', $household_id);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('license_plate', 'like', "%{$search}%")
                  ->orWhereHas('createdBy', function ($uq) use ($search) {
                      $uq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        $vehicles = $query->orderBy('created_at', 'desc')->get()
            ->map(fn($v) => $this->formatVehicle($v));

        return response()->json([
            'success' => true,
            'data' => $vehicles,
        ]);
    }

    /**
     * POST /api/households/{household_id}/vehicles
     */
    public function store(Request $request, $household_id)
    {
        $validator = Validator::make($request->all(), [
            'title'         => 'required|string|max:255',
            'license_plate' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $vehicle = Vehicle::create([
            'household_id'       => $household_id,
            'created_by_user_id' => Auth::id(),
            'title'              => $request->title,
            'license_plate'      => $request->license_plate,
        ]);

        $vehicle->load(['createdBy:id,first_name,last_name,email,avatar']);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle added successfully',
            'data' => $this->formatVehicle($vehicle),
        ], 201);
    }

    /**
     * GET /api/households/{household_id}/vehicles/{vehicle_id}
     */
    public function show($household_id, $vehicle_id)
    {
        $vehicle = Vehicle::with(['createdBy:id,first_name,last_name,email,avatar'])
            ->where('household_id', $household_id)
            ->findOrFail($vehicle_id);

        // Get latest renewal with vehicle services for this vehicle
        $latestRenewal = Renewal::with('vehicleServices')
            ->where('vehicle_id', $vehicle_id)
            ->where('renewal_type', 'vehicle')
            ->orderBy('created_at', 'desc')
            ->first();

        $data = $this->formatVehicle($vehicle);
        $data['latest_renewal'] = $latestRenewal ? [
            'id' => $latestRenewal->id,
            'title' => $latestRenewal->title,
            'status' => $latestRenewal->status,
            'due_date' => $latestRenewal->due_date instanceof \DateTimeInterface ? $latestRenewal->due_date->format('Y-m-d') : $latestRenewal->due_date,
            'services' => $latestRenewal->vehicleServices->map(fn($s) => [
                'service_type' => $s->service_type,
                'service_date' => $s->service_date instanceof \DateTimeInterface ? $s->service_date->format('Y-m-d') : $s->service_date,
                'service_amount' => $s->service_amount,
            ]),
        ] : null;

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * PATCH /api/households/{household_id}/vehicles/{vehicle_id}
     */
    public function update(Request $request, $household_id, $vehicle_id)
    {
        $vehicle = Vehicle::where('household_id', $household_id)->findOrFail($vehicle_id);

        $validator = Validator::make($request->all(), [
            'title'         => 'sometimes|string|max:255',
            'license_plate' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $vehicle->update($request->only(['title', 'license_plate']));

        $vehicle->load(['createdBy:id,first_name,last_name,email,avatar']);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle updated successfully',
            'data' => $this->formatVehicle($vehicle),
        ]);
    }

    /**
     * DELETE /api/households/{household_id}/vehicles/{vehicle_id}
     */
    public function destroy($household_id, $vehicle_id)
    {
        $vehicle = Vehicle::where('household_id', $household_id)->findOrFail($vehicle_id);
        $vehicle->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vehicle deleted successfully',
        ]);
    }

    private function formatVehicle(Vehicle $vehicle): array
    {
        return [
            'id'               => $vehicle->id,
            'household_id'     => $vehicle->household_id,
            'title'            => $vehicle->title,
            'license_plate'    => $vehicle->license_plate,
            'created_by_user_id' => $vehicle->created_by_user_id,
            'created_by'       => $vehicle->createdBy ? [
                'id'    => $vehicle->createdBy->id,
                'name'  => $vehicle->createdBy->name,
                'email' => $vehicle->createdBy->email,
            ] : null,
            'created_at'       => $vehicle->created_at?->toIso8601String(),
            'updated_at'       => $vehicle->updated_at?->toIso8601String(),
        ];
    }
}
