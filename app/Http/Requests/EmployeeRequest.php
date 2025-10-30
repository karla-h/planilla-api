<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'dni' => 'required|string|size:8|unique:employees,dni',
            'born_date' => 'required|date',
            'email' => 'nullable|email|unique:employees,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'account' => 'nullable|string|max:255',
            'headquarter_id' => 'required|exists:headquarters,id'
        ];

        // Para actualización
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['dni'] = 'required|string|size:8|unique:employees,dni,' . $this->route('employee');
            $rules['email'] = 'nullable|email|unique:employees,email,' . $this->route('employee');
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'firstname.required' => 'El nombre es obligatorio',
            'lastname.required' => 'El apellido es obligatorio',
            'dni.required' => 'El DNI es obligatorio',
            'dni.size' => 'El DNI debe tener 8 dígitos',
            'dni.unique' => 'El DNI ya está registrado',
            'born_date.required' => 'La fecha de nacimiento es obligatoria',
            'email.email' => 'El email debe ser válido',
            'email.unique' => 'El email ya está registrado',
            'headquarter_id.required' => 'La sede es obligatoria',
            'headquarter_id.exists' => 'La sede seleccionada no existe'
        ];
    }
}