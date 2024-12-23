<?php

namespace App\Models\Client;

use App\Models\Parameter\ParameterValue;
use App\Traits\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientContact extends Model
{
    use HasFactory, SoftDeletes, CreatedUpdatedBy;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'prefix',
        'note',
        'client_id',
        'parameter_id'
    ];

    public function parameter(): HasOne
    {
        return $this->hasOne(ParameterValue::class, 'id', 'parameter_value_id');
    }


}
