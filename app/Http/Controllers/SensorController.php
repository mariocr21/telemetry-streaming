<?php

namespace App\Http\Controllers;

use App\Models\Sensor;
use App\Models\VehicleSensor;
use App\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SensorController extends Controller
{
    /**
     * Verificar que el usuario sea Super Admin
     */
    private function ensureSuperAdmin()
    {
        $user = Auth::user();
        if ($user->role !== UserRole::SUPER_ADMIN) {
            abort(403, 'No tienes permisos para acceder a esta sección.');
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->ensureSuperAdmin();

        $query = Sensor::query();

        // Filtro de búsqueda
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('pid', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filtro por categoría
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filtro por tipo (estándar o custom)
        if ($request->filled('is_standard')) {
            $query->where('is_standard', $request->is_standard === 'true');
        }

        // Ordenamiento
        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // Obtener categorías únicas para el filtro
        $categories = Sensor::distinct()->pluck('category')->sort()->values();

        // Contar vehículos que usan cada sensor
        $sensors = $query->withCount('vehicleSensors')->paginate(15)->withQueryString();

        return Inertia::render('Admin/Sensors/Index', [
            'sensors' => $sensors,
            'categories' => $categories,
            'filters' => [
                'search' => $request->search,
                'category' => $request->category,
                'is_standard' => $request->is_standard,
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
            'stats' => [
                'total' => Sensor::count(),
                'standard' => Sensor::where('is_standard', true)->count(),
                'custom' => Sensor::where('is_standard', false)->count(),
                'categories_count' => Sensor::distinct('category')->count('category'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->ensureSuperAdmin();

        // Obtener categorías existentes para sugerencias
        $categories = Sensor::distinct()->pluck('category')->sort()->values();

        return Inertia::render('Admin/Sensors/Create', [
            'categories' => $categories,
            'dataTypes' => ['numeric', 'boolean', 'bit_encoded', 'string'],
            'sourceTypes' => ['OBD2', 'CAN_CUSTOM', 'GPS', 'ANALOG', 'DIGITAL', 'VIRTUAL'],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'pid' => 'required|string|max:10|unique:sensors,pid',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:50',
            'unit' => 'required|string|max:20',
            'data_type' => 'required|string|in:numeric,boolean,bit_encoded,string',
            'min_value' => 'nullable|numeric',
            'max_value' => 'nullable|numeric',
            'requires_calculation' => 'boolean',
            'calculation_formula' => 'nullable|string',
            'data_bytes' => 'integer|min:1|max:8',
            'is_standard' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $sensor = Sensor::create($validated);

        return redirect()
            ->route('admin.sensors.index')
            ->with('message', "Sensor '{$sensor->name}' creado exitosamente.");
    }

    /**
     * Display the specified resource.
     */
    public function show(Sensor $sensor): Response
    {
        $this->ensureSuperAdmin();

        // Cargar vehículos que usan este sensor
        $vehicleSensors = VehicleSensor::with(['vehicle.clientDevice.client'])
            ->where('sensor_id', $sensor->id)
            ->get();

        return Inertia::render('Admin/Sensors/Show', [
            'sensor' => $sensor,
            'vehicleSensors' => $vehicleSensors,
            'usageCount' => $vehicleSensors->count(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Sensor $sensor): Response
    {
        $this->ensureSuperAdmin();

        $categories = Sensor::distinct()->pluck('category')->sort()->values();

        return Inertia::render('Admin/Sensors/Edit', [
            'sensor' => $sensor,
            'categories' => $categories,
            'dataTypes' => ['numeric', 'boolean', 'bit_encoded', 'string'],
            'sourceTypes' => ['OBD2', 'CAN_CUSTOM', 'GPS', 'ANALOG', 'DIGITAL', 'VIRTUAL'],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Sensor $sensor)
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'pid' => 'required|string|max:10|unique:sensors,pid,' . $sensor->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:50',
            'unit' => 'required|string|max:20',
            'data_type' => 'required|string|in:numeric,boolean,bit_encoded,string',
            'min_value' => 'nullable|numeric',
            'max_value' => 'nullable|numeric',
            'requires_calculation' => 'boolean',
            'calculation_formula' => 'nullable|string',
            'data_bytes' => 'integer|min:1|max:8',
            'is_standard' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $sensor->update($validated);

        return redirect()
            ->route('admin.sensors.index')
            ->with('message', "Sensor '{$sensor->name}' actualizado exitosamente.");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sensor $sensor)
    {
        $this->ensureSuperAdmin();

        // Verificar si el sensor está en uso
        $usageCount = VehicleSensor::where('sensor_id', $sensor->id)->count();

        if ($usageCount > 0) {
            return redirect()
                ->route('admin.sensors.index')
                ->with('error', "No se puede eliminar el sensor '{$sensor->name}' porque está en uso por {$usageCount} vehículo(s).");
        }

        $sensorName = $sensor->name;
        $sensor->delete();

        return redirect()
            ->route('admin.sensors.index')
            ->with('message', "Sensor '{$sensorName}' eliminado exitosamente.");
    }
}
