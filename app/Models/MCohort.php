<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MCohort extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_cohorts';
    protected $fillable = ['context_id', 'name', 'idnumber', 'visible', 'description', 'component'];

    protected $casts = ['visible' => 'boolean'];

    public function members()
    {
        return $this->hasMany(MCohortMember::class, 'cohort_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'm_cohort_members', 'cohort_id', 'user_id')
            ->withPivot('timeadded');
    }
}
