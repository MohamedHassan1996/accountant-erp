<?php

namespace App\Services\Client;

use App\Enums\Client\ClientServiceDiscountStatus;
use App\Enums\Client\ClientServiceDiscountType;
use App\Filters\ClientServiceDiscount\FilterClientServiceDiscount;
use App\Models\Client\ClientServiceDiscount;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ClientServiceDiscountService{

    public function allClientServiceDiscounts(array $filters){

        $clientServiceDiscountes = QueryBuilder::for(ClientServiceDiscount::class)
        ->allowedFilters([
            //AllowedFilter::custom('search', new FilterClientServiceDiscount()), // Add a custom search filter
            //AllowedFilter::exact('clientServiceDiscountType', 'clientServiceDiscount_type'),
        ])
        ->when(
            $filters['clientId'] ?? null,
            fn ($query) => $query->where('client_id', $filters['clientId'])
        )
        ->get();
        return $clientServiceDiscountes;

    }

    public function createClientServiceDiscount(array $clientServiceDiscountData){

        $clientServiceDiscount = ClientServiceDiscount::create([
            'service_category_id' => $clientServiceDiscountData['serviceCategoryId'],
            'discount' => $clientServiceDiscountData['discount'],
            'type' => ClientServiceDiscountType::from($clientServiceDiscountData['type'])->value,
            'is_active' => ClientServiceDiscountStatus::from($clientServiceDiscountData['isActive'])->value,
            'client_id' => $clientServiceDiscountData['clientId'],
        ]);

        return $clientServiceDiscount;

    }

    public function editClientServiceDiscount(string $clientServiceDiscountId){
        $clientServiceDiscount = ClientServiceDiscount::find($clientServiceDiscountId);

        return $clientServiceDiscount;

    }

    public function updateClientServiceDiscount(array $clientServiceDiscountData){

        $clientServiceDiscount = ClientServiceDiscount::find($clientServiceDiscountData['clientServiceDiscountId']);

        $clientServiceDiscount->fill([
            'service_category_id' => $clientServiceDiscountData['serviceCategoryId'],
            'discount' => $clientServiceDiscountData['discount'],
            'type' => ClientServiceDiscountType::from($clientServiceDiscountData['type'])->value,
            'is_active' => ClientServiceDiscountStatus::from($clientServiceDiscountData['isActive'])->value,
        ]);

        $clientServiceDiscount->save();

        return $clientServiceDiscount;

    }

    public function deleteClientServiceDiscount(string $clientServiceDiscountId){
        $clientServiceDiscount = ClientServiceDiscount::find($clientServiceDiscountId);
        $clientServiceDiscount->delete();
    }

}
