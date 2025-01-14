<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;


class CreateClientRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'iva' => ['nullable'],
            'ragioneSociale' => ['required'],
            'cf' => ['nullable'],
            'note' => ['nullable'],
            'phone' => ['nullable'],
            'email' => ['nullable'],
            'hoursPerMonth' => ['nullable'],
            'price' => ['nullable'],
            'addresses' => ['nullable'],
            'contacts' => ['nullable'],
            'payment_type_id' => ['nullable'|'exists:parameter_values,id'] ,
            'pay_steps_id' => ['nullable'|'exists:parameter_values,id'],
            'payment_type_two_id' => ['nullable'|'exists:parameter_values,id'],
            'iban' => ['nullable'],
            'abi' => ['nullable'],
            'cab' => ['nullable']
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()
        ], 401));
    }

}
