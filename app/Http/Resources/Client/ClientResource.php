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
            'payment_type_id' => $this->payment_type_id??"" ,
            'pay_steps_id'=> $this->pay_steps_id??"",
            'payment_type_two_id'=> $this->payment_type_two_id??"",
            'addableToBulkInvoice'=>$this->addable_to_bulk_invoice,
            'allowedDaysToPay'=>$this->allowed_days_to_pay??"",
            'iban' => $this->iban??"",
            'abi'=> $this->abi??"",
            'cab' => $this->cab??"",
            'addresses' => AllClientAddressResource::collection($this->whenLoaded('addresses')),
            'contacts' => AllClientContactResource::collection($this->whenLoaded('contacts')),

        ];
    }
}
