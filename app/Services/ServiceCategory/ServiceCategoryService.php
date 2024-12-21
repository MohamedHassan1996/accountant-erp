<?php

namespace App\Services\ServiceCategory;

use App\Enums\ServiceCategory\ServiceCategoryAddToInvoiceStatus;
use App\Filters\ServiceCategory\FilterServiceCategory;
use App\Models\ServiceCategory\ServiceCategory;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ServiceCategoryService{

    public function allServiceCategorys(array $filters){

        $serviceCategories = QueryBuilder::for(ServiceCategory::class)
        ->allowedFilters([
            //AllowedFilter::custom('search', new FilterServiceCategory()), // Add a custom search filter
        ])
        ->get();
        return $serviceCategories;

    }

    public function createServiceCategory(array $serviceCategoryData){

        $serviceCategory = ServiceCategory::create([
            'name' => $serviceCategoryData['name'],
            'description' => $serviceCategoryData['description'],
            'add_to_invoice' => ServiceCategoryAddToInvoiceStatus::from($serviceCategoryData['addToInvoice']),
            'price' => $serviceCategoryData['price'],
        ]);

        return $serviceCategory;

    }

    public function editServiceCategory(string $serviceCategoryId){
        $serviceCategory = ServiceCategory::find($serviceCategoryId);

        return $serviceCategory;

    }

    public function updateServiceCategory(array $serviceCategoryData){

        $serviceCategory = ServiceCategory::find($serviceCategoryData['serviceCategoryId']);

        $serviceCategory->fill([
            'name' => $serviceCategoryData['name'],
            'description' => $serviceCategoryData['description'],
            'add_to_invoice' => ServiceCategoryAddToInvoiceStatus::from($serviceCategoryData['addToInvoice']),
            'price' => $serviceCategoryData['price'],
        ]);

        $serviceCategory->save();

        return $serviceCategory;

    }

    public function deleteServiceCategory(string $serviceCategoryId){
        $serviceCategory = ServiceCategory::find($serviceCategoryId);
        $serviceCategory->delete();
    }

}
