<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class VehicleTelemetryEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $vehicleId;
    public $deviceId;
    public $telemetryData;
    public $timestamp;
    public $dtcCodes; // Nuevo campo para códigos DTC

    public function __construct($vehicleId, $deviceId, $telemetryData, $dtcCodes = [])
    {
        $this->vehicleId = $vehicleId;
        $this->deviceId = $deviceId;
        $this->telemetryData = $telemetryData;
        $this->timestamp = now()->toISOString();
        $this->dtcCodes = $dtcCodes; // Inicializar campo DTC
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        // Log::info('Broadcasting telemetry data', [
        //     'vehicle_id' => $this->vehicleId,
        //     'device_id' => $this->deviceId,
        //     'sensors_count' => count($this->telemetryData),
        //     'dtc_codes_count' => count($this->dtcCodes),
        // ]);

        return [
            new Channel('telemetry'),
            new PrivateChannel('vehicle.' . $this->vehicleId),
            new PrivateChannel('device.' . $this->deviceId),
            new Channel('dtc'), // Canal para alertas de códigos de error
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'telemetry.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'vehicle_id' => $this->vehicleId,
            'device_id' => $this->deviceId,
            'timestamp' => $this->timestamp,
            'data' => $this->telemetryData,
            'dtc_codes' => $this->dtcCodes,
            'has_dtc' => count($this->dtcCodes) > 0,
        ];
    }

    /**
     * Determine if this event should broadcast.
     */
    public function broadcastWhen(): bool
    {
        return !empty($this->telemetryData) || !empty($this->dtcCodes);
    }
}