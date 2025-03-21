<?php

namespace App\Models\Client;

use App\Enums\Client\ClientServiceDiscountStatus;
use App\Enums\Client\ClientServiceDiscountType;
use App\Enums\Client\ClientShowStatus;
use App\Traits\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientServiceDiscount extends Model
{
    use HasFactory, SoftDeletes, CreatedUpdatedBy;
    protected $fillable = [
        'client_id',
        'service_category_id',
        'discount',
        'is_active',
        'type',
        'is_show',
        'category'
    ];

    protected $casts = [
        'is_active' => ClientServiceDiscountStatus::class,
        'type' => ClientServiceDiscountType::class,
        'is_show'=>ClientShowStatus::class
    ];
}
