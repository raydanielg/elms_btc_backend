<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MBadgeIssued extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_badge_issued';
    protected $fillable = ['badge_id', 'user_id', 'visible'];

    protected $casts = ['visible' => 'boolean', 'issued_at' => 'datetime'];

    public function badge()
    {
        return $this->belongsTo(MBadge::class, 'badge_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
