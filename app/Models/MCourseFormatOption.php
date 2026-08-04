<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MCourseFormatOption extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_course_format_options';
    protected $fillable = ['course_id', 'format', 'name', 'value'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
