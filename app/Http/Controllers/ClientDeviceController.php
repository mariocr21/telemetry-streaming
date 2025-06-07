<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientDevice;
use App\Models\DeviceInventory;
use App\Http\Requests\StoreClientDeviceRequest;
use App\Http\Requests\UpdateClientDeviceRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientDeviceController extends Controller
{
    /**
     * Display a listing of the client's devices.
     */
    public function index(Client $client): Response
    {
        $devices = $client->devices()
            ->with(['DeviceInventory', 'vehicles'])
            ->when(request('search'), function ($query, $search) {
                $query->where('device_name', 'like', "%{$search}%")
                    ->orWhere('mac_address', 'like', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Clients/Devices/Index', [
            'client' => [
                'id' => $client->id,
                'full_name' => $client->full_name,
                'email' => $client->email,
            ],
            'devices' => $devices->through(function ($device) {
                return [
                    'id' => $device->id,
                    'device_name' => $device->device_name,
                    'mac_address' => $device->mac_address,
                    'status' => $device->status,
                    'activated_at' => $device->activated_at?->format('Y-m-d H:i:s'),
                    'last_ping' => $device->last_ping?->format('Y-m-d H:i:s'),
                    'created_at' => $device->created_at->format('Y-m-d H:i:s'),
                    'device_inventory' => $device->DeviceInventory ? [
                        'id' => $device->DeviceInventory->id,
                        'serial_number' => $device->DeviceInventory->serial_number,
                        'device_uuid' => $device->DeviceInventory->device_uuid,
                        'model' => $device->DeviceInventory->model,
                        'hardware_version' => $device->DeviceInventory->hardware_version,
                        'firmware_version' => $device->DeviceInventory->firmware_version,
                        'status' => $device->DeviceInventory->status,
                    ] : null,
                    'vehicles' => $device->vehicles && $device->vehicles->count() > 0
                        ? $device->vehicles->map(function ($vehicle) {
                            return [
                                'id' => $vehicle->id,
                                'make' => $vehicle->make,
                                'model' => $vehicle->model,
                                'year' => $vehicle->year,
                                'license_plate' => $vehicle->license_plate,
                                'color' => $vehicle->color,
                                'nickname' => $vehicle->nickname,
                                'vin' => $vehicle->vin,
                                'status' => $vehicle->status,
                                'is_configured' => $vehicle->is_configured,
                                'last_reading_at' => $vehicle->last_reading_at?->format('Y-m-d H:i:s'),
                            ];
                        })
                        : [],
                    'vehicles_count' => $device->vehicles ? $device->vehicles->count() : 0,

                    'can' => [
                        'view' => true,
                        'update' => true,
                        'delete' => true,
                    ]
                ];
            }),
            'filters' => request()->only(['search']),
            'can' => [
                'create_device' => true,
            ]
        ]);
    }

    /**
     * Show the form for creating a new device.
     */
    public function create(Client $client): Response
    {
        $availableDevices = DeviceInventory::where('status', 'available')
            ->orderBy('model')
            ->orderBy('serial_number')
            ->get()
            ->map(function ($device) {
                return [
                    'id' => $device->id,
                    'serial_number' => $device->serial_number,
                    'device_uuid' => $device->device_uuid,
                    'model' => $device->model,
                    'hardware_version' => $device->hardware_version,
                    'firmware_version' => $device->firmware_version,
                    'display_name' => "{$device->model} - SN: {$device->serial_number} (HW: {$device->hardware_version})",
                ];
            });

        return Inertia::render('Clients/Devices/Create', [
            'client' => [
                'id' => $client->id,
                'full_name' => $client->full_name,
                'email' => $client->email,
            ],
            'availableDevices' => $availableDevices,
        ]);
    }

    /**
     * Store a newly created device in storage.
     */
    public function store(StoreClientDeviceRequest $request, Client $client)
    {
        $deviceInventory = DeviceInventory::findOrFail($request->device_inventory_id);

        // Verificar que el dispositivo esté disponible
        if ($deviceInventory->status !== 'available') {
            return back()->withErrors(['device_inventory_id' => 'Este dispositivo ya no está disponible.']);
        }

        // Crear el dispositivo del cliente
        $clientDevice = ClientDevice::create([
            'client_id' => $client->id,
            'device_inventory_id' => $request->device_inventory_id,
            'device_name' => $request->device_name,
            'mac_address' => $request->mac_address,
            'status' => 'pending', // Estado inicial
            'device_config' => $request->device_config ? json_decode($request->device_config, true) : null,
        ]);

        // Actualizar el estado del dispositivo en inventario
        $deviceInventory->update(['status' => 'sold']);

        return redirect()->route('clients.devices.index', $client)
            ->with('message', 'Dispositivo registrado exitosamente.');
    }

    /**
     * Display the specified device.
     */
    public function show(Client $client, ClientDevice $device): Response
    {
        // Verificar que el dispositivo pertenece al cliente
        if ($device->client_id !== $client->id) {
            abort(404);
        }

        // Cargar el dispositivo con todas sus relaciones incluyendo múltiples vehículos
        $device->load([
            'DeviceInventory',
            'vehicles' => function ($query) {
                $query->with(['sensors' => function ($sensorQuery) {
                    $sensorQuery->withPivot('is_active', 'frequency_seconds', 'last_reading_at');
                }])
                    ->orderBy('created_at', 'desc');
            }
        ]);

        return Inertia::render('Clients/Devices/Show', [
            'client' => [
                'id' => $client->id,
                'full_name' => $client->full_name,
                'email' => $client->email,
            ],
            'device' => [
                'id' => $device->id,
                'device_name' => $device->device_name,
                'mac_address' => $device->mac_address,
                'status' => $device->status,
                'activated_at' => $device->activated_at?->format('Y-m-d H:i:s'),
                'last_ping' => $device->last_ping?->format('Y-m-d H:i:s'),
                'device_config' => $device->device_config,
                'created_at' => $device->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $device->updated_at->format('Y-m-d H:i:s'),
                'device_inventory' => $device->DeviceInventory ? [
                    'id' => $device->DeviceInventory->id,
                    'serial_number' => $device->DeviceInventory->serial_number,
                    'device_uuid' => $device->DeviceInventory->device_uuid,
                    'model' => $device->DeviceInventory->model,
                    'hardware_version' => $device->DeviceInventory->hardware_version,
                    'firmware_version' => $device->DeviceInventory->firmware_version,
                    'manufactured_date' => $device->DeviceInventory->manufactured_date?->format('Y-m-d'),
                    'sold_date' => $device->DeviceInventory->sold_date?->format('Y-m-d'),
                ] : null,
                'vehicles' => $device->vehicles && $device->vehicles->count() > 0
                    ? $device->vehicles->map(function ($vehicle) {
                        return [
                            'id' => $vehicle->id,
                            'make' => $vehicle->make,
                            'model' => $vehicle->model,
                            'year' => $vehicle->year,
                            'license_plate' => $vehicle->license_plate,
                            'color' => $vehicle->color,
                            'nickname' => $vehicle->nickname,
                            'vin' => $vehicle->vin,
                            'protocol' => $vehicle->protocol,
                            'status' => $vehicle->status,
                            'auto_detected' => $vehicle->auto_detected,
                            'is_configured' => $vehicle->is_configured,
                            'first_reading_at' => $vehicle->first_reading_at?->format('Y-m-d H:i:s'),
                            'last_reading_at' => $vehicle->last_reading_at?->format('Y-m-d H:i:s'),
                            'created_at' => $vehicle->created_at->format('Y-m-d H:i:s'),
                            'sensors_count' => $vehicle->sensors ? $vehicle->sensors->count() : 0,
                            'active_sensors_count' => $vehicle->sensors ?
                                $vehicle->sensors->where('pivot.is_active', true)->count() : 0,
                            'supported_pids' => $vehicle->supported_pids,
                        ];
                    })
                    : [],
                'vehicles_count' => $device->vehicles ? $device->vehicles->count() : 0,

                'can' => [
                    'view' => true,
                    'update' => true,
                    'delete' => true,
                ]
            ],
        ]);
    }

    /**
     * Show the form for editing the specified device.
     */
    public function edit(Client $client, ClientDevice $device): Response
    {
        // Verificar que el dispositivo pertenece al cliente
        if ($device->client_id !== $client->id) {
            abort(404);
        }

        $device->load('DeviceInventory');

        return Inertia::render('Clients/Devices/Edit', [
            'client' => [
                'id' => $client->id,
                'full_name' => $client->full_name,
                'email' => $client->email,
            ],
            'device' => [
                'id' => $device->id,
                'device_inventory_id' => $device->device_inventory_id,
                'device_name' => $device->device_name,
                'mac_address' => $device->mac_address,
                'status' => $device->status,
                'device_config' => $device->device_config,
                'device_inventory' => $device->DeviceInventory ? [
                    'id' => $device->DeviceInventory->id,
                    'serial_number' => $device->DeviceInventory->serial_number,
                    'device_uuid' => $device->DeviceInventory->device_uuid,
                    'model' => $device->DeviceInventory->model,
                    'hardware_version' => $device->DeviceInventory->hardware_version,
                    'firmware_version' => $device->DeviceInventory->firmware_version,
                ] : null,
            ],
        ]);
    }

    /**
     * Update the specified device in storage.
     */
    public function update(UpdateClientDeviceRequest $request, Client $client, ClientDevice $device)
    {
        // Verificar que el dispositivo pertenece al cliente
        if ($device->client_id !== $client->id) {
            abort(404);
        }

        $device->update([
            'device_name' => $request->device_name,
            'mac_address' => $request->mac_address,
            'status' => $request->status,
            'device_config' => $request->device_config ? json_decode($request->device_config, true) : $device->device_config,
        ]);

        return redirect()->route('clients.devices.show', [$client, $device])
            ->with('message', 'Dispositivo actualizado exitosamente.');
    }

    /**
     * Remove the specified device from storage.
     */
    public function destroy(Client $client, ClientDevice $device)
    {
        // Verificar que el dispositivo pertenece al cliente
        if ($device->client_id !== $client->id) {
            abort(404);
        }

        // Liberar el dispositivo en inventario
        if ($device->DeviceInventory) {
            $device->DeviceInventory->update(['status' => 'available']);
        }

        $device->delete();

        return redirect()->route('clients.devices.index', $client)
            ->with('message', 'Dispositivo eliminado exitosamente.');
    }

    /**
     * Activate a device
     */
    public function activate(Client $client, ClientDevice $device)
    {
        if ($device->client_id !== $client->id) {
            abort(404);
        }

        $device->update([
            'status' => 'active',
            'activated_at' => now(),
            'last_ping' => now(),
        ]);

        return back()->with('message', 'Dispositivo activado exitosamente.');
    }

    /**
     * Deactivate a device
     */
    public function deactivate(Client $client, ClientDevice $device)
    {
        if ($device->client_id !== $client->id) {
            abort(404);
        }

        $device->update([
            'status' => 'inactive',
        ]);

        return back()->with('message', 'Dispositivo desactivado exitosamente.');
    }
}
