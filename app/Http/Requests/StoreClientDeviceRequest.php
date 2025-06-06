<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientDeviceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_inventory_id' => 'required|exists:device_inventories,id',
            'device_name' => 'required|string|max:255',
            'mac_address' => 'nullable|string|max:17|unique:client_devices,mac_address',
            'device_config' => 'nullable|json',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'device_inventory_id.required' => 'Debe seleccionar un dispositivo del inventario.',
            'device_inventory_id.exists' => 'El dispositivo seleccionado no existe.',
            'device_name.required' => 'El nombre del dispositivo es obligatorio.',
            'device_name.max' => 'El nombre del dispositivo no puede exceder 255 caracteres.',
            'mac_address.required' => 'La dirección MAC es obligatoria.',
            'mac_address.unique' => 'Esta dirección MAC ya está registrada en otro dispositivo.',
            'mac_address.max' => 'La dirección MAC no puede exceder 17 caracteres.',
            'device_config.json' => 'La configuración del dispositivo debe ser un JSON válido.',
        ];
    }
}