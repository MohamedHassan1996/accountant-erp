<?php

namespace App\Http\Resources\Client\PayInstallment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllPayInstallmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'payInstallmentId' => $this->id,
            'startAt' => $this->start_at,
            'endAt' => $this->end_at,
            'amount' => $this->amount,
            'parameterValueName' => $this->parameterValue?->parameter_value??'',
            'payInstallmentSubData' => count($this->payInstallmentSubData) ? AllPayInstallmentSubDataResource::collection($this->payInstallmentSubData) : []
        ];

    }
}
