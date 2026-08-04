<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MAward extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_awards';
    protected $fillable = ['name', 'description', 'criteria', 'template_id', 'is_active'];

    protected $casts = ['criteria' => 'array', 'is_active' => 'boolean'];

    public function template()
    {
        return $this->belongsTo(MCertificateTemplate::class, 'template_id');
    }
}
