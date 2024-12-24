<?php

namespace App\Http\Resources\Client\ClientContact;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllClientContactResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return [
            'clientContactId' => $this->id,
            'firstName' => $this->firstName??"",
            'lastName' => $this->lastName??"",
            'phone' => $this->phone??"",
            'prefix' => $this->prefix??"",
            'email' => $this->email??"",
            'note' => $this->note??"",
            'parameterValueName' => $this->parameter?->parameter_value??"",
        ];
    }
}
