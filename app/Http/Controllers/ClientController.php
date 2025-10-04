<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\User;
use App\UserRole; // ← Importar el enum
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ClientController extends Controller
{
    /**
     * Verificar si el usuario tiene acceso al cliente
     */
    private function canAccessClient(Client $client): bool
    {
        $user = Auth::user();

        // Si es SA, tiene acceso a todo
        if ($user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        // Si no es SA, solo puede acceder a su propio cliente
        return $user->client_id === $client->id;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        Log::info('User role: ' . $user->role->value);

        // Si no es SA, redirigir a su propio perfil de cliente
        if ($user->role !== UserRole::SUPER_ADMIN) {
            return redirect()->route('clients.show', $user->client_id);
        }

        $clients = Client::query()
            ->when($request->search, function ($query, $search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%");
            })
            ->when($request->sort, function ($query, $sort) use ($request) {
                $direction = $request->direction === 'desc' ? 'desc' : 'asc';
                $query->orderBy($sort, $direction);
            })
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Clients/Index', [
            'clients' => ClientResource::collection($clients),
            'filters' => $request->only(['search', 'sort', 'direction']),
            'can' => [
                'create_client' => $user->role === UserRole::SUPER_ADMIN,
            ]
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $user = Auth::user();

        // Solo SA puede crear clientes
        if ($user->role !== UserRole::SUPER_ADMIN) {
            abort(403, 'No tienes permiso para crear clientes.');
        }

        return Inertia::render('Clients/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreClientRequest $request)
    {
        $user = Auth::user();

        // Solo SA puede crear clientes
        if ($user->role !== UserRole::SUPER_ADMIN) {
            abort(403, 'No tienes permiso para crear clientes.');
        }

        // Crear el cliente
        $client = Client::create($request->validated());

        // Generar una contraseña aleatoria
        $password = $this->generateRandomPassword();

        // Crear el usuario asociado al cliente con rol CA (Client Admin)
        $newUser = User::create([
            'name' => $client->full_name,
            'email' => $client->email,
            'password' => bcrypt($password),
            'client_id' => $client->id,
            'role' => UserRole::CLIENT_ADMIN, // Usar el enum
            'is_active' => true,
        ]);

        return redirect()->route('clients.show', $client->id)
            ->with('success', [
                'message' => 'Cliente y usuario creados exitosamente.',
                'user_created' => true,
                'user_email' => $newUser->email,
                'user_password' => $password, // Contraseña temporal para mostrar
                'user_name' => $newUser->name,
                'user_role' => 'CA',
                'user_role_label' => 'Administrador de Cliente',
            ]);
    }

    /**
     * Generar una contraseña aleatoria segura
     */
    private function generateRandomPassword($length = 12): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';

        // Asegurar que tenga al menos una mayúscula, una minúscula, un número y un símbolo
        $password .= chr(rand(65, 90)); // Mayúscula
        $password .= chr(rand(97, 122)); // Minúscula  
        $password .= rand(0, 9); // Número
        $password .= '!@#$%^&*'[rand(0, 7)]; // Símbolo

        // Completar el resto de la longitud
        for ($i = 4; $i < $length; $i++) {
            $password .= $characters[rand(0, strlen($characters) - 1)];
        }

        // Mezclar los caracteres
        return str_shuffle($password);
    }

    /**
     * Display the specified resource.
     */
    public function show(Client $client): Response
    {
        $user = Auth::user();

        // Verificar permisos de acceso
        if (!$this->canAccessClient($client)) {
            abort(403, 'No tienes permiso para ver este cliente.');
        }

        // Cargar las relaciones users y devices con sus relaciones anidadas
        $client->load([
            'users',
            'devices' => function ($query) {
                $query->with(['DeviceInventory', 'vehicles'])
                    ->orderBy('created_at', 'desc');
            }
        ]);

        $isSuperAdmin = $user->role === UserRole::SUPER_ADMIN;
        $isClientAdmin = $user->role === UserRole::CLIENT_ADMIN && $user->client_id === $client->id;

        return Inertia::render('Clients/Show', [
            'client' => [
                'id' => $client->id,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'full_name' => $client->full_name,
                'email' => $client->email,
                'phone' => $client->phone,
                'address' => $client->address,
                'city' => $client->city,
                'state' => $client->state,
                'zip_code' => $client->zip_code,
                'country' => $client->country,
                'company' => $client->company,
                'job_title' => $client->job_title,
                'created_at' => $client->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $client->updated_at->format('Y-m-d H:i:s'),
                'users' => $client->users->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'name' => $u->name,
                        'email' => $u->email,
                        'role' => $u->role->value, // Obtener el valor del enum
                        'role_label' => $u->role->label(), // Usar el método label del enum
                        'is_active' => $u->is_active,
                        'created_at' => $u->created_at->format('Y-m-d H:i:s'),
                    ];
                }),
                'devices' => $client->devices->map(function ($device) {
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
                        'vehicle' => optional($device->vehicles->sortByDesc('created_at')->first(), function ($vehicle) {
                            return [
                                'id' => $vehicle->id,
                                'make' => $vehicle->make,
                                'model' => $vehicle->model,
                                'year' => $vehicle->year,
                                'license_plate' => $vehicle->license_plate,
                                'nickname' => $vehicle->nickname,
                            ];
                        }),
                        'can' => [
                            'view' => true,
                            'update' => true,
                            'delete' => true,
                        ]
                    ];
                }),
                'can' => [
                    'view' => true,
                    'update' => $isSuperAdmin || $isClientAdmin,
                    'delete' => $isSuperAdmin,
                ]
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Client $client): Response
    {
        $user = Auth::user();

        // Verificar permisos de acceso y edición
        if (!$this->canAccessClient($client)) {
            abort(403, 'No tienes permiso para editar este cliente.');
        }

        // Solo SA o CA del mismo cliente pueden editar
        $canEdit = $user->role === UserRole::SUPER_ADMIN ||
            ($user->role === UserRole::CLIENT_ADMIN && $user->client_id === $client->id);

        if (!$canEdit) {
            abort(403, 'No tienes permiso para editar este cliente.');
        }

        return Inertia::render('Clients/Edit', [
            'client' => [
                'id' => $client->id,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'full_name' => $client->full_name,
                'email' => $client->email,
                'phone' => $client->phone,
                'address' => $client->address,
                'city' => $client->city,
                'state' => $client->state,
                'zip_code' => $client->zip_code,
                'country' => $client->country,
                'company' => $client->company,
                'job_title' => $client->job_title,
                'created_at' => $client->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $client->updated_at->format('Y-m-d H:i:s'),
                'can' => [
                    'view' => true,
                    'update' => true,
                    'delete' => $user->role === UserRole::SUPER_ADMIN,
                ]
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClientRequest $request, Client $client)
    {
        $user = Auth::user();

        // Verificar permisos
        if (!$this->canAccessClient($client)) {
            abort(403, 'No tienes permiso para actualizar este cliente.');
        }

        $canEdit = $user->role === UserRole::SUPER_ADMIN ||
            ($user->role === UserRole::CLIENT_ADMIN && $user->client_id === $client->id);

        if (!$canEdit) {
            abort(403, 'No tienes permiso para actualizar este cliente.');
        }

        $client->update($request->validated());

        return redirect()->route('clients.show', $client->id)
            ->with('message', 'Cliente actualizado exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Client $client)
    {
        $user = Auth::user();

        // Solo SA puede eliminar clientes
        if ($user->role !== UserRole::SUPER_ADMIN) {
            abort(403, 'No tienes permiso para eliminar clientes.');
        }

        $client->delete();

        return redirect()->route('clients.index')
            ->with('message', 'Cliente eliminado exitosamente.');
    }
}
