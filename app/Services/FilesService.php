<?php

namespace App\Services;

use App\Models\MFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FilesService
{
    private const MAX_FILE_SIZE = 104857600; // 100MB
    private const ALLOWED_MIMES = [
        'application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain', 'text/csv', 'application/zip', 'video/mp4', 'audio/mpeg',
    ];

    /**
     * Upload a file to a draft area (spec §18.3).
     * Draft area = user's staging area before saving to an item.
     */
    public function uploadDraft(UploadedFile $file, string $userId): MFile
    {
        $this->validateFile($file);

        $content = file_get_contents($file->getRealPath());
        $contenthash = sha1($content);

        // Dedup: if contenthash exists, reuse the stored file
        $existing = MFile::where('contenthash', $contenthash)
            ->where('filearea', 'draft')
            ->first();

        if ($existing) {
            // Create a new record pointing to same content
            $draftId = Str::uuid();
            $record = MFile::create([
                'contenthash' => $contenthash,
                'context_id' => null,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftId,
                'filepath' => '/',
                'filename' => $file->getClientOriginalName(),
                'filesize' => $file->getSize(),
                'mimetype' => $file->getMimeType(),
                'user_id' => $userId,
                'storage' => 'local',
                'pathnamehash' => MFile::makePathnameHash('draft', 'user', 'draft', $draftId, '/', $file->getClientOriginalName()),
            ]);
        } else {
            // Store the file
            $storedPath = $this->storeFile($content, $contenthash);

            $draftId = Str::uuid();
            $record = MFile::create([
                'contenthash' => $contenthash,
                'context_id' => null,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftId,
                'filepath' => '/',
                'filename' => $file->getClientOriginalName(),
                'filesize' => $file->getSize(),
                'mimetype' => $file->getMimeType(),
                'user_id' => $userId,
                'storage' => 'local',
                'pathnamehash' => MFile::makePathnameHash('draft', 'user', 'draft', $draftId, '/', $file->getClientOriginalName()),
            ]);
        }

        return $record;
    }

    /**
     * Save files from draft area to a permanent file area (spec §18.3).
     */
    public function saveFromDraft(string $draftItemId, string $contextId, string $component, string $filearea, string $itemId, string $userId): array
    {
        $draftFiles = MFile::where('itemid', $draftItemId)
            ->where('filearea', 'draft')
            ->where('user_id', $userId)
            ->get();

        $saved = [];
        foreach ($draftFiles as $draft) {
            $record = MFile::create([
                'contenthash' => $draft->contenthash,
                'context_id' => $contextId,
                'component' => $component,
                'filearea' => $filearea,
                'itemid' => $itemId,
                'filepath' => $draft->filepath,
                'filename' => $draft->filename,
                'filesize' => $draft->filesize,
                'mimetype' => $draft->mimetype,
                'user_id' => $userId,
                'storage' => $draft->storage,
                'pathnamehash' => MFile::makePathnameHash($contextId, $component, $filearea, $itemId, $draft->filepath, $draft->filename),
            ]);
            $saved[] = $record;
        }

        // Clean up draft records
        MFile::where('itemid', $draftItemId)->where('filearea', 'draft')->delete();

        return $saved;
    }

    /**
     * Get files for a specific file area.
     */
    public function getFiles(string $contextId, string $component, string $filearea, ?string $itemId = null)
    {
        $query = MFile::where('context_id', $contextId)
            ->where('component', $component)
            ->where('filearea', $filearea);

        if ($itemId) {
            $query->where('itemid', $itemId);
        }

        return $query->orderBy('filename')->get();
    }

    /**
     * Get a single file by pathnamehash for download.
     */
    public function getFile(string $contextId, string $component, string $filearea, string $itemId, string $filename): ?MFile
    {
        $hash = MFile::makePathnameHash($contextId, $component, $filearea, $itemId, '/', $filename);
        return MFile::where('pathnamehash', $hash)->first();
    }

    /**
     * Generate a signed download URL (spec §18.3).
     */
    public function generateSignedUrl(MFile $file, string $userId, int $expiresMinutes = 30): string
    {
        $expires = now()->addMinutes($expiresMinutes)->timestamp;
        $signature = hash_hmac('sha256', $file->id . '|' . $userId . '|' . $expires, config('app.key'));

        return route('api.files.download', [
            'fileId' => $file->id,
            'expires' => $expires,
            'signature' => $signature,
        ]);
    }

    /**
     * Verify a signed download URL.
     */
    public function verifySignedUrl(string $fileId, int $expires, string $signature, string $userId): bool
    {
        if (now()->timestamp > $expires) return false;

        $expected = hash_hmac('sha256', $fileId . '|' . $userId . '|' . $expires, config('app.key'));
        return hash_equals($expected, $signature);
    }

    /**
     * Delete a file.
     */
    public function deleteFile(string $fileId): bool
    {
        $file = MFile::find($fileId);
        if (!$file) return false;

        // Check if other records use the same contenthash
        $otherRecords = MFile::where('contenthash', $file->contenthash)
            ->where('id', '!=', $file->id)
            ->exists();

        if (!$otherRecords) {
            // Delete the physical file
            $path = "files/{$file->contenthash}";
            Storage::disk('local')->delete($path);
        }

        $file->delete();
        return true;
    }

    /**
     * Validate uploaded file.
     */
    private function validateFile(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \Exception('File size exceeds maximum allowed (100MB).');
        }

        $mime = $file->getMimeType();
        if (!in_array($mime, self::ALLOWED_MIMES)) {
            throw new \Exception("File type '{$mime}' is not allowed.");
        }
    }

    /**
     * Store file content on disk.
     */
    private function storeFile(string $content, string $contenthash): string
    {
        $path = "files/{$contenthash}";
        Storage::disk('local')->put($path, $content);
        return $path;
    }
}
