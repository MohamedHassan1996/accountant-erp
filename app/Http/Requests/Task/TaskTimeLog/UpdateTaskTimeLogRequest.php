<?php

namespace App\Http\Requests\Task\TaskTimeLog;

use App\Enums\Task\TaskTimeLogType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rules\Enum;

class UpdateTaskTimeLogRequest extends FormRequest
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
            'taskTimeLogId' => 'required',
            'startAt' => 'required',
            'endAt' => 'nullable',
            'type' => ['required', new Enum(TaskTimeLogType::class)],
            'comment' => ['nullable'],
            'taskId' => 'required',
            'parentTimeLogId' => 'nullable',
            'userId' => 'required',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()
        ], 401));
    }

}
