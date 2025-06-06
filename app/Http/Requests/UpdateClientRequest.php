<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Simplificado - permitir a todos los usuarios autenticados
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $clientId = $this->route('client')->id;

        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('clients', 'email')->ignore($clientId)
            ],
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'company' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'El nombre es obligatorio.',
            'first_name.string' => 'El nombre debe ser una cadena de texto.',
            'first_name.max' => 'El nombre no puede tener más de 255 caracteres.',
            
            'last_name.required' => 'El apellido es obligatorio.',
            'last_name.string' => 'El apellido debe ser una cadena de texto.',
            'last_name.max' => 'El apellido no puede tener más de 255 caracteres.',
            
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico debe ser una dirección válida.',
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'email.max' => 'El correo electrónico no puede tener más de 255 caracteres.',
            
            'phone.string' => 'El teléfono debe ser una cadena de texto.',
            'phone.max' => 'El teléfono no puede tener más de 20 caracteres.',
            
            'address.string' => 'La dirección debe ser una cadena de texto.',
            'address.max' => 'La dirección no puede tener más de 500 caracteres.',
            
            'city.string' => 'La ciudad debe ser una cadena de texto.',
            'city.max' => 'La ciudad no puede tener más de 100 caracteres.',
            
            'state.string' => 'El estado debe ser una cadena de texto.',
            'state.max' => 'El estado no puede tener más de 100 caracteres.',
            
            'zip_code.string' => 'El código postal debe ser una cadena de texto.',
            'zip_code.max' => 'El código postal no puede tener más de 20 caracteres.',
            
            'country.string' => 'El país debe ser una cadena de texto.',
            'country.max' => 'El país no puede tener más de 100 caracteres.',
            
            'company.string' => 'La empresa debe ser una cadena de texto.',
            'company.max' => 'La empresa no puede tener más de 255 caracteres.',
            
            'job_title.string' => 'El cargo debe ser una cadena de texto.',
            'job_title.max' => 'El cargo no puede tener más de 255 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'nombre',
            'last_name' => 'apellido',
            'email' => 'correo electrónico',
            'phone' => 'teléfono',
            'address' => 'dirección',
            'city' => 'ciudad',
            'state' => 'estado',
            'zip_code' => 'código postal',
            'country' => 'país',
            'company' => 'empresa',
            'job_title' => 'cargo',
        ];
    }
}