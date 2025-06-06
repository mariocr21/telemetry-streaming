<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientDeviceRequest extends FormRequest
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
            'device_name' => 'required|string|max:255',
            'mac_address' => [
                'nullable',
                'string',
                'max:17',
                Rule::unique('client_devices', 'mac_address')->ignore($this->route('device')->id)
            ],
            'status' => 'required|in:pending,active,inactive,maintenance,retired',
            'device_config' => 'nullable|json',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'device_name.required' => 'El nombre del dispositivo es obligatorio.',
            'device_name.max' => 'El nombre del dispositivo no puede exceder 255 caracteres.',
            'mac_address.required' => 'La dirección MAC es obligatoria.',
            'mac_address.unique' => 'Esta dirección MAC ya está registrada en otro dispositivo.',
            'mac_address.max' => 'La dirección MAC no puede exceder 17 caracteres.',
            'status.required' => 'El estado del dispositivo es obligatorio.',
            'status.in' => 'El estado del dispositivo no es válido.',
            'device_config.json' => 'La configuración del dispositivo debe ser un JSON válido.',
        ];
    }
}