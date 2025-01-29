<?php

namespace App\Services\Client;

use App\Enums\Client\AddableToBulck;
use App\Enums\Client\AddableToBulk;
use App\Filters\Client\FilterClient;
use App\Models\Client\Client;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ClientService{

    public function allClients(){

        $clients = QueryBuilder::for(Client::class)
        ->allowedFilters([
            AllowedFilter::exact('clientId', 'client_id'), // Add a custom search filter
            AllowedFilter::custom('search', new FilterClient()), // Add a custom search filter
        ])
        ->get();
        return $clients;

    }

    public function createClient(array $clientData){

        $client = Client::create([
            'iva' => $clientData['iva'],
            'ragione_sociale' => $clientData['ragioneSociale'],
            'cf' => $clientData['cf'],
            'note' => $clientData['note'],
            'phone' => $clientData['phone']??"",
            'email' => $clientData['email']??"",
            'hours_per_month' => $clientData['hoursPerMonth']??0,
            'price' => $clientData['price'],
            'payment_type_id'=>$clientData['paymentTypeId'],
            'pay_steps_id'=>$clientData['payStepsId'],
            'payment_type_two_id'=>$clientData['paymentTypeTwoId'],
            'iban'=>$clientData['iban'],
            'abi'=>$clientData['abi'],
            'cab'=>$clientData['cab'],
            'addable_to_bulk_invoice'=>AddableToBulk::from($clientData['AddableToBulkInvoice'])->value,
            'allowed_days_to_pay'=>$clientData['AllowedDaysToPay']??"",
        ]);

        return $client;

    }

    public function editClient(string $clientId){
        $client = Client::with(['addresses', 'contacts'])->find($clientId);

        return $client;

    }

    public function updateClient(array $clientData){

        $client = Client::find($clientData['clientId']);

        $client->fill([
            'iva' => $clientData['iva'],
            'ragione_sociale' => $clientData['ragioneSociale'],
            'cf' => $clientData['cf'],
            'note' => $clientData['note'],
            'phone' => $clientData['phone']??"",
            'email' => $clientData['email']??"",
            'hours_per_month' => $clientData['hoursPerMonth']??0,
            'price' => $clientData['price'],
            'payment_type_id'=>$clientData['paymentTypeId']??"" ,
            'pay_steps_id'=>$clientData['payStepsId']??"",
            'payment_type_two_id'=>$clientData['paymentTypeTwoId']??"",
            'iban'=>$clientData['iban']??"",
            'abi'=>$clientData['abi']??"",
            'cab'=>$clientData['cab']??"",
            'addable_to_bulk_invoice'=>AddableToBulk::from($clientData['AddableToBulkInvoice'])->value,
            'allowed_days_to_pay'=>$clientData['AllowedDaysToPay']??"",
        ]);

        $client->save();

        return $client;

    }

    public function deleteClient(string $clientId){
        $client = Client::find($clientId);
        $client->delete();
    }

}
