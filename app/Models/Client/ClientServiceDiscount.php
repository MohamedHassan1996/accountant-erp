<?php

namespace App\Models\Client;

use App\Enums\Client\ClientServiceDiscountStatus;
use App\Enums\Client\ClientServiceDiscountType;
use App\Enums\Client\ClientShowStatus;
use App\Models\ServiceCategory\ServiceCategory;
use App\Traits\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'is_show'
    ];

    protected $casts = [
        'is_active' => ClientServiceDiscountStatus::class,
        'type' => ClientServiceDiscountType::class,
        'is_show'=>ClientShowStatus::class
    ];

    public function serviceCategory() : BelongsToMany
    {
        return $this->belongsToMany(ServiceCategory::class, 'client_service_discounts', 'client_id', 'service_category_id');
    }
}
