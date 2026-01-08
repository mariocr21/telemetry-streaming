<?php

namespace App\Http\Controllers;

use App\UserRole;
use App\Models\Client;
use App\Models\ClientDevice;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VehicleAdminController extends Controller
{
    /**
     * Ensure the user is a Super Admin
     */
    private function ensureSuperAdmin(): void
    {
        if (auth()->user()->role !== UserRole::SUPER_ADMIN) {
            abort(403, 'Acceso denegado. Solo Super Admins pueden acceder a esta sección.');
        }
    }

    /**
     * Display a listing of all vehicles (Super Admin only)
     */
    public function index(Request $request): Response
    {
        $this->ensureSuperAdmin();

        $query = Vehicle::query()
            ->with([
                'client:id,first_name,last_name,email,company',
                'clientDevice:id,device_name,mac_address,status,client_id',
                'clientDevice.DeviceInventory:id,serial_number,model',
            ])
            ->withCount('vehicleSensors');

        // Search filter
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('make', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('vin', 'like', "%{$search}%")
                    ->orWhere('license_plate', 'like', "%{$search}%")
                    ->orWhere('nickname', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('company', 'like', "%{$search}%");
                    });
            });
        }

        // Client filter
        if ($clientId = $request->get('client_id')) {
            $query->where('client_id', $clientId);
        }

        // Status filter
        if ($request->has('status') && $request->get('status') !== '') {
            $query->where('status', $request->boolean('status'));
        }

        // Device assignment filter
        if ($request->get('has_device') === 'yes') {
            $query->whereNotNull('client_device_id');
        } elseif ($request->get('has_device') === 'no') {
            $query->whereNull('client_device_id');
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $allowedSorts = ['make', 'model', 'year', 'created_at', 'last_reading_at', 'status'];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        }

        // Paginate
        $vehicles = $query->paginate(15)->withQueryString();

        // Get all clients for filter dropdown
        $clients = Client::select('id', 'first_name', 'last_name', 'company')
            ->orderBy('first_name')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->full_name,
                'company' => $c->company,
            ]);

        // Stats
        $stats = [
            'total' => Vehicle::count(),
            'active' => Vehicle::where('status', true)->count(),
            'with_device' => Vehicle::whereNotNull('client_device_id')->count(),
            'with_sensors' => Vehicle::has('vehicleSensors')->count(),
        ];

        return Inertia::render('Admin/Vehicles/Index', [
            'vehicles' => $vehicles,
            'clients' => $clients,
            'filters' => $request->only(['search', 'client_id', 'status', 'has_device', 'sort', 'direction']),
            'stats' => $stats,
        ]);
    }

    /**
     * Get available devices for assignment (for a specific client)
     */
    public function getAvailableDevices(Request $request)
    {
        $this->ensureSuperAdmin();

        $clientId = $request->get('client_id');

        if (!$clientId) {
            return response()->json(['devices' => []]);
        }

        $devices = ClientDevice::where('client_id', $clientId)
            ->with('DeviceInventory:id,serial_number,model')
            ->select('id', 'device_name', 'mac_address', 'status', 'device_inventory_id')
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'name' => $d->device_name,
                'mac' => $d->mac_address,
                'status' => $d->status,
                'serial' => $d->DeviceInventory?->serial_number,
                'model' => $d->DeviceInventory?->model,
            ]);

        return response()->json(['devices' => $devices]);
    }

    /**
     * Store a new vehicle (Super Admin only)
     */
    public function store(Request $request)
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'device_id' => 'nullable|exists:client_devices,id',
            'make' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'year' => 'nullable|integer|min:1990|max:' . (date('Y') + 2),
            'license_plate' => 'nullable|string|max:20',
            'nickname' => 'nullable|string|max:100',
            'vin' => 'nullable|string|size:17',
        ]);

        // Verify device belongs to the client if provided
        if (!empty($validated['device_id'])) {
            $device = ClientDevice::find($validated['device_id']);
            if ($device->client_id != $validated['client_id']) {
                return back()->withErrors(['device_id' => 'El dispositivo debe pertenecer al cliente seleccionado.']);
            }
        }

        $vehicle = Vehicle::create([
            'client_id' => $validated['client_id'],
            'client_device_id' => $validated['device_id'] ?: null,
            'make' => $validated['make'],
            'model' => $validated['model'],
            'year' => $validated['year'] ?? null,
            'license_plate' => $validated['license_plate'] ?? null,
            'nickname' => $validated['nickname'] ?? null,
            'vin' => $validated['vin'] ?? null,
            'status' => true,
        ]);

        $displayName = $vehicle->nickname ?: "{$vehicle->make} {$vehicle->model}";
        return back()->with('message', "Vehículo '{$displayName}' creado exitosamente.");
    }

    /**
     * Update device assignment for a vehicle
     */
    public function assignDevice(Request $request, Vehicle $vehicle)
    {
        $this->ensureSuperAdmin();

        $request->validate([
            'device_id' => 'nullable|exists:client_devices,id',
        ]);

        $deviceId = $request->get('device_id');

        // If assigning a device, verify it belongs to the same client
        if ($deviceId) {
            $device = ClientDevice::find($deviceId);
            if ($device->client_id !== $vehicle->client_id) {
                return back()->with('error', 'El dispositivo debe pertenecer al mismo cliente que el vehículo.');
            }
        }

        $vehicle->update([
            'client_device_id' => $deviceId,
        ]);

        $message = $deviceId
            ? "Dispositivo asignado exitosamente al vehículo."
            : "Dispositivo desasignado del vehículo.";

        return back()->with('message', $message);
    }

    /**
     * Toggle vehicle status
     */
    public function toggleStatus(Vehicle $vehicle)
    {
        $this->ensureSuperAdmin();

        $vehicle->update([
            'status' => !$vehicle->status,
        ]);

        $statusText = $vehicle->status ? 'activado' : 'desactivado';
        return back()->with('message', "Vehículo {$statusText} exitosamente.");
    }

    /**
     * Show vehicle details (redirects to client/device/vehicle show)
     */
    public function show(Vehicle $vehicle)
    {
        $this->ensureSuperAdmin();

        if ($vehicle->clientDevice) {
            return redirect()->route('clients.devices.vehicles.show', [
                'client' => $vehicle->client_id,
                'device' => $vehicle->client_device_id,
                'vehicle' => $vehicle->id,
            ]);
        }

        // If no device, show basic info
        return Inertia::render('Admin/Vehicles/Show', [
            'vehicle' => $vehicle->load(['client', 'vehicleSensors.sensor']),
        ]);
    }

    /**
     * Delete a vehicle
     */
    public function destroy(Vehicle $vehicle)
    {
        $this->ensureSuperAdmin();

        $vehicleName = $vehicle->display_name;

        // Soft delete
        $vehicle->delete();

        return back()->with('message', "Vehículo '{$vehicleName}' eliminado exitosamente.");
    }
}
