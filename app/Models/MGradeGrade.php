<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MGradeGrade extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_grade_grades';
    protected $fillable = [
        'itemid', 'user_id', 'rawgrade', 'rawgrademax', 'rawgrademin',
        'finalgrade', 'hidden', 'locked', 'overridden', 'excluded', 'feedback',
    ];

    protected $casts = [
        'rawgrade' => 'decimal:2', 'rawgrademax' => 'decimal:2', 'rawgrademin' => 'decimal:2',
        'finalgrade' => 'decimal:2',
        'hidden' => 'boolean', 'locked' => 'boolean', 'overridden' => 'boolean', 'excluded' => 'boolean',
    ];

    public function item()
    {
        return $this->belongsTo(MGradeItem::class, 'itemid');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
