<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MPaymentGateway extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_payment_gateways';
    protected $fillable = ['name', 'type', 'active', 'config', 'sortorder'];

    protected $casts = [
        'active' => 'boolean',
        'config' => 'array',
    ];
}
