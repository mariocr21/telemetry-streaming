<?php

namespace App\Http\Controllers;

use App\Models\DiagnosticTroubleCode;
use App\Services\TelemetryIngestService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RegisterVehiculeController extends Controller
{
    public function __construct(
        protected TelemetryIngestService $telemetryIngestService
    ) {}

    public function store(Request $request)
    {
        $startTime = microtime(true);

        // Sigue usando tu mapeo de formato nuevo → viejo
        $requestData = $this->normalizeRequestData($request);

        try {
            $data = $request->merge($requestData)->validate([
                'id'  => 'required|exists:device_inventories,serial_number',
                'idc' => 'required|exists:vehicles,vin',
                'dt'  => 'required|string',
                's'   => 'required|array',
                'DTC' => 'nullable|array',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('📥 Telemetry rejected - Validation failed', [
                'device' => $request->input('id') ?? $request->input('device_id'),
                'errors' => $e->errors(),
            ]);

            return response()->json(['status' => 'error', 'errors' => $e->errors()], 422);
        }

        try {
            // Ahora delegas todo al servicio
            $this->telemetryIngestService->ingest($data);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('📥 Telemetry processed via HTTP', [
                'device'        => $data['id'],
                'vehicle'       => $data['idc'],
                'execution_ms'  => $executionTime,
            ]);

            return response()->json(['status' => 'success']);
        } catch (Exception $e) {
            Log::error('❌ Telemetry failed via HTTP', [
                'device'  => $data['id'] ?? 'unknown',
                'vehicle' => $data['idc'] ?? 'unknown',
                'error'   => $e->getMessage(),
                'file'    => basename($e->getFile()) . ':' . $e->getLine(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Internal server error'], 500);
        }
    }

    /**
     * Este helper sí se queda aquí porque mapea los campos de HTTP
     * al formato interno (id, idc, dt, s, DTC).
     */
    private function normalizeRequestData(Request $request): array
    {
        $data = $request->all();

        if (isset($data['device_id']) && isset($data['vehicle_vin'])) {
            return [
                'id'   => $data['device_id'],
                'idc'  => $data['vehicle_vin'],
                'dt'   => $data['timestamp'],
                's'    => $data['sensors'] ?? [],
                'DTC'  => $data['dtc_codes'] ?? [],
            ];
        }

        return $data;
    }


    /**
     * Obtiene la última telemetría desde cache
     */
    public function getLatestTelemetry(int $vehicleId)
    {
        Log::info('📊 Telemetry requested', ['vehicle_id' => $vehicleId]);

        return response()->json([
            'vehicle_id' => $vehicleId,
            'timestamp' => now()->toISOString(),
            'data' => Cache::get("vehicle_telemetry_{$vehicleId}", []),
            'dtc_codes' => Cache::get("vehicle_dtc_{$vehicleId}", []),
        ]);
    }

    /**
     * Obtiene los códigos DTC activos
     */
    public function getActiveDTC(int $vehicleId)
    {
        $dtcCodes = Cache::get("vehicle_dtc_{$vehicleId}");

        if (empty($dtcCodes)) {
            $dtcCodes = DiagnosticTroubleCode::where('vehicle_id', $vehicleId)
                ->where('is_active', true)
                ->get()
                ->map(fn($dtc) => [
                    'code' => $dtc->code,
                    'description' => $this->telemetryIngestService->getDTCDescription($dtc->code),
                    'severity' => $this->telemetryIngestService->getDTCSeverity($dtc->code),
                    'detected_at' => $dtc->detected_at,
                ])
                ->toArray();
        }

        Log::info('🔧 DTC requested', [
            'vehicle_id' => $vehicleId,
            'active_codes' => count($dtcCodes),
        ]);

        return response()->json([
            'vehicle_id' => $vehicleId,
            'timestamp' => now()->toISOString(),
            'dtc_codes' => $dtcCodes,
        ]);
    }
}
