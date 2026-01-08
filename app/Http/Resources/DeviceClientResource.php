<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceClientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            "device_name" => $this->device_name,
            "mac_address" => $this->mac_address,
            "status" => $this->status,
            "activated_at" => $this->activated_at,
            "last_ping" => $this->last_ping,
            "device_config" => $this->device_config,
            "client_name" => $this->whenLoaded('client', function () {
                return $this->client ? $this->client->first_name . " " . $this->client->last_name : null;
            }),
            "client" => $this->whenLoaded('client', function () {
                return $this->client ? [
                    'id' => $this->client->id,
                    'full_name' => $this->client->first_name . ' ' . $this->client->last_name,
                    'company' => $this->client->company,
                ] : null;
            }),
            "device_inventory" => $this->whenLoaded('DeviceInventory', function () {
                return $this->DeviceInventory ? $this->DeviceInventory->serial_number . ' - ' . $this->DeviceInventory->model : null;
            }),
            "active_vehicle" => $this->whenLoaded('vehicles', function () {
                $activeVehicle = $this->vehicles->where('status', true)->first();
                return $activeVehicle ? [
                    'id' => $activeVehicle->id,
                    'vin' => $activeVehicle->vin,
                    'make' => $activeVehicle->make,
                    'model' => $activeVehicle->model,
                    'year' => $activeVehicle->year,
                    'nickname' => $activeVehicle->nickname,
                    'license_plate' => $activeVehicle->license_plate,
                ] : null;
            }),
        ];
    }
}
