<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'country' => $this->country,
            'company' => $this->company,
            'job_title' => $this->job_title,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Relaciones condicionales
            'users' => UserResource::collection($this->whenLoaded('users')),
            'users_count' => $this->when(isset($this->users_count), $this->users_count),
            
            // Permisos simplificados - todos true por ahora
            'can' => [
                'view' => true,
                'update' => true,
                'delete' => true,
            ],
        ];
    }
}