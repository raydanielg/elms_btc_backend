<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MCompetencyFramework extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_competency_frameworks';
    protected $fillable = ['name', 'description', 'context_id'];

    public function competencies()
    {
        return $this->hasMany(MCompetency::class, 'framework_id');
    }
}
