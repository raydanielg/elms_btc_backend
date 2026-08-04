<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MCourseModuleCompletion extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_course_modules_completion';
    protected $fillable = ['course_module_id', 'user_id', 'completionstate', 'timemodified'];

    const STATE_INCOMPLETE = 'INCOMPLETE';
    const STATE_COMPLETE = 'COMPLETE';
    const STATE_COMPLETE_FAIL = 'COMPLETE_FAIL';

    public function courseModule()
    {
        return $this->belongsTo(MCourseModule::class, 'course_module_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
