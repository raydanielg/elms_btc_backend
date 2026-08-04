<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MRoleCapability extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_role_capabilities';
    public $timestamps = false;

    protected $fillable = ['role_id', 'capability_id', 'context_id', 'permission', 'timemodified', 'modifier_id'];

    public function role()
    {
        return $this->belongsTo(MRole::class, 'role_id');
    }

    public function capability()
    {
        return $this->belongsTo(MCapability::class, 'capability_id');
    }

    public function context()
    {
        return $this->belongsTo(MContext::class, 'context_id');
    }
}
