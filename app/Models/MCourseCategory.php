<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MCourseCategory extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_course_categories';
    protected $fillable = ['name', 'idnumber', 'parent_id', 'path', 'depth', 'visible', 'sortorder', 'description'];

    protected $casts = ['visible' => 'boolean'];

    public function parent()
    {
        return $this->belongsTo(MCourseCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(MCourseCategory::class, 'parent_id')->orderBy('sortorder');
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'category_id');
    }

    /**
     * Build path after creation/update.
     */
    public static function buildPath(self $category): string
    {
        if (!$category->parent_id) return '/' . $category->id;
        $parent = self::find($category->parent_id);
        return $parent ? $parent->path . '/' . $category->id : '/' . $category->id;
    }
}
