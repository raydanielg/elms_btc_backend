<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MCertificateTemplate extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_certificate_templates';
    protected $fillable = [
        'name', 'type', 'context_id', 'page_size', 'orientation',
        'trigger_type', 'trigger_config', 'requires_fees_cleared', 'is_active',
    ];

    protected $casts = [
        'trigger_config' => 'array',
        'requires_fees_cleared' => 'boolean',
        'is_active' => 'boolean',
    ];

    const TYPE_COMPLETION = 'COMPLETION';
    const TYPE_AWARD = 'AWARD';
    const TYPE_TRANSCRIPT = 'TRANSCRIPT';
    const TYPE_PARTICIPATION = 'PARTICIPATION';

    const TRIGGER_MANUAL = 'MANUAL';
    const TRIGGER_COURSE_COMPLETION = 'COURSE_COMPLETION';
    const TRIGGER_ACTIVITY_COMPLETION = 'ACTIVITY_COMPLETION';
    const TRIGGER_GRADE_THRESHOLD = 'GRADE_THRESHOLD';
    const TRIGGER_PROGRAM_COMPLETION = 'PROGRAM_COMPLETION';
    const TRIGGER_COMPETENCY = 'COMPETENCY';
    const TRIGGER_COHORT = 'COHORT';

    public function pages()
    {
        return $this->hasMany(MCertificatePage::class, 'template_id')->orderBy('page_no');
    }

    public function issues()
    {
        return $this->hasMany(MCertificateIssue::class, 'template_id');
    }
}
