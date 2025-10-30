<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AffiliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'description' => 'required|string|max:255',
            'percent' => 'required|numeric|min:0|max:100'
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['description'] = 'sometimes|string|max:255';
            $rules['percent'] = 'sometimes|numeric|min:0|max:100';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'description.required' => 'La descripción es obligatoria',
            'percent.required' => 'El porcentaje es obligatorio',
            'percent.numeric' => 'El porcentaje debe ser un número',
            'percent.min' => 'El porcentaje no puede ser negativo',
            'percent.max' => 'El porcentaje no puede ser mayor a 100'
        ];
    }
}