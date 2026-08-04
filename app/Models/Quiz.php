<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['course_id', 'title', 'duration_minutes', 'total_marks', 'open_at', 'close_at', 'grademethod', 'attempts', 'shuffleanswers', 'questionsperpage', 'reviewoptions'];

    protected function casts(): array
    {
        return [
            'open_at' => 'datetime',
            'close_at' => 'datetime',
            'shuffleanswers' => 'boolean',
            'reviewoptions' => 'array',
        ];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function questions()
    {
        return $this->hasMany(QuizQuestion::class);
    }

    public function attempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    // Moodle-style relationships
    public function slots()
    {
        return $this->hasMany(MQuizSlot::class, 'quiz_id')->orderBy('slot');
    }

    public function mAttempts()
    {
        return $this->hasMany(MQuizAttempt::class, 'quiz_id');
    }

    public function grades()
    {
        return $this->hasMany(MQuizGrade::class, 'quiz_id');
    }
}
