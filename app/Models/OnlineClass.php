<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OnlineClass extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['course_id', 'title', 'platform', 'link', 'scheduled_at', 'duration_minutes'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime'];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
