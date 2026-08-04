<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MModule extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_modules';
    protected $fillable = ['name', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function courseModules()
    {
        return $this->hasMany(MCourseModule::class, 'module_id');
    }
}
