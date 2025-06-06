<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceInventoryRequest extends FormRequest
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
            'serial_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('device_inventories', 'serial_number')->ignore($this->route('deviceInventory')->id)
            ],
            'device_uuid' => [
                'required',
                'string',
                'max:255',
                Rule::unique('device_inventories', 'device_uuid')->ignore($this->route('deviceInventory')->id)
            ],
            'model' => 'required|string|max:255',
            'hardware_version' => 'nullable|string|max:255',
            'firmware_version' => 'nullable|string|max:255',
            'status' => 'required|in:available,sold,maintenance,retired',
            'manufactured_date' => 'nullable|date|before_or_equal:today',
            'sold_date' => 'nullable|date|after_or_equal:manufactured_date',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'serial_number.required' => 'El número de serie es obligatorio.',
            'serial_number.unique' => 'Este número de serie ya existe en el inventario.',
            'device_uuid.required' => 'El UUID del dispositivo es obligatorio.',
            'device_uuid.unique' => 'Este UUID ya existe en el inventario.',
            'model.required' => 'El modelo del dispositivo es obligatorio.',
            'hardware_version.max' => 'La versión de hardware no puede exceder 255 caracteres.',
            'firmware_version.max' => 'La versión de firmware no puede exceder 255 caracteres.',
            'status.required' => 'El estado del dispositivo es obligatorio.',
            'status.in' => 'El estado seleccionado no es válido.',
            'manufactured_date.date' => 'La fecha de fabricación debe ser una fecha válida.',
            'manufactured_date.before_or_equal' => 'La fecha de fabricación no puede ser futura.',
            'sold_date.date' => 'La fecha de venta debe ser una fecha válida.',
            'sold_date.after_or_equal' => 'La fecha de venta debe ser posterior a la fecha de fabricación.',
            'notes.max' => 'Las notas no pueden exceder 1000 caracteres.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir a mayúsculas y limpiar espacios
        $this->merge([
            'serial_number' => strtoupper(trim($this->serial_number ?? '')),
            'device_uuid' => strtoupper(trim($this->device_uuid ?? '')),
            'model' => trim($this->model ?? ''),
        ]);
    }
}