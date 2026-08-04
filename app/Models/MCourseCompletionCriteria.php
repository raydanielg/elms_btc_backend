<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MCourseCompletionCriteria extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_course_completion_criteria';
    protected $fillable = ['course_id', 'criteriatype', 'module_instance', 'gradepass', 'enrolmentdate', 'date'];

    protected $casts = [
        'gradepass' => 'decimal:2',
        'enrolmentdate' => 'datetime',
        'date' => 'datetime',
    ];

    const TYPE_ACTIVITY = 'activity';
    const TYPE_GRADE = 'grade';
    const TYPE_COURSE = 'course';
    const TYPE_SELF = 'self';
    const TYPE_DATE = 'date';

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
