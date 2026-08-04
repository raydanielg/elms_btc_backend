<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MRoleContextLevel extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_role_context_levels';
    public $timestamps = false;

    protected $fillable = ['role_id', 'contextlevel'];

    public function role()
    {
        return $this->belongsTo(MRole::class, 'role_id');
    }
}
