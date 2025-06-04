<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContractRequest extends FormRequest
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
            'hire_date' => 'nullable|date',
            'termination_date' => 'nullable|date|after_or_equal:hire_date',
            'termination_reason' => 'nullable|string|max:255',
            'accounting_salary' => 'required|numeric|min:0|max:999999.99',
            'real_salary' => 'required|numeric|min:0|max:999999.99',
            'employee_id' => 'required|exists:employees,id',
        ];
    }
}
