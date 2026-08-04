<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MCohortMember extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_cohort_members';
    public $timestamps = false;

    protected $fillable = ['cohort_id', 'user_id'];

    public function cohort()
    {
        return $this->belongsTo(MCohort::class, 'cohort_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
