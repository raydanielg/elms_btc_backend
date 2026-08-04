<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MCertificatePage extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_certificate_pages';
    protected $fillable = ['template_id', 'page_no', 'width', 'height', 'background_image_url', 'margin'];

    public function template()
    {
        return $this->belongsTo(MCertificateTemplate::class, 'template_id');
    }

    public function elements()
    {
        return $this->hasMany(MCertificateElement::class, 'page_id')->orderBy('z_index');
    }
}
