<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MQuestionCategory extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_question_categories';
    protected $fillable = ['context_id', 'parent_id', 'name', 'info', 'sortorder'];

    public function parent()
    {
        return $this->belongsTo(MQuestionCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(MQuestionCategory::class, 'parent_id');
    }

    public function questions()
    {
        return $this->hasMany(MQuestion::class, 'category_id');
    }
}
