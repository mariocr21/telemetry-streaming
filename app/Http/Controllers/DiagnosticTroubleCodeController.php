<?php

namespace App\Http\Controllers;

use App\Models\DiagnosticTroubleCode;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DiagnosticTroubleCodeController extends Controller
{
    /**
     * Obtener todos los códigos DTC para un vehículo específico
     */
    public function getByVehicle($vehicleId)
    {
        $vehicle = Vehicle::findOrFail($vehicleId);
        
        $dtcCodes = DiagnosticTroubleCode::where('vehicle_id', $vehicleId)
            ->orderBy('is_active', 'desc')
            ->orderBy('detected_at', 'desc')
            ->get()
            ->map(function($dtc) {
                return [
                    'id' => $dtc->id,
                    'code' => $dtc->code,
                    'description' => $dtc->description ?? $this->getDTCDescription($dtc->code),
                    'severity' => $this->getDTCSeverity($dtc->code),
                    'detected_at' => $dtc->detected_at,
                    'redetected_at' => $dtc->redetected_at,
                    'resolved_at' => $dtc->resolved_at,
                    'is_active' => $dtc->is_active,
                ];
            });
        
        return response()->json([
            'vehicle_id' => $vehicleId,
            'vehicle_vin' => $vehicle->vin,
            'dtc_codes' => $dtcCodes,
            'active_count' => $dtcCodes->where('is_active', true)->count(),
            'total_count' => $dtcCodes->count(),
        ]);
    }
    
    /**
     * Marcar un código DTC como resuelto
     */
    public function markAsResolved(Request $request, $id)
    {
        $dtc = DiagnosticTroubleCode::findOrFail($id);
        
        $dtc->update([
            'is_active' => false,
            'resolved_at' => now(),
        ]);
        
        // Actualizar caché
        $this->updateDTCCache($dtc->vehicle_id);
        
        return response()->json([
            'status' => 'success',
            'message' => "DTC {$dtc->code} marked as resolved",
            'dtc' => $dtc
        ]);
    }
    
    /**
     * Actualizar la caché de DTCs para un vehículo
     */
    private function updateDTCCache($vehicleId)
    {
        $activeDtcCodes = DiagnosticTroubleCode::where('vehicle_id', $vehicleId)
            ->where('is_active', true)
            ->get()
            ->map(function($dtc) {
                return [
                    'code' => $dtc->code,
                    'description' => $dtc->description ?? $this->getDTCDescription($dtc->code),
                    'severity' => $this->getDTCSeverity($dtc->code),
                    'detected_at' => $dtc->detected_at,
                ];
            })
            ->toArray();
            
        Cache::put(
            "vehicle_dtc_{$vehicleId}",
            $activeDtcCodes,
            300 // 5 minutos
        );
        
        return $activeDtcCodes;
    }
    
    /**
     * Método para obtener descripción de código DTC
     * Duplicado del RegisterVehiculeController para mantener la consistencia
     */
    private function getDTCDescription($code)
    {
        $prefixes = [
            'P0' => 'Powertrain Issue',
            'P1' => 'Manufacturer-Specific Powertrain Issue',
            'P2' => 'Powertrain Issue (Fuel/Air Monitoring)',
            'P3' => 'Powertrain Issue (Ignition)',
            'B0' => 'Body Issue',
            'C0' => 'Chassis Issue',
            'U0' => 'Network Issue',
        ];
        
        $prefix = substr($code, 0, 2);
        
        return $prefixes[$prefix] ?? 'Unknown Issue';
    }
    
    /**
     * Método para determinar la severidad del código DTC
     * Duplicado del RegisterVehiculeController para mantener la consistencia
     */
    private function getDTCSeverity($code)
    {
        $prefix = substr($code, 0, 1);
        
        switch ($prefix) {
            case 'P':
                return 'high';
            case 'B':
                return 'medium';
            case 'C':
                return 'medium';
            case 'U':
                return 'low';
            default:
                return 'unknown';
        }
    }
}