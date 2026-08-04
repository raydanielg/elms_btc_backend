<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['code', 'title', 'fullname', 'shortname', 'program_id', 'subject_id', 'category_id', 'year', 'semester', 'credits', 'description', 'is_active', 'format', 'startdate', 'enddate', 'enablecompletion', 'groupmode'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'enablecompletion' => 'boolean',
            'startdate' => 'date',
            'enddate' => 'date',
        ];
    }

    public function category()
    {
        return $this->belongsTo(MCourseCategory::class, 'category_id');
    }

    public function sections()
    {
        return $this->hasMany(MCourseSection::class, 'course_id')->orderBy('section');
    }

    public function courseModules()
    {
        return $this->hasMany(MCourseModule::class, 'course_id');
    }

    public function formatOptions()
    {
        return $this->hasMany(MCourseFormatOption::class, 'course_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function tutors()
    {
        return $this->belongsToMany(User::class, 'course_tutors', 'course_id', 'tutor_id')
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'enrollments', 'course_id', 'student_id')
            ->withPivot(['status', 'enrolled_at'])
            ->withTimestamps();
    }

    public function topics()
    {
        return $this->hasMany(Topic::class);
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    public function onlineClasses()
    {
        return $this->hasMany(OnlineClass::class);
    }

    public function discussionThreads()
    {
        return $this->hasMany(DiscussionThread::class);
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class);
    }

    public function results()
    {
        return $this->hasMany(Result::class);
    }
}
