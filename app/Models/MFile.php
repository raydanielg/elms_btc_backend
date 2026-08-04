<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MFile extends Model
{
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'm_files';
    public $timestamps = true;

    protected $fillable = [
        'contenthash', 'context_id', 'component', 'filearea', 'itemid',
        'filepath', 'filename', 'filesize', 'mimetype', 'user_id',
        'storage', 'pathnamehash',
    ];

    protected $casts = [
        'timecreated' => 'datetime',
    ];

    /**
     * Generate pathnamehash for a file.
     */
    public static function makePathnameHash(string $contextId, string $component, string $filearea, ?string $itemid, string $filepath, string $filename): string
    {
        $item = $itemid ? $itemid : '0';
        return sha1("/{$contextId}/{$component}/{$filearea}/{$item}{$filepath}{$filename}");
    }
}
