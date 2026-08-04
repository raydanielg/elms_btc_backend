<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MQuestion extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_questions';
    protected $fillable = [
        'category_id', 'qtype', 'name', 'questiontext',
        'generalfeedback', 'defaultmark', 'penalty', 'hidden',
    ];

    protected $casts = [
        'defaultmark' => 'decimal:2',
        'penalty' => 'decimal:2',
        'hidden' => 'boolean',
    ];

    const TYPE_MULTICHOICE = 'multichoice';
    const TYPE_TRUEFALSE = 'truefalse';
    const TYPE_SHORTANSWER = 'shortanswer';
    const TYPE_ESSAY = 'essay';
    const TYPE_NUMERICAL = 'numerical';
    const TYPE_MATCHING = 'matching';
    const TYPE_DESCRIPTION = 'description';

    public function category()
    {
        return $this->belongsTo(MQuestionCategory::class, 'category_id');
    }

    public function answers()
    {
        return $this->hasMany(MQuestionAnswer::class, 'question_id')->orderBy('answer_no');
    }

    public function quizSlots()
    {
        return $this->hasMany(MQuizSlot::class, 'question_id');
    }
}
