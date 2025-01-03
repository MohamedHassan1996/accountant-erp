<?php

namespace App\Http\Requests\Client\ClinetServiceDiscount;

use App\Enums\Client\ClientServiceDiscountStatus;
use App\Enums\Client\ClientServiceDiscountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rules\Enum;

class UpdateClientServiceDiscountRequest extends FormRequest
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
            'clientServiceDiscountId' => ['required'],
            'serviceCategoryId' => ['required'],
            'discount' => ['required'],
            'type' => ['required', new Enum(ClientServiceDiscountType::class)],
            'isActive' => ['required', new Enum(ClientServiceDiscountStatus::class)],
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()
        ], 401));
    }

}
