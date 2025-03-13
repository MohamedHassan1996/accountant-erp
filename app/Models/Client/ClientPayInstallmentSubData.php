<?php

namespace App\Models\Client;

use App\Traits\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientPayInstallmentSubData extends Model
{
    use HasFactory, SoftDeletes, CreatedUpdatedBy;

    protected $table = 'client_pay_installment_sub_data';
    protected $fillable = [
        'price',
        'description',
        'client_pay_installment_id',
    ];
}
