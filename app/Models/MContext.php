<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MContext extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_contexts';
    protected $fillable = ['contextlevel', 'instanceid', 'path', 'depth'];

    // Context level constants (spec §2.1)
    public const LEVEL_SYSTEM = 10;
    public const LEVEL_USER = 30;
    public const LEVEL_COURSECAT = 40;
    public const LEVEL_COURSE = 50;
    public const LEVEL_MODULE = 70;
    public const LEVEL_BLOCK = 80;

    public function roleAssignments()
    {
        return $this->hasMany(MRoleAssignment::class, 'context_id');
    }

    public function roleCapabilities()
    {
        return $this->hasMany(MRoleCapability::class, 'context_id');
    }

    /**
     * Get ancestor contexts (including self) by path.
     * Used by the permission resolution algorithm.
     */
    public function ancestors()
    {
        $pathIds = array_filter(explode('/', $this->path), fn($p) => $p !== '');
        if (empty($pathIds)) return self::where('id', $this->id)->get();
        return self::whereIn('id', $pathIds)->orderBy('depth')->get();
    }

    /**
     * Get ancestor context IDs from path string.
     */
    public function ancestorIds(): array
    {
        return array_filter(explode('/', $this->path), fn($p) => $p !== '');
    }
}
