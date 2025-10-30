<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'hire_date' => 'required|date',
            'termination_date' => 'nullable|date|after:hire_date',
            'termination_reason' => 'nullable|string|max:255',
            'accounting_salary' => 'required|numeric|min:0',
            'real_salary' => 'required|numeric|min:0',
            'payment_type' => 'required|in:quincenal,mensual',
            'status_code' => 'required|in:active,terminated,suspended',
            'employee_id' => 'required|exists:employees,id'
        ];

        // Para actualización, hacer algunos campos opcionales
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['hire_date'] = 'sometimes|date';
            $rules['accounting_salary'] = 'sometimes|numeric|min:0';
            $rules['real_salary'] = 'sometimes|numeric|min:0';
            $rules['payment_type'] = 'sometimes|in:quincenal,mensual';
            $rules['status_code'] = 'sometimes|in:active,terminated,suspended';
            $rules['employee_id'] = 'sometimes|exists:employees,id';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'hire_date.required' => 'La fecha de contratación es obligatoria',
            'accounting_salary.required' => 'El salario contable es obligatorio',
            'real_salary.required' => 'El salario real es obligatorio',
            'payment_type.required' => 'El tipo de pago es obligatorio',
            'payment_type.in' => 'El tipo de pago debe ser quincenal o mensual',
            'status_code.required' => 'El estado del contrato es obligatorio',
            'status_code.in' => 'El estado debe ser active, terminated o suspended',
            'employee_id.required' => 'El empleado es obligatorio',
            'employee_id.exists' => 'El empleado seleccionado no existe',
            'termination_date.after' => 'La fecha de terminación debe ser posterior a la fecha de contratación'
        ];
    }
}