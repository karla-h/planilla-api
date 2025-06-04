<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeRequest extends FormRequest
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
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'dni' => 'required|string|max:20',
            'born_date'=> 'nullable|date',
            'email' => 'required|string|email|max:255',
            'account' => 'required|string',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'headquarter' => 'required',
            'affiliations'=> 'nullable|array',
            'contract' => 'required',
        ];
    }
}
