<?php

namespace App\Models\Client;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientBankAccount extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'client_id',
        'iban',
        'abi',
        'cab',
        'is_main',
    ];
}
