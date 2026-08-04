<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MRepository extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_repositories';
    protected $fillable = ['type', 'name', 'active', 'sortorder', 'config'];

    protected $casts = [
        'active' => 'boolean',
        'config' => 'array',
    ];
}
