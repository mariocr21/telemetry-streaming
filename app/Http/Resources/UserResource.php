<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Obtener el label del rol
        $roleLabel = match($this->role) {
            'SA' => 'Super Administrador',
            'CA' => 'Administrador de Cliente',
            'CU' => 'Usuario de Cliente',
            default => 'Usuario de Cliente'
        };

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role ?? 'CU',
            'role_label' => $roleLabel,
            'is_active' => $this->is_active ?? true,
            'client_id' => $this->client_id,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Relación con cliente si está cargada
            'client' => $this->whenLoaded('client', function () {
                return $this->client ? [
                    'id' => $this->client->id,
                    'full_name' => $this->client->full_name,
                    'email' => $this->client->email,
                ] : null;
            }),
        ];
    }
}