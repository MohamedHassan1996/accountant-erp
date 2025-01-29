<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllClientResource extends JsonResource
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
            'payment_type_id' => $this->payment_type_id??"" ,
            'pay_steps_id'=> $this->pay_steps_id??"",
            'payment_type_two_id'=> $this->payment_type_two_id??"",
            'addable_to_bulck_invoice'=>$this->addable_to_bulck_invoice,
            'allowed_days_to_pay'=>$this->allowed_days_to_pay??"",
            'iban' => $this->iban??"",
            'abi'=> $this->abi??"",
            'cab' => $this->cab??""
        ];
    }
}
