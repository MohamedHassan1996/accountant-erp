<?php

namespace App\Models\Client;

use App\Traits\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Client extends Model
{
    use HasFactory, SoftDeletes, CreatedUpdatedBy;

    protected $fillable = [
        'iva',
        'ragione_sociale',
        'cf',
        'note',
        'email',
        'phone',
        'note'
    ];

    public function addresses()
    {
        return $this->hasMany(ClientAddress::class, 'client_id');
    }

    public function contacts()
    {
        return $this->hasMany(ClientContact::class, 'client_id');
    }

}
