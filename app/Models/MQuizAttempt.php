<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MQuizAttempt extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_quiz_attempts';
    protected $fillable = ['quiz_id', 'user_id', 'attempt', 'state', 'timestart', 'timefinish', 'sumgrades'];

    protected $casts = [
        'timestart' => 'datetime',
        'timefinish' => 'datetime',
        'sumgrades' => 'decimal:2',
    ];

    const STATE_INPROGRESS = 'inprogress';
    const STATE_FINISHED = 'finished';
    const STATE_OVERDUE = 'overdue';

    public function quiz()
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
