<?php

namespace App\Http\Resources\ServiceCategory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;


class ServiceCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'serviceCategoryId' => $this->id,
            'name' => $this->name,
            'description' => $this->description??'',
            'addToInvoice' => $this->add_to_invoice,
            'price' => $this->price
        ];
    }
}
