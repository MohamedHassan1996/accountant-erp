<?php

namespace App\Http\Requests\Client;

use App\Enums\Client\AddableToBulk;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateClientRequest extends FormRequest
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
            'clientId' => ['required'],
            'iva' => ['nullable'],
            'ragioneSociale' => ['required'],
            'cf' => ['nullable'],
            'note' => ['nullable'],
            'phone' => ['nullable'],
            'email' => ['nullable'],
            'hoursPerMonth' => ['nullable'],
            'price' => ['nullable'],
            'addableToBulkInvoice'=>['nullable',new Enum(AddableToBulk::class) ],
            'allowedDaysToPay'=>['nullable'],
            'paymentTypeId' => ['nullable'] ,
            'payStepsId' => ['nullable'],
            'paymentTypeTwoId' => ['nullable'],
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
