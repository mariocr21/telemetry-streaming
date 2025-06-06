<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class VehicleTelemetryEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $vehicleId;
    public $deviceId;
    public $telemetryData;
    public $timestamp;

    public function __construct($vehicleId, $deviceId, $telemetryData)
    {
        $this->vehicleId = $vehicleId;
        $this->deviceId = $deviceId;
        $this->telemetryData = $telemetryData;
        $this->timestamp = now()->toISOString();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        Log::info('Broadcasting telemetry data', [
            'vehicle_id' => $this->vehicleId,
            'device_id' => $this->deviceId,
            'sensors_count' => count($this->telemetryData)
        ]);

        return [
            new Channel('telemetry'),
            new PrivateChannel('vehicle.' . $this->vehicleId),
            new PrivateChannel('device.' . $this->deviceId),
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
            'data' => $this->telemetryData
        ];
    }

    /**
     * Determine if this event should broadcast.
     */
    public function broadcastWhen(): bool
    {
        return !empty($this->telemetryData);
    }
}