<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MCapability extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_capabilities';
    protected $fillable = ['name', 'contextlevel', 'riskbitmask', 'component'];

    // Risk bitmasks (spec §3.1)
    public const RISK_XSS = 1;
    public const RISK_CONFIG = 2;
    public const RISK_PERSONAL = 4;
    public const RISK_SPAM = 8;
    public const RISK_DATALOSS = 16;

    // Permission values (spec §3.1)
    public const ALLOW = 1;
    public const PREVENT = -1;
    public const PROHIBIT = -1000;
    public const INHERIT = 0;

    public function roleCapabilities()
    {
        return $this->hasMany(MRoleCapability::class, 'capability_id');
    }
}
