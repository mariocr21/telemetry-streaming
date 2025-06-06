<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
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
                'create_client' => true, // Simplificado por ahora
            ]
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Clients/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreClientRequest $request)
    {
        // Crear el cliente
        $client = Client::create($request->validated());

        // Generar una contraseña aleatoria
        $password = 'password';

        // Crear el usuario asociado al cliente con rol CA (Client Admin)
        $user = User::create([
            'name' => $client->full_name,
            'email' => $client->email,
            'password' => bcrypt($password),
            'client_id' => $client->id,
            'role' => 'CA', // Client Admin por defecto para nuevos clientes
            'is_active' => true,
        ]);

        return redirect()->route('clients.show', $client->id)
            ->with('success', [
                'message' => 'Cliente y usuario creados exitosamente.',
                'user_created' => true,
                'user_email' => $user->email,
                'user_password' => $password, // Contraseña temporal para mostrar
                'user_name' => $user->name,
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
        // Cargar las relaciones users y devices con sus relaciones anidadas
        $client->load([
            'users',
            'devices' => function ($query) {
                $query->with(['DeviceInventory', 'vehicles'])
                    ->orderBy('created_at', 'desc');
            }
        ]);

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
                'users' => $client->users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'role_label' => match ($user->role) {
                            'SA' => 'Super Administrador',
                            'CA' => 'Administrador de Cliente',
                            'CU' => 'Usuario de Cliente',
                            default => 'Usuario de Cliente'
                        },
                        'is_active' => $user->is_active,
                        'created_at' => $user->created_at->format('Y-m-d H:i:s'),
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
                    'update' => true,
                    'delete' => true,
                ]
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Client $client): Response
    {
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
                    'delete' => true,
                ]
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClientRequest $request, Client $client)
    {
        $client->update($request->validated());

        return redirect()->route('clients.index')
            ->with('message', 'Cliente actualizado exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Client $client)
    {
        $client->delete();

        return redirect()->route('clients.index')
            ->with('message', 'Cliente eliminado exitosamente.');
    }
}
