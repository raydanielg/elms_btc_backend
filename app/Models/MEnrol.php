<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MEnrol extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_enrol';
    protected $fillable = [
        'enrol', 'course_id', 'status', 'role_id',
        'enrolstartdate', 'enrolenddate', 'enrolperiod',
        'customint1', 'customchar1', 'customchar2', 'sortorder',
    ];

    protected $casts = [
        'enrolstartdate' => 'datetime',
        'enrolenddate' => 'datetime',
    ];

    const STATUS_ENABLED = 'ENABLED';
    const STATUS_DISABLED = 'DISABLED';

    const METHOD_MANUAL = 'manual';
    const METHOD_SELF = 'self';
    const METHOD_COHORT = 'cohort';
    const METHOD_PAYMENT = 'payment';
    const METHOD_GUEST = 'guest';

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function userEnrolments()
    {
        return $this->hasMany(MUserEnrolment::class, 'enrol_id');
    }

    public function role()
    {
        return $this->belongsTo(MRole::class, 'role_id');
    }
}
