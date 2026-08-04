<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MCertificateElement extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_certificate_elements';
    protected $fillable = [
        'page_id', 'element_type', 'data', 'pos_x', 'pos_y', 'width',
        'font', 'font_size', 'colour', 'align', 'refpoint', 'z_index',
    ];

    protected $casts = ['data' => 'array'];

    const TYPE_STATIC_TEXT = 'static_text';
    const TYPE_RECIPIENT_NAME = 'recipient_name';
    const TYPE_PROGRAM_NAME = 'program_name';
    const TYPE_COURSE_NAME = 'course_name';
    const TYPE_TEACHING_SUBJECT = 'teaching_subject';
    const TYPE_GRADE = 'grade';
    const TYPE_DATE = 'date';
    const TYPE_VERIFICATION_CODE = 'verification_code';
    const TYPE_QR_CODE = 'qr_code';
    const TYPE_SIGNATURE_IMAGE = 'signature_image';
    const TYPE_SEAL_LOGO = 'seal_logo';
    const TYPE_WATERMARK = 'watermark';
    const TYPE_SERIAL_NUMBER = 'serial_number';
    const TYPE_CUSTOM_FIELD = 'custom_field';

    public function page()
    {
        return $this->belongsTo(MCertificatePage::class, 'page_id');
    }
}
