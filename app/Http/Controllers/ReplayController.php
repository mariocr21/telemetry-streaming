<?php

namespace App\Http\Controllers;

use App\Models\Register;
use App\Models\Vehicle;
use App\Models\DiagnosticTroubleCode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ReplayController extends Controller
{
    /**
     * Render the replay view
     */
public function index(Request $request)
{
    $user = $request->user();

    $vehiclesQuery = Vehicle::query()
        ->with(['client', 'clientDevice', 'vehicleSensors.sensor']);

    if (!$user->isSuperAdmin()) {
        $vehiclesQuery->whereHas('client', function ($q) use ($user) {
            $q->where('id', $user->client_id);
        });
    }

    $vehicles = $vehiclesQuery
        ->orderBy('vin')
        ->get();

    return Inertia::render('replays/Index', [
        'vehicles' => $vehicles,
    ]);
}

    /**
     * Obtener fechas disponibles para las que hay datos (últimos 30 días con datos)
     */
    private function getAvailableDates()
    {
        $dates = DB::table('registers as r')
            ->join('vehicle_sensors as vs', 'r.vehicle_sensor_id', '=', 'vs.id')
            ->select([
                DB::raw('DATE(r.recorded_at) as date'),
                DB::raw('COUNT(DISTINCT vs.vehicle_id) as vehicle_count'),
                DB::raw('COUNT(*) as record_count')
            ])
            ->where('r.recorded_at', '>=', now()->subDays(30))
            ->groupBy(DB::raw('DATE(r.recorded_at)'))
            ->orderBy('date', 'desc')
            ->get();

        $formattedDates = $dates->map(function ($dateRecord) {
            $date = Carbon::parse($dateRecord->date);
            return [
                'date' => $dateRecord->date,
                'formatted_date' => $date->format('d/m/Y'),
                'day_name' => $date->translatedFormat('l'),
                'vehicle_count' => $dateRecord->vehicle_count,
                'record_count' => $dateRecord->record_count,
            ];
        });

        return $formattedDates;
    }

    /**
     * Obtener las horas disponibles para un día específico y un vehículo
     */
    public function getAvailableHours(Request $request)
    {
        $vehicleId = $request->input('vehicle_id');
        $date = $request->input('date');

        if (!$vehicleId || !$date) {
            return response()->json([
                'error' => 'Se requiere vehicle_id y date'
            ], 400);
        }

        // Validar que el vehículo pertenezca al cliente o el usuario sea superadmin
        if (!$request->user()->isSuperAdmin()) {
            $vehicle = Vehicle::whereHas('clientDevice', function ($query) use ($request) {
                $query->where('client_id', $request->user()->client_id);
            })->find($vehicleId);

            if (!$vehicle) {
                return response()->json([
                    'error' => 'No tiene permisos para acceder a este vehículo'
                ], 403);
            }
        }

        try {
            // Parsear la fecha para asegurar un formato válido
            $parsedDate = Carbon::parse($date);
            $dateStart = $parsedDate->startOfDay()->toDateTimeString();
            $dateEnd = $parsedDate->copy()->endOfDay()->toDateTimeString();

            // Obtener horas con datos
            $hours = DB::table('registers as r')
                ->join('vehicle_sensors as vs', 'r.vehicle_sensor_id', '=', 'vs.id')
                ->select([
                    DB::raw('HOUR(r.recorded_at) as hour'),
                    DB::raw('MIN(r.recorded_at) as start_time'),
                    DB::raw('MAX(r.recorded_at) as end_time'),
                    DB::raw('COUNT(*) as record_count')
                ])
                ->where('vs.vehicle_id', $vehicleId)
                ->whereBetween('r.recorded_at', [$dateStart, $dateEnd])
                ->groupBy(DB::raw('HOUR(r.recorded_at)'))
                ->having('record_count', '>', 10) // Solo horas con suficientes datos
                ->orderBy('hour', 'asc')
                ->get();

            $formattedHours = $hours->map(function ($hourData) {
                $startTime = Carbon::parse($hourData->start_time);
                $endTime = Carbon::parse($hourData->end_time);
                $durationMinutes = $startTime->diffInMinutes($endTime);

                return [
                    'hour' => $hourData->hour,
                    'formatted_hour' => sprintf('%02d:00', $hourData->hour),
                    'start_time' => $startTime->toIso8601String(),
                    'end_time' => $endTime->toIso8601String(),
                    'record_count' => $hourData->record_count,
                    'duration_minutes' => $durationMinutes,
                    'formatted_duration' => $this->formatDuration($durationMinutes),
                ];
            });

            return response()->json([
                'vehicle_id' => $vehicleId,
                'date' => $parsedDate->toDateString(),
                'formatted_date' => $parsedDate->format('d/m/Y'),
                'hours' => $formattedHours,
                'hour_count' => $formattedHours->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching available hours', [
                'vehicle_id' => $vehicleId,
                'date' => $date,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Error al obtener horas disponibles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener datos de telemetría para una sesión específica
     */
    public function getReplayData(Request $request, Vehicle $vehicle)
    {
        $startTime = $request->input('from');
        $endTime   = $request->input('to');

        if (!$startTime || !$endTime) {
            return response()->json([
                'error' => 'Se requieren los parámetros from y to'
            ], 400);
        }

        $vehicleId = $vehicle->id;
        $start_time = $request->input('from');
        $end_time = $request->input('to');

        // Validar que el vehículo pertenezca al cliente o el usuario sea superadmin
        if (!$request->user()->isSuperAdmin()) {
            $vehicle = Vehicle::whereHas('clientDevice', function ($query) use ($request) {
                $query->where('client_id', $request->user()->client_id);
            })->find($vehicleId);

            if (!$vehicle) {
                return response()->json([
                    'error' => 'No tienes permisos para acceder a este vehículo'
                ], 403);
            }
        } else {
            // Si es superadmin, validar que el vehículo exista
            $vehicle = Vehicle::find($vehicleId);
            if (!$vehicle) {
                return response()->json([
                    'error' => 'Vehículo no encontrado'
                ], 404);
            }
        }

        try {
            $from = Carbon::parse($startTime);
            $to   = Carbon::parse($endTime);

            if ($from->greaterThanOrEqualTo($to)) {
                return response()->json([
                    'error' => 'El tiempo de inicio debe ser menor al tiempo final'
                ], 400);
            }

            // 1) Obtener todos los registros del vehículo en el rango, con sus sensores
            $registers = Register::query()
                ->forVehicle($vehicleId)
                ->whereBetween('recorded_at', [$from, $to])
                ->with(['sensor', 'originalSensor']) // sensor = VehicleSensor, originalSensor = Sensor
                ->orderBy('recorded_at')
                ->get();

            if ($registers->isEmpty()) {
                return response()->json([
                    'vehicle_id' => $vehicleId,
                    'from' => $from->toIso8601String(),
                    'to' => $to->toIso8601String(),
                    'frames' => [],
                    'gps_path' => [],
                    'dtc_events' => [],
                    'summary' => [
                        'total_frames' => 0,
                        'total_points' => 0,
                        'duration_minutes' => $from->diffInMinutes($to),
                    ],
                ]);
            }

            $frames    = [];
            $gpsFrames = [];

            foreach ($registers as $reg) {
                $vehicleSensor = $reg->sensor;            // VehicleSensor
                $sensor        = $reg->originalSensor;    // Sensor "real"

                if (!$vehicleSensor || !$sensor) {
                    continue;
                }

                $timestamp = $reg->recorded_at instanceof Carbon
                    ? $reg->recorded_at->toIso8601String()
                    : Carbon::parse($reg->recorded_at)->toIso8601String();

                if (!isset($frames[$timestamp])) {
                    $frames[$timestamp] = [
                        'timestamp' => $timestamp,
                        'sensors'   => [],
                    ];
                }

                $processedValue = $this->processSensorValueForReplay(
                    $reg->value,
                    $sensor
                );

                $pid = $sensor->pid;

                $frames[$timestamp]['sensors'][$pid] = [
                    'pid'             => $pid,
                    'name'            => $sensor->name,
                    'unit'            => $sensor->unit,
                    'raw_value'       => $reg->value,
                    'processed_value' => $processedValue,
                ];

                // Construir la info de GPS si aplica
                if (!isset($gpsFrames[$timestamp])) {
                    $gpsFrames[$timestamp] = [
                        'timestamp' => $timestamp,
                        'lat'       => null,
                        'lng'       => null,
                        'speed'     => null,
                        'alt'       => null,
                        'heading'   => null,
                    ];
                }

                switch ($pid) {
                    case 'lat':
                        $gpsFrames[$timestamp]['lat'] = $processedValue;
                        break;
                    case 'lng':
                        $gpsFrames[$timestamp]['lng'] = $processedValue;
                        break;
                    case 'vel_kmh':
                        $gpsFrames[$timestamp]['speed'] = $processedValue;
                        break;
                    case 'alt_m':
                        $gpsFrames[$timestamp]['alt'] = $processedValue;
                        break;
                    case 'rumbo':
                    case 'heading':
                        $gpsFrames[$timestamp]['heading'] = $processedValue;
                        break;
                }
            }

            // Normalizar frames (ordenados por tiempo)
            $framesList = array_values($frames);
            usort($framesList, function ($a, $b) {
                return strcmp($a['timestamp'], $b['timestamp']);
            });

            // Normalizar ruta GPS (solo puntos con lat/lng)
            $gpsPath = array_values(array_filter($gpsFrames, function ($p) {
                return !is_null($p['lat']) && !is_null($p['lng']);
            }));
            usort($gpsPath, function ($a, $b) {
                return strcmp($a['timestamp'], $b['timestamp']);
            });

            // 2) DTC detectados en ese rango
            $dtcEvents = DiagnosticTroubleCode::where('vehicle_id', $vehicleId)
                ->whereBetween('detected_at', [$from, $to])
                ->orderBy('detected_at')
                ->get()
                ->map(function ($dtc) {
                    return [
                        'code'        => $dtc->code,
                        'description' => $dtc->description,
                        'detected_at' => $dtc->detected_at
                            ? $dtc->detected_at->toIso8601String()
                            : null,
                        'is_active'   => $dtc->is_active,
                    ];
                })->values();

            $durationMinutes = $from->diffInMinutes($to);

            return response()->json([
                'vehicle_id' => $vehicleId,
                'from'       => $from->toIso8601String(),
                'to'         => $to->toIso8601String(),
                'frames'     => $framesList,
                'gps_path'   => $gpsPath,
                'dtc_events' => $dtcEvents,
                'summary'    => [
                    'total_frames'  => count($framesList),
                    'total_points'  => $registers->count(),
                    'duration_minutes'   => $durationMinutes,
                    'formatted_duration' => $this->formatDuration($durationMinutes),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener datos de replay: ' . $e->getMessage(), [
                'vehicle_id' => $vehicleId,
                'start_time' => $startTime,
                'end_time'   => $endTime,
            ]);

            return response()->json([
                'error' => 'Error interno al obtener datos de replay',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Procesar un grupo de lecturas para un mismo punto en el tiempo
     */
    private function processReadingGroup($readings)
    {
        $processedReadings = [];

        foreach ($readings as $reading) {
            $value = $reading->raw_value;

            // Aplicar fórmula de cálculo si es necesario
            if ($reading->requires_calculation && $reading->calculation_formula) {
                try {
                    $formula = $reading->calculation_formula;
                    $A = (float) $reading->raw_value;
                    $B = 0; // Para datos de 2 bytes, implementar después

                    // Reemplazar variables en la fórmula de manera segura
                    $calculatedFormula = str_replace(['A', 'B'], [$A, $B], $formula);

                    // Evaluar de manera segura
                    $result = eval("return $calculatedFormula;");
                    $value = round((float) $result, 2);
                } catch (\Exception $e) {
                    Log::error('Error calculating sensor value', [
                        'raw_value' => $reading->raw_value,
                        'formula' => $reading->calculation_formula,
                        'error' => $e->getMessage()
                    ]);
                    $value = (float) $reading->raw_value;
                }
            }

            $processedReadings[$reading->pid] = $value;
        }

        return $processedReadings;
    }
    /**
     * Replica de la lógica de cálculo de RegisterVehiculeController,
     * pero local a este controlador.
     */
    private function processSensorValueForReplay($rawValue, $sensor)
    {
        if (!$sensor || !$sensor->requires_calculation || !$sensor->calculation_formula) {
            return $rawValue;
        }

        try {
            $formula = $sensor->calculation_formula;
            $A = $rawValue;
            $B = 0; // Para datos de 2 bytes, si después lo manejas

            $calculatedFormula = str_replace(['A', 'B'], [$A, $B], $formula);

            $result = eval("return $calculatedFormula;");

            return round($result, 2);
        } catch (\Throwable $e) {
            Log::error('Error calculando valor de sensor en replay: ' . $e->getMessage(), [
                'sensor_id' => $sensor->id ?? null,
            ]);
            return $rawValue;
        }
    }

    /**
     * Formatear duración en minutos a formato legible
     */
    private function formatDuration($minutes)
    {
        if ($minutes < 1) {
            $seconds = round($minutes * 60);
            return "{$seconds}s";
        } elseif ($minutes < 60) {
            return round($minutes) . 'min';
        } else {
            $hours = floor($minutes / 60);
            $remainingMinutes = round($minutes % 60);
            return $remainingMinutes > 0 ? "{$hours}h {$remainingMinutes}min" : "{$hours}h";
        }
    }

    /**
     * Página de visualización del replay
     */
    public function showReplay(Request $request)
    {
        $vehicleId = $request->input('vehicle_id');
        $startTime = $request->input('start_time');
        $endTime = $request->input('end_time');

        if (!$vehicleId || !$startTime || !$endTime) {
            return redirect()->route('replay.index')->with('error', 'Parámetros insuficientes para iniciar replay');
        }

        // Validar que el vehículo pertenezca al cliente o el usuario sea superadmin
        if (!$request->user()->isSuperAdmin()) {
            $vehicle = Vehicle::whereHas('clientDevice', function ($query) use ($request) {
                $query->where('client_id', $request->user()->client_id);
            })->find($vehicleId);

            if (!$vehicle) {
                return redirect()->route('replay.index')->with('error', 'No tiene permisos para acceder a este vehículo');
            }
        }

        // Cargar el vehículo para la vista
        $vehicle = Vehicle::with(['vehicleSensors.sensor', 'clientDevice', 'clientDevice.DeviceInventory'])
            ->findOrFail($vehicleId);

        $startTimeObj = Carbon::parse($startTime);
        $endTimeObj = Carbon::parse($endTime);
        $durationMinutes = $startTimeObj->diffInMinutes($endTimeObj);

        return Inertia::render('Replay/Player', [
            'vehicle' => $vehicle,
            'replayParams' => [
                'vehicle_id' => $vehicleId,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'formatted_start' => $startTimeObj->format('d/m/Y H:i:s'),
                'formatted_end' => $endTimeObj->format('d/m/Y H:i:s'),
                'duration_minutes' => $durationMinutes,
                'formatted_duration' => $this->formatDuration($durationMinutes),
            ],
        ]);
    }
}
