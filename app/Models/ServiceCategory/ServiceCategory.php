<?php

namespace App\Models\ServiceCategory;

use App\Enums\ServiceCategory\ServiceCategoryAddToInvoiceStatus;
use App\Traits\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceCategory extends Model
{
    use HasFactory, CreatedUpdatedBy, SoftDeletes;
    protected $fillable = [
        'name',
        'description',
        'price',
        'add_to_invoice'
    ];

    protected $casts = [
        'add_to_invoice' => ServiceCategoryAddToInvoiceStatus::class
    ];

    public function getPrice()
    {
        if ($this->add_to_invoice == ServiceCategoryAddToInvoiceStatus::REMOVE) {
            return 0;
        }
        return $this->price;
    }
}
