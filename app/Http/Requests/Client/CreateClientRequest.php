<?php

namespace App\Http\Requests\Client;

use App\Enums\Client\AddableToBulck;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;


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
            'payment_type_id' => ['nullable'] ,
            'pay_steps_id' => ['nullable'],
            'payment_type_two_id' => ['nullable'],
            'AddableToBulckInvoice'=>['nullable',new Enum(AddableToBulck::class) ],
            'AllowedDaysToPay'=>['nullable'],
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
