<?php

namespace App\Http\Requests\ServiceCategory;

use App\Enums\ServiceCategory\ServiceCategoryAddToInvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rules\Enum;


class CreateServiceCategoryRequest extends FormRequest
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
            'name' => ['required', 'unique:service_categories,name'],
            'description' => 'nullable|string',
            'addToInvoice' => ['required', new Enum(ServiceCategoryAddToInvoiceStatus::class)],
            'price' => 'required',
            'serviceTypeId'=>'nullable',
            'extraIsPricable'=>'nullable',
            'extraCode'=>'nullable',
            'extraPriceDescription'=>'nullable',
            'extraPrice'=>'nullable'
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()
        ], 401));
    }

}
