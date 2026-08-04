<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MCompetency extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_competencies';
    protected $fillable = ['framework_id', 'parent_id', 'shortname', 'description', 'sortorder'];

    public function framework()
    {
        return $this->belongsTo(MCompetencyFramework::class, 'framework_id');
    }

    public function parent()
    {
        return $this->belongsTo(MCompetency::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(MCompetency::class, 'parent_id');
    }

    public function userCompetencies()
    {
        return $this->hasMany(MUserCompetency::class, 'competency_id');
    }
}
