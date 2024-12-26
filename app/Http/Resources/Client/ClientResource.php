<?php

namespace App\Http\Resources\Client;

use App\Http\Resources\Client\ClientAddress\AllClientAddressResource;
use App\Http\Resources\Client\ClientContact\AllClientContactResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'clientId' => $this->id,
            'ragioneSociale' => $this->ragione_sociale??"",
            'iva' => $this->iva??"",
            'cf' => $this->cf??"",
            'note' => $this->note??"",
            'phone' => $this->phone??"",
            'email' => $this->email??"",
            'price' => $this->price??0,
            'hoursPerMonth' => $this->hours_per_month??0,
            'addresses' => AllClientAddressResource::collection($this->whenLoaded('addresses')),
            'contacts' => AllClientContactResource::collection($this->whenLoaded('contacts')),
        ];
    }
}
