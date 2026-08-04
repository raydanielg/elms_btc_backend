<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MUserEnrolment extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_user_enrolments';
    protected $fillable = ['enrol_id', 'user_id', 'status', 'timestart', 'timeend'];

    protected $casts = [
        'timestart' => 'datetime',
        'timeend' => 'datetime',
    ];

    const STATUS_ACTIVE = 'ACTIVE';
    const STATUS_SUSPENDED = 'SUSPENDED';

    public function enrol()
    {
        return $this->belongsTo(MEnrol::class, 'enrol_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
