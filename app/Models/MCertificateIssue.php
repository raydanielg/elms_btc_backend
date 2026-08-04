<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MCertificateIssue extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_certificate_issues';
    protected $fillable = [
        'template_id', 'user_id', 'course_id', 'program_id',
        'code', 'hash', 'grade', 'classification',
        'issued_by', 'issued_at', 'pdf_fileid',
        'status', 'revoke_reason', 'serial_number',
    ];

    protected $casts = ['issued_at' => 'datetime'];

    const STATUS_ISSUED = 'ISSUED';
    const STATUS_REVOKED = 'REVOKED';
    const STATUS_REISSUED = 'REISSUED';

    public function template()
    {
        return $this->belongsTo(MCertificateTemplate::class, 'template_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
