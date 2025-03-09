<?php

namespace App\Http\Resources\Client\PayInstallment;

use App\Http\Resources\Client\PayInstallment\PayInstallmentSubDataResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayInstallmentResource extends JsonResource
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
            'description' => $this->description??'',
            'payInstallmentSubDatas' => PayInstallmentSubDataResource::collection($this->payInstallmentSubData)
        ];

    }
}
