<?php

namespace App\Models\Client;

use App\Traits\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientPayInstallment extends Model
{
    use HasFactory, SoftDeletes, CreatedUpdatedBy;

    protected $fillable = [
        'start_at',
        'end_at',
        'amount',
        'description',
        'client_id',
    ];

    public function payInstallmentSubData(){
        return $this->hasMany(ClientPayInstallmentSubData::class, 'client_pay_installment_id');
    }
}
