<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MCalendarEvent extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_calendar_events';
    protected $fillable = [
        'name', 'description', 'timestart', 'timeduration',
        'eventtype', 'course_id', 'group_id', 'category_id', 'user_id',
        'location', 'visible', 'repeattype', 'repeatinterval',
    ];

    protected $casts = [
        'timestart' => 'datetime',
        'visible' => 'boolean',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function group()
    {
        return $this->belongsTo(MGroup::class, 'group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
