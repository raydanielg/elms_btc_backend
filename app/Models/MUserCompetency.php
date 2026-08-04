<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MUserCompetency extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_user_competencies';
    protected $fillable = ['competency_id', 'user_id', 'proficiency', 'rating', 'reviewer_id', 'status'];

    public function competency()
    {
        return $this->belongsTo(MCompetency::class, 'competency_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
