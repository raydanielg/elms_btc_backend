<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MConfig extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_config';
    protected $fillable = ['name', 'value', 'description', 'type', 'options', 'hidden'];

    protected $casts = ['hidden' => 'boolean', 'options' => 'array'];
}
