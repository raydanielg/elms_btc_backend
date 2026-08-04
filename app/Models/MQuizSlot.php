<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MQuizSlot extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_quiz_slots';
    protected $fillable = ['quiz_id', 'slot', 'question_id', 'maxmark', 'page'];

    protected $casts = ['maxmark' => 'decimal:2'];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    public function question()
    {
        return $this->belongsTo(MQuestion::class, 'question_id');
    }
}
