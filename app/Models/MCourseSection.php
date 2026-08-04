<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MCourseSection extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_course_sections';
    protected $fillable = ['course_id', 'section', 'name', 'summary', 'sequence', 'visible'];

    protected $casts = ['visible' => 'boolean'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function modules()
    {
        return $this->hasMany(MCourseModule::class, 'section_id')->orderBy('created_at');
    }

    /**
     * Get ordered module IDs from sequence field.
     */
    public function getModuleIds(): array
    {
        return array_filter(explode(',', $this->sequence ?? ''), fn($s) => trim($s) !== '');
    }
}
