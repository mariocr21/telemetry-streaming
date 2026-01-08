<?php

namespace App\Http\Controllers;

use App\UserRole;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Hash;

class ClientAdminController extends Controller
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
     * Display a listing of all clients (Super Admin only)
     */
    public function index(Request $request): Response
    {
        $this->ensureSuperAdmin();

        $query = Client::query()
            ->withCount(['devices', 'users'])
            ->with([
                'devices' => function ($q) {
                    $q->withCount('vehicles');
                }
            ]);

        // Search filter
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $allowedSorts = ['first_name', 'last_name', 'email', 'company', 'created_at'];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        }

        // Paginate
        $clients = $query->paginate(15)->withQueryString();

        // Transform data
        $clientsData = $clients->through(function ($client) {
            $vehiclesCount = $client->devices->sum('vehicles_count');
            return [
                'id' => $client->id,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'full_name' => $client->full_name,
                'email' => $client->email,
                'phone' => $client->phone,
                'company' => $client->company,
                'city' => $client->city,
                'country' => $client->country,
                'devices_count' => $client->devices_count,
                'users_count' => $client->users_count,
                'vehicles_count' => $vehiclesCount,
                'created_at' => $client->created_at->format('Y-m-d H:i'),
            ];
        });

        // Stats
        $stats = [
            'total' => Client::count(),
            'with_devices' => Client::has('devices')->count(),
            'with_users' => Client::has('users')->count(),
            'recent' => Client::where('created_at', '>=', now()->subDays(30))->count(),
        ];

        return Inertia::render('Admin/Clients/Index', [
            'clients' => $clientsData,
            'filters' => $request->only(['search', 'sort', 'direction']),
            'stats' => $stats,
        ]);
    }

    /**
     * Store a new client
     */
    public function store(Request $request)
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:clients,email',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:150',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'job_title' => 'nullable|string|max:100',
            'create_user' => 'boolean',
        ]);

        // Create client
        $client = Client::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'company' => $validated['company'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'zip_code' => $validated['zip_code'] ?? null,
            'country' => $validated['country'] ?? null,
            'job_title' => $validated['job_title'] ?? null,
        ]);

        $message = "Cliente '{$client->full_name}' creado exitosamente.";

        // Optionally create user
        if ($request->boolean('create_user')) {
            $password = $this->generateRandomPassword();

            User::create([
                'name' => $client->full_name,
                'email' => $client->email,
                'password' => Hash::make($password),
                'client_id' => $client->id,
                'role' => UserRole::CLIENT_ADMIN,
                'is_active' => true,
            ]);

            return back()->with([
                'message' => $message,
                'user_created' => true,
                'user_password' => $password,
            ]);
        }

        return back()->with('message', $message);
    }

    /**
     * Show client details - DEBUG MODE
     */
    public function show(Client $client): Response
    {
        $this->ensureSuperAdmin();

        // 1. Carga básica para verificar que la página renderiza
        $clientData = [
            'id' => $client->id,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'full_name' => $client->full_name,
            'email' => $client->email,
            'phone' => $client->phone,
            'company' => $client->company,
            'city' => $client->city,
            'country' => $client->country,
            'address' => $client->address,
            'created_at' => $client->created_at ? $client->created_at->format('Y-m-d H:i') : 'N/A',
        ];

        // 2. Carga de Usuarios e Inventario
        $client->load('users');

        // Transform Users
        $users = $client->users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => is_object($user->role) ? $user->role->value : $user->role,
                'is_active' => $user->is_active ?? true,
                'last_login' => $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i') : null,
            ];
        });

        // Available Inventory (Corregido: clientDevices en plural)
        $availableInventory = \App\Models\DeviceInventory::whereDoesntHave('clientDevices')
            ->get()
            ->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'serial_number' => $inv->serial_number,
                    'model' => $inv->model,
                ];
            });

        // 3. Carga de Dispositivos con Vehículos y Sensores
        $client->load([
            'devices.DeviceInventory',
            'devices.vehicles' => function ($q) {
                $q->withCount('vehicleSensors');
            },
            'devices.vehicles.vehicleSensors.sensor',
            'devices.vehicles.dashboardLayouts',
        ]);

        $devices = $client->devices->map(function ($device) {
            return [
                'id' => $device->id,
                'device_name' => $device->device_name ?? 'Sin nombre',
                'mac_address' => $device->mac_address ?? 'N/A',
                'status' => $device->status ?? 'unknown',
                'last_ping' => $device->last_ping,
                'inventory' => $device->DeviceInventory ? [
                    'serial_number' => $device->DeviceInventory->serial_number ?? 'N/A',
                    'model' => $device->DeviceInventory->model ?? 'N/A',
                ] : null,
                'vehicles' => $device->vehicles ? $device->vehicles->map(function ($vehicle) {
                    // Validar relaciones que pueden ser nulas
                    $sensors = $vehicle->vehicleSensors ?? collect([]);
                    $layouts = $vehicle->dashboardLayouts ?? collect([]);

                    $hasDashboard = $layouts->where('is_active', true)->count() > 0;

                    return [
                        'id' => $vehicle->id,
                        'make' => $vehicle->make,
                        'model' => $vehicle->model,
                        'year' => $vehicle->year,
                        'nickname' => $vehicle->nickname,
                        'license_plate' => $vehicle->license_plate,
                        'vin' => $vehicle->vin,
                        'status' => $vehicle->status,
                        'sensors_count' => $vehicle->vehicle_sensors_count ?? 0,
                        'has_dashboard' => $hasDashboard,
                        'sensors' => $sensors->map(function ($vs) {
                            return [
                                'id' => $vs->id,
                                'sensor_id' => $vs->sensor_id,
                                'name' => $vs->sensor->name ?? 'Unknown',
                                'pid' => $vs->sensor->pid ?? 'N/A',
                                'unit' => $vs->sensor->unit ?? '',
                                'is_active' => $vs->is_active ?? false,
                            ];
                        })->values()->toArray(),
                    ];
                })->values()->toArray() : [],
                'vehicles_count' => $device->vehicles ? $device->vehicles->count() : 0,
            ];
        })->values()->toArray();

        // Stats completas
        $stats = [
            'devices_count' => count($devices),
            'vehicles_count' => collect($devices)->sum('vehicles_count'),
            'sensors_count' => collect($devices)->sum(fn($d) => collect($d['vehicles'])->sum('sensors_count')),
            'users_count' => $client->users->count(),
        ];

        return Inertia::render('Admin/Clients/Show', [
            'client' => $clientData,
            'devices' => $devices,
            'users' => $users,
            'availableInventory' => $availableInventory,
            'stats' => $stats,
        ]);
    }

    /**
     * Update client information
     */
    public function update(Request $request, Client $client)
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:clients,email,' . $client->id,
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:150',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'job_title' => 'nullable|string|max:100',
        ]);

        $client->update($validated);

        return back()->with('message', "Cliente '{$client->full_name}' actualizado exitosamente.");
    }

    /**
     * Delete a client
     */
    public function destroy(Client $client)
    {
        $this->ensureSuperAdmin();

        $clientName = $client->full_name;

        // Check dependencies
        $devicesCount = $client->devices()->count();
        if ($devicesCount > 0) {
            return back()->with('error', "No se puede eliminar el cliente. Tiene {$devicesCount} dispositivos asignados.");
        }

        // Delete associated users first
        $client->users()->delete();

        // Soft delete client
        $client->delete();

        return back()->with('message', "Cliente '{$clientName}' eliminado exitosamente.");
    }

    /**
     * Generate random password
     */
    private function generateRandomPassword($length = 12): string
    {
        $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
}
