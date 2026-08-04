<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['course_id', 'title', 'instructions', 'max_marks', 'open_at', 'due_at', 'timer_minutes', 'allow_late', 'cutoffdate', 'allowsubmissionsfromdate', 'maxattempts', 'submissiondrafts', 'teamsubmission', 'blindmarking', 'markingworkflow'];

    protected function casts(): array
    {
        return [
            'open_at' => 'datetime',
            'due_at' => 'datetime',
            'cutoffdate' => 'datetime',
            'allowsubmissionsfromdate' => 'datetime',
            'allow_late' => 'boolean',
            'submissiondrafts' => 'boolean',
            'teamsubmission' => 'boolean',
            'blindmarking' => 'boolean',
            'markingworkflow' => 'boolean',
        ];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function submissions()
    {
        return $this->hasMany(AssignmentSubmission::class);
    }
}
