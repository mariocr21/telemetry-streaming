<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientDevice;
use App\Models\Vehicle;
use App\Models\Sensor;
use App\Models\Register;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;

class VehicleController extends Controller
{
    /**
     * Display a listing of vehicles for a specific device.
     */
    public function index(Client $client, ClientDevice $device): Response
    {
        // Verificar que el dispositivo pertenece al cliente
        if ($device->client_id !== $client->id) {
            abort(404);
        }

        $vehicles = $device->vehicles()
            ->with(['vehicleSensors.sensor'])
            ->when(request('search'), function ($query, $search) {
                $query->where('make', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('license_plate', 'like', "%{$search}%")
                    ->orWhere('vin', 'like', "%{$search}%")
                    ->orWhere('nickname', 'like', "%{$search}%");
            })
            ->when(request('status'), function ($query, $status) {
                if ($status !== 'all') {
                    $query->where('status', $status === 'active');
                }
            })
            ->orderBy('status', 'desc') // Activos primero
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        // Asegurar que siempre tenemos la estructura correcta
        $vehiclesData = [
            'data' => $vehicles->items(),
            'links' => $vehicles->linkCollection()->toArray(),
            'meta' => [
                'current_page' => $vehicles->currentPage(),
                'last_page' => $vehicles->lastPage(),
                'from' => $vehicles->firstItem(),
                'to' => $vehicles->lastItem(),
                'total' => $vehicles->total(),
                'per_page' => $vehicles->perPage(),
            ]
        ];

        return Inertia::render('Clients/Devices/Vehicles/Index', [
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
            ],
            'vehicles' => [
                'data' => collect($vehiclesData['data'])->map(function ($vehicle) {
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
                        'auto_detected' => $vehicle->auto_detected,
                        'is_configured' => $vehicle->is_configured,
                        'display_name' => $vehicle->display_name,
                        'last_reading_at' => $vehicle->last_reading_at?->format('Y-m-d H:i:s'),
                        'created_at' => $vehicle->created_at->format('Y-m-d H:i:s'),
                        'sensors_count' => $vehicle->vehicleSensors->count(),
                        'active_sensors_count' => $vehicle->vehicleSensors->where('is_active', true)->count(),
                        'can' => [
                            'view' => true,
                            'update' => true,
                            'deactivate' => $vehicle->status,
                            'activate' => !$vehicle->status,
                        ]
                    ];
                })->toArray(),
                'links' => $vehiclesData['links'],
                'meta' => $vehiclesData['meta']
            ],
            'filters' => request()->only(['search', 'status']),
            'can' => [
                'create_vehicle' => true,
            ]
        ]);
    }

    /**
     * Show the form for creating a new vehicle.
     */
    public function create(Client $client, ClientDevice $device): Response
    {
        // Verificar que el dispositivo pertenece al cliente
        if ($device->client_id !== $client->id) {
            abort(404);
        }

        return Inertia::render('Clients/Devices/Vehicles/Create', [
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
            ],
            // Años disponibles (desde 1990 hasta año actual + 2)
            'available_years' => range(date('Y') + 2, 1990),
            // Marcas más comunes (se puede expandir o traer de una tabla)
            'common_makes' => [
                'Toyota',
                'Honda',
                'Ford',
                'Chevrolet',
                'Nissan',
                'Hyundai',
                'Kia',
                'Volkswagen',
                'BMW',
                'Mercedes-Benz',
                'Audi',
                'Mazda',
                'Subaru',
                'Lexus',
                'Jeep',
                'GMC',
                'Dodge',
                'Ram',
                'Buick',
                'Cadillac',
                'Lincoln',
                'Acura',
                'Infiniti',
                'Volvo',
                'Jaguar',
                'Land Rover',
                'Porsche',
                'Tesla',
                'Mitsubishi',
                'Suzuki'
            ]
        ]);
    }

    /**
     * Store a newly created vehicle.
     */
    public function store(Request $request, Client $client, ClientDevice $device)
    {
        // Verificar que el dispositivo pertenece al cliente
        if ($device->client_id !== $client->id) {
            abort(404);
        }

        $request->validate([
            'make' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 2),
            'license_plate' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'nickname' => 'nullable|string|max:255',
            'vin' => 'nullable|string|max:17',
        ], [
            'make.required' => 'La marca es obligatoria',
            'model.required' => 'El modelo es obligatorio',
            'year.required' => 'El año es obligatorio',
            'year.min' => 'El año debe ser mayor a 1900',
            'year.max' => 'El año no puede ser mayor al año actual + 2',
            'vin.size' => 'El VIN debe tener exactamente 17 caracteres',
        ]);

        // Verificar que el VIN no exista para este cliente (si se proporciona)
        if ($request->vin) {
            $existingVehicle = Vehicle::where('client_id', $client->id)
                ->where('vin', $request->vin)
                ->first();

            if ($existingVehicle) {
                return back()->withErrors(['vin' => 'Ya existe un vehículo con este VIN en su cuenta.']);
            }
        }

        // Crear el vehículo
        $vehicle = Vehicle::create([
            'client_id' => $client->id,
            'client_device_id' => $device->id,
            'make' => $request->make,
            'model' => $request->model,
            'year' => $request->year,
            'license_plate' => $request->license_plate,
            'color' => $request->color,
            'nickname' => $request->nickname,
            'vin' => $request->vin,
            'auto_detected' => false, // Registro manual
            'is_configured' => true, // Marcamos como configurado al ser manual
            'status' => true, // Activo por defecto
            'first_reading_at' => now(),
        ]);

        return redirect()
            ->route('clients.devices.vehicles.show', [$client, $device, $vehicle])
            ->with('message', 'Vehículo registrado exitosamente.');
    }

    /**
     * Show the form for editing the specified vehicle.
     */
    public function edit(Client $client, ClientDevice $device, Vehicle $vehicle): Response
    {
        // Verificar permisos
        if ($vehicle->client_device_id !== $device->id || $device->client_id !== $client->id) {
            abort(404);
        }

        return Inertia::render('Clients/Devices/Vehicles/Edit', [
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
            ],
            'vehicle' => [
                'id' => $vehicle->id,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'license_plate' => $vehicle->license_plate,
                'color' => $vehicle->color,
                'nickname' => $vehicle->nickname,
                'vin' => $vehicle->vin,
                'auto_detected' => $vehicle->auto_detected,
                'is_configured' => $vehicle->is_configured,
                'status' => $vehicle->status,
            ],
            'available_years' => range(date('Y') + 2, 1990),
            'common_makes' => [
                'Toyota',
                'Honda',
                'Ford',
                'Chevrolet',
                'Nissan',
                'Hyundai',
                'Kia',
                'Volkswagen',
                'BMW',
                'Mercedes-Benz',
                'Audi',
                'Mazda',
                'Subaru',
                'Lexus',
                'Jeep',
                'GMC',
                'Dodge',
                'Ram',
                'Buick',
                'Cadillac',
                'Lincoln',
                'Acura',
                'Infiniti',
                'Volvo',
                'Jaguar',
                'Land Rover',
                'Porsche',
                'Tesla',
                'Mitsubishi',
                'Suzuki'
            ]
        ]);
    }

    /**
     * Update the specified vehicle.
     */
    public function update(Request $request, Client $client, ClientDevice $device, Vehicle $vehicle)
    {
        // Verificar permisos
        if ($vehicle->client_device_id !== $device->id || $device->client_id !== $client->id) {
            abort(404);
        }

        $request->validate([
            'make' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 2),
            'license_plate' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'nickname' => 'nullable|string|max:255',
            'vin' => 'nullable|string|max:17',
        ], [
            'make.required' => 'La marca es obligatoria',
            'model.required' => 'El modelo es obligatorio',
            'year.required' => 'El año es obligatorio',
            'year.min' => 'El año debe ser mayor a 1900',
            'year.max' => 'El año no puede ser mayor al año actual + 2',
            'vin.size' => 'El VIN debe tener exactamente 17 caracteres',
        ]);

        // Verificar que el VIN no exista para otros vehículos del cliente (si se proporciona y cambió)
        if ($request->vin && $request->vin !== $vehicle->vin) {
            $existingVehicle = Vehicle::where('client_id', $client->id)
                ->where('vin', $request->vin)
                ->where('id', '!=', $vehicle->id)
                ->first();

            if ($existingVehicle) {
                return back()->withErrors(['vin' => 'Ya existe otro vehículo con este VIN en su cuenta.']);
            }
        }

        // Actualizar el vehículo
        $vehicle->update([
            'make' => $request->make,
            'model' => $request->model,
            'year' => $request->year,
            'license_plate' => $request->license_plate,
            'color' => $request->color,
            'nickname' => $request->nickname,
            'vin' => $request->vin,
        ]);

        return redirect()
            ->route('clients.devices.vehicles.show', [$client, $device, $vehicle])
            ->with('message', 'Vehículo actualizado exitosamente.');
    }

    /**
     * "Delete" (deactivate) the specified vehicle.
     */
    public function destroy(Client $client, ClientDevice $device, Vehicle $vehicle)
    {
        // Verificar permisos
        if ($vehicle->client_device_id !== $device->id || $device->client_id !== $client->id) {
            abort(404);
        }

        // No eliminar, solo desactivar
        $vehicle->update(['status' => false]);

        return redirect()
            ->route('clients.devices.vehicles.index', [$client, $device])
            ->with('message', 'Vehículo desactivado exitosamente.');
    }

    /**
     * Activate a vehicle
     */
    public function activate(Client $client, ClientDevice $device, Vehicle $vehicle)
    {
        // Verificar permisos
        if ($vehicle->client_device_id !== $device->id || $device->client_id !== $client->id) {
            abort(404);
        }

        $vehicle->update(['status' => true]);

        return back()->with('message', 'Vehículo activado exitosamente.');
    }

    /**
     * Deactivate a vehicle
     */
    public function deactivate(Client $client, ClientDevice $device, Vehicle $vehicle)
    {
        // Verificar permisos
        if ($vehicle->client_device_id !== $device->id || $device->client_id !== $client->id) {
            abort(404);
        }

        $vehicle->update(['status' => false]);

        return back()->with('message', 'Vehículo desactivado exitosamente.');
    }

    /**
     * Sync sensors for a vehicle based on supported PIDs
     */
    public function syncSensors(Client $client, ClientDevice $device, Vehicle $vehicle)
    {
        // Verificar permisos
        if ($vehicle->client_device_id !== $device->id || $device->client_id !== $client->id) {
            abort(404);
        }

        if (!$vehicle->supported_pids) {
            return back()->withErrors(['message' => 'No hay PIDs soportados para sincronizar.']);
        }

        $syncedCount = 0;
        $supportedPidList = array_keys(array_filter($vehicle->supported_pids));

        if (empty($supportedPidList)) {
            return back()->withErrors(['message' => 'No hay PIDs válidos para sincronizar.']);
        }

        // Obtener sensores disponibles que coincidan con los PIDs soportados
        $availableSensors = Sensor::whereIn('pid', $supportedPidList)->get();

        foreach ($availableSensors as $sensor) {
            // Crear o actualizar la relación vehicle_sensor
            $vehicleSensor = $vehicle->vehicleSensors()
                ->where('sensor_id', $sensor->id)
                ->first();

            if (!$vehicleSensor) {
                $vehicle->vehicleSensors()->create([
                    'sensor_id' => $sensor->id,
                    'is_active' => true,
                    'frequency_seconds' => 5, // Frecuencia por defecto
                    'min_value' => $sensor->min_value,
                    'max_value' => $sensor->max_value,
                ]);
                $syncedCount++;
            }
        }

        $message = $syncedCount > 0
            ? "Se sincronizaron {$syncedCount} sensores exitosamente."
            : "No se encontraron nuevos sensores para sincronizar.";

        return back()->with('message', $message);
    }
    public function show(Client $client, ClientDevice $device, Vehicle $vehicle): Response
    {
        // Verificar que el vehículo pertenece al dispositivo del cliente
        if ($vehicle->client_device_id !== $device->id || $device->client_id !== $client->id) {
            abort(404);
        }

        // Cargar el vehículo con todas sus relaciones
        $vehicle->load([
            'clientDevice.DeviceInventory',
            'vehicleSensors.sensor',
            'client'
        ]);

        // Obtener los sensores con datos recientes
        $sensorsWithRecentData = $vehicle->vehicleSensors()
            ->orderBy('is_active', 'desc')
            ->with(['sensor'])
            ->get()
            ->map(function ($vehicleSensor) use ($vehicle) {
                // Obtener los últimos 10 registros para este sensor
                $recentRegisters = Register::where('vehicle_sensor_id', $vehicleSensor->id)
                    ->orderBy('recorded_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($register) {
                        return [
                            'id' => $register->id,
                            'value' => (float) $register->value,
                            'recorded_at' => $register->recorded_at ? $register->recorded_at->format('Y-m-d H:i:s') : null,
                            'recorded_at_human' => $register->recorded_at ? $register->recorded_at->diffForHumans() : 'Sin fecha',
                        ];
                    });

                // Calcular estadísticas básicas
                $values = $recentRegisters->pluck('value')->filter();
                $stats = null;
                if ($values->count() > 0) {
                    $stats = [
                        'min' => $values->min(),
                        'max' => $values->max(),
                        'avg' => round($values->avg(), 2),
                        'current' => $values->first(), // El valor más reciente
                        'count' => $values->count()
                    ];
                }

                return [
                    'id' => $vehicleSensor->id,
                    'is_active' => $vehicleSensor->is_active,
                    'frequency_seconds' => $vehicleSensor->frequency_seconds,
                    'min_value' => $vehicleSensor->min_value,
                    'max_value' => $vehicleSensor->max_value,
                    'last_reading_at' => $vehicleSensor->last_reading_at?->format('Y-m-d H:i:s'),
                    'sensor' => [
                        'id' => $vehicleSensor->sensor->id,
                        'pid' => $vehicleSensor->sensor->pid,
                        'name' => $vehicleSensor->sensor->name,
                        'description' => $vehicleSensor->sensor->description,
                        'category' => $vehicleSensor->sensor->category,
                        'unit' => $vehicleSensor->sensor->unit,
                        'data_type' => $vehicleSensor->sensor->data_type,
                        'is_standard' => $vehicleSensor->sensor->is_standard,
                    ],
                    'recent_registers' => $recentRegisters,
                    'stats' => $stats,
                ];
            });

        // Agrupar sensores por categoría
        $sensorsByCategory = $sensorsWithRecentData->groupBy(function ($item) {
            return $item['sensor']['category'];
        });

        // Estadísticas generales del vehículo
        $totalSensors = $sensorsWithRecentData->count();
        $activeSensors = $sensorsWithRecentData->where('is_active', true)->count();
        $sensorsWithRecentActivity = $sensorsWithRecentData->filter(function ($sensor) {
            return $sensor['last_reading_at'] &&
                now()->diffInHours($sensor['last_reading_at']) < 24;
        })->count();

        // Obtener actividad reciente general (últimos 50 registros de cualquier sensor)
        $recentActivity = Register::whereHas('sensor.vehicle', function ($query) use ($vehicle) {
            $query->where('id', $vehicle->id);
        })
            ->with(['sensor.sensor'])
            ->orderBy('recorded_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($register) {
                return [
                    'id' => $register->id,
                    'value' => (float) $register->value,
                    'recorded_at' => $register->recorded_at ? $register->recorded_at->format('Y-m-d H:i:s') : null,
                    'recorded_at_human' => $register->recorded_at ? $register->recorded_at->diffForHumans() : 'Sin fecha',
                    'sensor_name' => $register->sensor->sensor->name ?? 'Sensor desconocido',
                    'sensor_unit' => $register->sensor->sensor->unit ?? '',
                    'sensor_category' => $register->sensor->sensor->category ?? 'general',
                ];
            });

        return Inertia::render('Clients/Devices/Vehicles/Show', [
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
                'device_inventory' => $device->DeviceInventory ? [
                    'model' => $device->DeviceInventory->model,
                    'serial_number' => $device->DeviceInventory->serial_number,
                ] : null,
            ],
            'vehicle' => [
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
                'supported_pids' => $vehicle->supported_pids,
            ],
            'sensors' => $sensorsWithRecentData->values()->toArray(),
            'sensors_by_category' => $sensorsByCategory->toArray(),
            'vehicle_stats' => [
                'total_sensors' => $totalSensors,
                'active_sensors' => $activeSensors,
                'sensors_with_recent_data' => $sensorsWithRecentActivity,
                'configuration_progress' => $totalSensors > 0 ? round(($activeSensors / $totalSensors) * 100) : 0,
            ],
            'recent_activity' => $recentActivity->toArray(),
            'can' => [
                'view' => true,
                'update' => true,
                'delete' => true,
                'configure_sensors' => true,
            ]
        ]);
    }

    /**
     * Get sensor data for charts (AJAX endpoint)
     */
    public function getSensorData(Client $client, ClientDevice $device, Vehicle $vehicle, Request $request)
    {
        // Verificar permisos
        if ($vehicle->client_device_id !== $device->id || $device->client_id !== $client->id) {
            abort(404);
        }

        $sensorId = $request->get('sensor_id');
        $hours = $request->get('hours', 24); // Por defecto últimas 24 horas

        if (!$sensorId) {
            return response()->json(['error' => 'sensor_id is required'], 400);
        }

        // Verificar que el sensor pertenece al vehículo
        $vehicleSensor = $vehicle->vehicleSensors()
            ->where('sensor_id', $sensorId)
            ->first();

        if (!$vehicleSensor) {
            return response()->json(['error' => 'Sensor not found for this vehicle'], 404);
        }

        // Obtener datos del sensor para el período especificado
        $data = Register::where('vehicle_sensor_id', $vehicleSensor->id)
            ->where('recorded_at', '>=', now()->subHours($hours))
            ->orderBy('recorded_at', 'asc')
            ->get()
            ->map(function ($register) {
                return [
                    'x' => $register->recorded_at->format('Y-m-d H:i:s'),
                    'y' => (float) $register->value,
                ];
            });

        return response()->json([
            'data' => $data,
            'sensor' => [
                'name' => $vehicleSensor->sensor->name,
                'unit' => $vehicleSensor->sensor->unit,
                'category' => $vehicleSensor->sensor->category,
            ]
        ]);
    }

    /**
     * Toggle sensor active status
     */
    public function toggleSensor(Client $client, ClientDevice $device, Vehicle $vehicle, Request $request)
    {
        if ($vehicle->client_device_id !== $device->id || $device->client_id !== $client->id) {
            abort(404);
        }

        $vehicleSensorId = $request->get('vehicle_sensor_id');

        $vehicleSensor = $vehicle->vehicleSensors()
            ->where('id', $vehicleSensorId)
            ->first();

        if (!$vehicleSensor) {
            return response()->json(['error' => 'Sensor not found'], 404);
        }

        $vehicleSensor->update([
            'is_active' => !$vehicleSensor->is_active
        ]);

        return response()->json([
            'success' => true,
            'is_active' => $vehicleSensor->is_active
        ]);
    }

    /**
     * Update sensor configuration
     */
    public function updateSensorConfig(Client $client, ClientDevice $device, Vehicle $vehicle, Request $request)
    {
        if ($vehicle->client_device_id !== $device->id || $device->client_id !== $client->id) {
            abort(404);
        }

        $vehicleSensorId = $request->get('vehicle_sensor_id');

        $vehicleSensor = $vehicle->vehicleSensors()
            ->where('id', $vehicleSensorId)
            ->first();

        if (!$vehicleSensor) {
            return response()->json(['error' => 'Sensor not found'], 404);
        }

        $request->validate([
            'frequency_seconds' => 'required|integer|min:1|max:3600',
            'min_value' => 'nullable|numeric',
            'max_value' => 'nullable|numeric',
        ]);

        $vehicleSensor->update([
            'frequency_seconds' => $request->frequency_seconds,
            'min_value' => $request->min_value,
            'max_value' => $request->max_value,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Configuración del sensor actualizada exitosamente'
        ]);
    }
    /**
     * Export sensor data to CSV
     */
    public function exportSensorData(Client $client, ClientDevice $device, Vehicle $vehicle, Request $request)
    {
        // Verificar permisos
        // if (!$this->canAccessClient($client)) {
        //     abort(403);
        // }

        if ($vehicle->client_device_id !== $device->id || $device->client_id !== $client->id) {
            abort(404);
        }

        $request->validate([
            'vehicle_sensor_ids' => 'required|string',
            'date_range_type' => 'required|in:day,range',
            'date' => 'required_if:date_range_type,day|date',
            'start_date' => 'required_if:date_range_type,range|date',
            'end_date' => 'required_if:date_range_type,range|date|after_or_equal:start_date',
        ]);

        $sensorIds = explode(',', $request->vehicle_sensor_ids);

        // Query base
        $query = Register::whereIn('vehicle_sensor_id', $sensorIds)
            ->with(['sensor.sensor']);

        // Filtrar por fecha
        if ($request->date_range_type === 'day') {
            $query->whereDate('recorded_at', $request->date);
        } else {
            $query->whereBetween('recorded_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }

        $registers = $query->orderBy('recorded_at', 'asc')->get();

        // Generar CSV
        $csvData = [];

        // Encabezados
        $csvData[] = [
            'Fecha y Hora',
            'Sensor',
            'PID',
            'Categoría',
            'Valor',
            'Unidad',
            'Vehículo'
        ];

        // Datos
        foreach ($registers as $register) {
            $csvData[] = [
                $register->recorded_at->format('Y-m-d H:i:s'),
                $register->sensor->sensor->name ?? 'N/A',
                $register->sensor->sensor->pid ?? 'N/A',
                $register->sensor->sensor->category ?? 'N/A',
                (float) $register->value,
                $register->sensor->sensor->unit ?? '',
                $vehicle->display_name
            ];
        }

        // Crear el CSV
        $filename = 'sensores_' . $vehicle->id . '_' . date('Ymd_His') . '.csv';

        $callback = function () use ($csvData) {
            $file = fopen('php://output', 'w');

            // BOM para UTF-8
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            foreach ($csvData as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
