<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MQuestionAnswer extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_question_answers';
    protected $fillable = ['question_id', 'answer_no', 'answer', 'fraction', 'feedback'];

    protected $casts = ['fraction' => 'decimal:2'];

    public function question()
    {
        return $this->belongsTo(MQuestion::class, 'question_id');
    }
}
