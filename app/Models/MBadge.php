<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MBadge extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_badges';
    protected $fillable = ['name', 'description', 'image', 'user_id', 'context_id', 'type', 'issued_on', 'active'];

    protected $casts = ['active' => 'boolean', 'issued_on' => 'datetime'];

    public function criteria()
    {
        return $this->hasMany(MBadgeCriteria::class, 'badge_id');
    }

    public function issued()
    {
        return $this->hasMany(MBadgeIssued::class, 'badge_id');
    }
}
