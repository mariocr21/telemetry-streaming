<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
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
            // NOTA: serial_number y device_uuid NO se incluyen porque no son editables
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
            'model.required' => 'El modelo del dispositivo es obligatorio.',
            'model.max' => 'El modelo no puede exceder 255 caracteres.',
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
        // Solo limpiar los campos que SÍ son editables
        $data = [];
        
        if ($this->has('model')) {
            $data['model'] = trim($this->model);
        }
        
        if ($this->has('hardware_version')) {
            $data['hardware_version'] = trim($this->hardware_version);
        }
        
        if ($this->has('firmware_version')) {
            $data['firmware_version'] = trim($this->firmware_version);
        }
        
        if ($this->has('notes')) {
            $data['notes'] = trim($this->notes);
        }
        
        $this->merge($data);
    }
}