<?php

namespace App\Http\Controllers;

use App\Models\DeviceInventory;
use App\Http\Requests\StoreDeviceInventoryRequest;
use App\Http\Requests\UpdateDeviceInventoryRequest;
use App\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DeviceInventoryController extends Controller
{
    /**
     * Verificar que el usuario sea Super Admin
     */
    private function ensureSuperAdmin(): void
    {
        $user = Auth::user();

        if ($user->role !== UserRole::SUPER_ADMIN) {
            abort(403, 'Solo los Super Administradores pueden acceder al inventario de dispositivos.');
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->ensureSuperAdmin();

        $devices = DeviceInventory::query()
            ->withCount('clientDevices')
            ->when($request->search, function ($query, $search) {
                $query->where('serial_number', 'like', "%{$search}%")
                    ->orWhere('device_uuid', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%");
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->sort, function ($query, $sort) use ($request) {
                $direction = $request->direction === 'desc' ? 'desc' : 'asc';
                $query->orderBy($sort, $direction);
            }, function ($query) {
                $query->orderBy('created_at', 'desc');
            })
            ->paginate(15)
            ->withQueryString();

        // Obtener filtros únicos para los dropdowns
        $statuses = ['available', 'sold', 'maintenance', 'retired'];

        return Inertia::render('DeviceInventory/Index', [
            'devices' => $devices->through(function ($device) {
                return [
                    'id' => $device->id,
                    'serial_number' => $device->serial_number,
                    'device_uuid' => $device->device_uuid,
                    'model' => $device->model,
                    'hardware_version' => $device->hardware_version,
                    'firmware_version' => $device->firmware_version,
                    'status' => $device->status,
                    'manufactured_date' => $device->manufactured_date?->format('Y-m-d'),
                    'sold_date' => $device->sold_date?->format('Y-m-d'),
                    'notes' => $device->notes,
                    'created_at' => $device->created_at->format('Y-m-d H:i:s'),
                    'client_devices_count' => $device->client_devices_count,
                    'can' => [
                        'view' => true,
                        'update' => true,
                        'delete' => $device->client_devices_count === 0,
                    ]
                ];
            }),
            'filters' => $request->only(['search', 'status', 'sort', 'direction']),
            'filterOptions' => [
                'statuses' => $statuses,
            ],
            'can' => [
                'create_device' => true,
            ]
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->ensureSuperAdmin();

        return Inertia::render('DeviceInventory/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDeviceInventoryRequest $request)
    {
        $this->ensureSuperAdmin();

        $device = DeviceInventory::create($request->validated());

        return redirect()->route('device-inventory.index')
            ->with('message', 'Dispositivo agregado al inventario exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(DeviceInventory $deviceInventory): Response
    {
        $this->ensureSuperAdmin();

        $deviceInventory->load(['clientDevices.client', 'clientDevices.vehicles']);

        return Inertia::render('DeviceInventory/Show', [
            'device' => [
                'id' => $deviceInventory->id,
                'serial_number' => $deviceInventory->serial_number,
                'device_uuid' => $deviceInventory->device_uuid,
                'model' => $deviceInventory->model,
                'hardware_version' => $deviceInventory->hardware_version,
                'firmware_version' => $deviceInventory->firmware_version,
                'status' => $deviceInventory->status,
                'manufactured_date' => $deviceInventory->manufactured_date?->format('Y-m-d'),
                'sold_date' => $deviceInventory->sold_date?->format('Y-m-d'),
                'notes' => $deviceInventory->notes,
                'created_at' => $deviceInventory->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $deviceInventory->updated_at->format('Y-m-d H:i:s'),
                'client_devices' => $deviceInventory->clientDevices->map(function ($clientDevice) {
                    return [
                        'id' => $clientDevice->id,
                        'device_name' => $clientDevice->device_name,
                        'mac_address' => $clientDevice->mac_address,
                        'status' => $clientDevice->status,
                        'activated_at' => $clientDevice->activated_at?->format('Y-m-d H:i:s'),
                        'last_ping' => $clientDevice->last_ping?->format('Y-m-d H:i:s'),
                        'client' => $clientDevice->client ? [
                            'id' => $clientDevice->client->id,
                            'full_name' => $clientDevice->client->full_name,
                            'email' => $clientDevice->client->email,
                        ] : null,
                        'vehicle' => $clientDevice->vehicles
                            ? optional($clientDevice->vehicles->where('status', true)->first(), function ($vehicle) {
                                return [
                                    'id' => $vehicle->id,
                                    'make' => $vehicle->make,
                                    'model' => $vehicle->model,
                                    'year' => $vehicle->year,
                                    'license_plate' => $vehicle->license_plate,
                                    'nickname' => $vehicle->nickname,
                                ];
                            })
                            : null,
                    ];
                }),
                'can' => [
                    'view' => true,
                    'update' => true,
                    'delete' => $deviceInventory->clientDevices->count() === 0,
                ]
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DeviceInventory $deviceInventory): Response
    {
        $this->ensureSuperAdmin();

        return Inertia::render('DeviceInventory/Edit', [
            'device' => [
                'id' => $deviceInventory->id,
                'serial_number' => $deviceInventory->serial_number,
                'device_uuid' => $deviceInventory->device_uuid,
                'model' => $deviceInventory->model,
                'hardware_version' => $deviceInventory->hardware_version,
                'firmware_version' => $deviceInventory->firmware_version,
                'status' => $deviceInventory->status,
                'manufactured_date' => $deviceInventory->manufactured_date?->format('Y-m-d'),
                'sold_date' => $deviceInventory->sold_date?->format('Y-m-d'),
                'notes' => $deviceInventory->notes,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDeviceInventoryRequest $request, DeviceInventory $deviceInventory)
    {
        $this->ensureSuperAdmin();

        $data = $request->validated();

        $deviceInventory->update($data);

        return redirect()->route('device-inventory.show', $deviceInventory)
            ->with('message', 'Dispositivo actualizado exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeviceInventory $deviceInventory)
    {
        $this->ensureSuperAdmin();

        // Verificar que no tenga dispositivos de clientes asignados
        if ($deviceInventory->clientDevices()->count() > 0) {
            return back()->withErrors([
                'delete' => 'No se puede eliminar este dispositivo porque está asignado a uno o más clientes.'
            ]);
        }

        $deviceInventory->delete();

        return redirect()->route('device-inventory.index')
            ->with('message', 'Dispositivo eliminado del inventario exitosamente.');
    }

    /**
     * Get device statistics
     */
    public function stats()
    {
        $this->ensureSuperAdmin();

        $stats = [
            'total' => DeviceInventory::count(),
            'available' => DeviceInventory::where('status', 'available')->count(),
            'sold' => DeviceInventory::where('status', 'sold')->count(),
            'maintenance' => DeviceInventory::where('status', 'maintenance')->count(),
            'retired' => DeviceInventory::where('status', 'retired')->count(),
            'assigned' => DeviceInventory::has('clientDevices')->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Bulk update status
     */
    public function bulkUpdateStatus(Request $request)
    {
        $this->ensureSuperAdmin();

        $request->validate([
            'device_ids' => 'required|array',
            'device_ids.*' => 'exists:device_inventories,id',
            'status' => 'required|in:available,sold,maintenance,retired',
        ]);

        DeviceInventory::whereIn('id', $request->device_ids)
            ->update(['status' => $request->status]);

        return back()->with('message', 'Estado actualizado para ' . count($request->device_ids) . ' dispositivos.');
    }
}
