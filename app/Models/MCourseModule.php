<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MCourseModule extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_course_modules';
    protected $fillable = [
        'course_id', 'module_id', 'instance_id', 'section_id',
        'idnumber', 'visible', 'visibleoncoursepage',
        'completion', 'completiongradeitemnumber', 'completionview', 'completionexpected',
        'availability', 'groupmode',
    ];

    protected $casts = [
        'visible' => 'boolean',
        'visibleoncoursepage' => 'boolean',
        'completionview' => 'boolean',
        'completionexpected' => 'datetime',
        'availability' => 'array',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function module()
    {
        return $this->belongsTo(MModule::class, 'module_id');
    }

    public function section()
    {
        return $this->belongsTo(MCourseSection::class, 'section_id');
    }
}
