<?php

namespace App\Models\Parameter;

use App\Traits\CreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Parameter extends Model
{
    use HasFactory;
    use SoftDeletes;
    use CreatedUpdatedBy;

    const CREATED_AT = 'data_creazione';
    const UPDATED_AT = 'versione';

    protected $fillable = [
        'parameter_name',
        'parameter_order'
    ];

}
