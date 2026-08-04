<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProgramMode extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['program_id', 'mode'];
    public $timestamps = false;

    protected $primaryKey = ['program_id', 'mode'];
}
