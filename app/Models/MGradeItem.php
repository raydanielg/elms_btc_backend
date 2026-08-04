<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MGradeItem extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_grade_items';
    protected $fillable = [
        'course_id', 'category_id', 'itemname', 'itemtype', 'itemmodule',
        'iteminstance', 'itemnumber', 'grademax', 'grademin', 'gradepass',
        'multfactor', 'aggregationcoef', 'sortorder', 'hidden', 'locked', 'needsupdate',
    ];

    protected $casts = [
        'grademax' => 'decimal:2', 'grademin' => 'decimal:2', 'gradepass' => 'decimal:2',
        'multfactor' => 'decimal:4', 'aggregationcoef' => 'decimal:4',
        'hidden' => 'boolean', 'locked' => 'boolean', 'needsupdate' => 'boolean',
    ];

    const TYPE_COURSE = 'course';
    const TYPE_CATEGORY = 'category';
    const TYPE_MOD = 'mod';
    const TYPE_MANUAL = 'manual';

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function category()
    {
        return $this->belongsTo(MGradeCategory::class, 'category_id');
    }

    public function grades()
    {
        return $this->hasMany(MGradeGrade::class, 'itemid');
    }
}
