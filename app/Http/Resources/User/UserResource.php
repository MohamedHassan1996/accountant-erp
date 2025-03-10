<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;


class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'userId' => $this->id,
            'firstName' => $this->first_name??"",
            'lastName' => $this->last_name??"",
            'username' => $this->username??"",
            'phone' => $this->phone?$this->phone:"",
            'address' => $this->address?$this->address:"",
            'status' => $this->status,
            'avatar' => $this->avatar?Storage::disk('public')->url($this->avatar):"",
            'perHourRate' => $this->per_hour_rate??0,
            'email' => $this->email??''
        ];
    }
}
