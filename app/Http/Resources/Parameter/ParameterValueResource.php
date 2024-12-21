<?php

namespace App\Http\Resources\Parameter;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParameterValueResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'parameterValueGuid' => $this->guid,
            'parameterValueName' => $this->parameter_value,
            'parameterValueDescription' => $this->description??""
        ];
    }
}
