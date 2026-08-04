<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\MFile;
use App\Services\FilesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;

class FilesApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private FilesService $filesService;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create(['role' => 'TUTOR', 'status' => 'ACTIVE']);
        $this->token = $this->user->createToken('test')->plainTextToken;
        $this->filesService = app(FilesService::class);
    }

    public function test_upload_draft_file(): void
    {
        $file = File::create('test.pdf', 100, 'application/pdf');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/files/draft', ['file' => $file]);

        $response->assertStatus(201)
            ->assertJsonPath('data.filename', 'test.pdf')
            ->assertJsonPath('data.filearea', 'draft');

        $this->assertDatabaseHas('m_files', [
            'filename' => 'test.pdf',
            'filearea' => 'draft',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_upload_rejects_unsupported_type(): void
    {
        $file = File::create('test.exe', 100, 'application/x-msdownload');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/files/draft', ['file' => $file]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'UPLOAD_FAILED');
    }

    public function test_dedup_by_content_hash(): void
    {
        $content = 'same content for both files';
        $file1 = File::create('file1.txt', strlen($content), 'text/plain')->setContent($content);
        $file2 = File::create('file2.txt', strlen($content), 'text/plain')->setContent($content);

        $r1 = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/files/draft', ['file' => $file1]);
        $r1->assertStatus(201);

        $r2 = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/files/draft', ['file' => $file2]);
        $r2->assertStatus(201);

        // Both should have the same contenthash
        $this->assertEquals($r1->json('data.contenthash'), $r2->json('data.contenthash'));
    }

    public function test_save_from_draft(): void
    {
        // Upload to draft
        $file = File::create('assignment.pdf', 100, 'application/pdf');
        $r1 = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/files/draft', ['file' => $file]);
        $r1->assertStatus(201);

        $draftItemId = $r1->json('data.itemid');

        // Save to permanent area
        $contextId = fake()->uuid();
        $cmId = fake()->uuid();

        $r2 = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/mod_assign/{$cmId}/files/save", [
                'draftitemid' => $draftItemId,
                'filearea' => 'submission_files',
                'context_id' => $contextId,
            ]);

        $r2->assertStatus(201);
        $this->assertDatabaseHas('m_files', [
            'component' => 'mod_assign',
            'filearea' => 'submission_files',
            'itemid' => $cmId,
            'filename' => 'assignment.pdf',
        ]);

        // Draft records should be cleaned up
        $this->assertDatabaseMissing('m_files', [
            'itemid' => $draftItemId,
            'filearea' => 'draft',
        ]);
    }

    public function test_generate_and_verify_signed_url(): void
    {
        $file = MFile::create([
            'contenthash' => 'abc123',
            'component' => 'test',
            'filearea' => 'test',
            'itemid' => fake()->uuid(),
            'filename' => 'test.txt',
            'filesize' => 100,
            'mimetype' => 'text/plain',
            'user_id' => $this->user->id,
            'pathnamehash' => 'hash123',
        ]);

        $url = $this->filesService->generateSignedUrl($file, $this->user->id, 30);
        $this->assertNotEmpty($url);

        // Verify the signature
        $expires = now()->addMinutes(30)->timestamp;
        $signature = hash_hmac('sha256', $file->id . '|' . $this->user->id . '|' . $expires, config('app.key'));
        $this->assertTrue($this->filesService->verifySignedUrl($file->id, $expires, $signature, $this->user->id));
    }

    public function test_signed_url_expires(): void
    {
        $fileId = fake()->uuid();
        $expires = now()->subMinutes(5)->timestamp;
        $signature = hash_hmac('sha256', $fileId . '|' . $this->user->id . '|' . $expires, config('app.key'));

        $this->assertFalse($this->filesService->verifySignedUrl($fileId, $expires, $signature, $this->user->id));
    }

    public function test_delete_file(): void
    {
        $file = MFile::create([
            'contenthash' => 'delete123',
            'component' => 'test',
            'filearea' => 'test',
            'itemid' => fake()->uuid(),
            'filename' => 'delete.txt',
            'filesize' => 50,
            'mimetype' => 'text/plain',
            'user_id' => $this->user->id,
            'pathnamehash' => 'deletehash',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/files/{$file->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('m_files', ['id' => $file->id]);
    }

    public function test_list_files_in_area(): void
    {
        $contextId = fake()->uuid();
        MFile::create([
            'contenthash' => 'hash1', 'context_id' => $contextId,
            'component' => 'mod_assign', 'filearea' => 'submission_files',
            'itemid' => fake()->uuid(), 'filename' => 'file1.pdf',
            'filesize' => 100, 'mimetype' => 'application/pdf',
            'user_id' => $this->user->id, 'pathnamehash' => 'ph1',
        ]);
        MFile::create([
            'contenthash' => 'hash2', 'context_id' => $contextId,
            'component' => 'mod_assign', 'filearea' => 'submission_files',
            'itemid' => fake()->uuid(), 'filename' => 'file2.pdf',
            'filesize' => 200, 'mimetype' => 'application/pdf',
            'user_id' => $this->user->id, 'pathnamehash' => 'ph2',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/files/area/{$contextId}/mod_assign/submission_files");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }
}
