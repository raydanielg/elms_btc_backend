<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MFile;
use App\Services\FilesService;
use Illuminate\Support\Facades\Storage;

class FilesController extends Controller
{
    public function __construct(
        private FilesService $filesService
    ) {}

    /** POST /files/draft — upload to draft area (spec §18.3) */
    public function uploadDraft(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:102400', // 100MB
        ]);

        try {
            $file = $this->filesService->uploadDraft($request->file('file'), $request->user()->id);
            return $this->success($file, 201);
        } catch (\Exception $e) {
            return $this->error('UPLOAD_FAILED', $e->getMessage(), 400);
        }
    }

    /** POST /:component/:cmid/files/save — save draft to permanent area (spec §18.3) */
    public function saveFromDraft(Request $request, string $component, string $cmid)
    {
        $data = $request->validate([
            'draftitemid' => 'required|uuid',
            'filearea' => 'required|string',
            'context_id' => 'required|uuid',
        ]);

        $saved = $this->filesService->saveFromDraft(
            $data['draftitemid'],
            $data['context_id'],
            $component,
            $data['filearea'],
            $cmid,
            $request->user()->id
        );

        return $this->success($saved, 201);
    }

    /** GET /files/:fileId/download — signed, access-checked download (spec §18.3) */
    public function download(Request $request, string $fileId)
    {
        $file = MFile::findOrFail($fileId);

        // Verify signed URL
        $expires = (int) $request->get('expires', 0);
        $signature = $request->get('signature', '');
        $userId = $request->user() ? $request->user()->id : '';

        if (!$this->filesService->verifySignedUrl($fileId, $expires, $signature, $userId)) {
            return $this->error('ACCESS_DENIED', 'Invalid or expired download link.', 403);
        }

        // Get file content
        $path = "files/{$file->contenthash}";
        if (!Storage::disk('local')->exists($path)) {
            return $this->error('FILE_NOT_FOUND', 'File content not found.', 404);
        }

        return response()->streamDownload(function () use ($path) {
            echo Storage::disk('local')->get($path);
        }, $file->filename, [
            'Content-Type' => $file->mimetype ?? 'application/octet-stream',
            'Content-Length' => $file->filesize,
        ]);
    }

    /** GET /files/:contextId/:component/:filearea/:itemid/:filename — get file info */
    public function getFile(Request $request, string $contextId, string $component, string $filearea, string $itemId, string $filename)
    {
        $file = $this->filesService->getFile($contextId, $component, $filearea, $itemId, $filename);

        if (!$file) {
            return $this->error('FILE_NOT_FOUND', 'File not found.', 404);
        }

        // Generate signed URL for download
        $url = $this->filesService->generateSignedUrl($file, $request->user()->id);

        return $this->success([
            'file' => $file,
            'download_url' => $url,
        ]);
    }

    /** GET /files/area/:contextId/:component/:filearea/:itemid — list files in an area */
    public function listFiles(Request $request, string $contextId, string $component, string $filearea, ?string $itemId = null)
    {
        $files = $this->filesService->getFiles($contextId, $component, $filearea, $itemId);
        return $this->success($files);
    }

    /** DELETE /files/:fileId — delete a file */
    public function deleteFile(string $fileId)
    {
        $deleted = $this->filesService->deleteFile($fileId);
        return $this->success(['deleted' => $deleted]);
    }

    /** GET /repositories — list file repositories (spec §18.2) */
    public function indexRepositories()
    {
        $repos = \App\Models\MRepository::where('active', true)->orderBy('sortorder')->get();
        return $this->success($repos);
    }
}
