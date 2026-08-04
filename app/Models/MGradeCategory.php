<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MGradeCategory extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_grade_categories';
    protected $fillable = ['course_id', 'fullname', 'aggregation', 'aggregateonlygraded', 'aggregateoutcomes', 'droplow', 'keephigh'];

    protected $casts = [
        'aggregateonlygraded' => 'boolean',
        'aggregateoutcomes' => 'boolean',
        'droplow' => 'boolean',
    ];

    const AGG_NATURAL = 'natural';
    const AGG_WEIGHTED = 'weighted';
    const AGG_MEAN = 'mean';
    const AGG_SUM = 'sum';
    const AGG_MIN = 'min';
    const AGG_MAX = 'max';

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function items()
    {
        return $this->hasMany(MGradeItem::class, 'category_id');
    }
}
