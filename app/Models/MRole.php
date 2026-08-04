<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MRole extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_roles';
    protected $fillable = ['name', 'shortname', 'archetype', 'description', 'sortorder'];

    // Archetype constants — maps to Butimba role enum
    public const ARCHETYPE_SUPER_ADMIN = 'SUPER_ADMIN';
    public const ARCHETYPE_PRINCIPAL = 'PRINCIPAL';
    public const ARCHETYPE_REGISTRAR = 'REGISTRAR';
    public const ARCHETYPE_ACCOUNTANT = 'ACCOUNTANT';
    public const ARCHETYPE_TUTOR = 'TUTOR';
    public const ARCHETYPE_STUDENT = 'STUDENT';
    public const ARCHETYPE_APPLICANT = 'APPLICANT';

    // Moodle-style shortnames
    public const SHORT_MANAGER = 'manager';
    public const SHORT_COURSE_CREATOR = 'coursecreator';
    public const SHORT_EDITING_TEACHER = 'editingteacher';
    public const SHORT_TEACHER = 'teacher';
    public const SHORT_STUDENT = 'student';
    public const SHORT_GUEST = 'guest';
    public const SHORT_AUTHENTICATED = 'authenticateduser';

    public function capabilities()
    {
        return $this->hasMany(MRoleCapability::class, 'role_id');
    }

    public function assignments()
    {
        return $this->hasMany(MRoleAssignment::class, 'role_id');
    }

    public function contextLevels()
    {
        return $this->hasMany(MRoleContextLevel::class, 'role_id');
    }
}
