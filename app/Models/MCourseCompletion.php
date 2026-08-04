<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MCourseCompletion extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_course_completions';
    protected $fillable = ['course_id', 'user_id', 'status', 'timecompleted'];

    protected $casts = ['timecompleted' => 'datetime'];

    const STATUS_INPROGRESS = 'INPROGRESS';
    const STATUS_COMPLETE = 'COMPLETE';
    const STATUS_COMPLETE_FAIL = 'COMPLETE_FAIL';

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
