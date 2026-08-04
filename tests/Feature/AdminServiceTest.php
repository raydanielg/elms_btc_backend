<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Course;
use App\Models\MBadge;
use App\Models\MBadgeIssued;
use App\Models\MCompetencyFramework;
use App\Models\MCompetency;
use App\Models\MUserCompetency;
use App\Models\MWebserviceToken;
use App\Models\MConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class AdminServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $student;
    private string $adminToken;
    private string $studentToken;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'SUPER_ADMIN', 'status' => 'ACTIVE']);
        $this->student = User::factory()->create(['role' => 'STUDENT', 'status' => 'ACTIVE']);
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
        $this->studentToken = $this->student->createToken('test')->plainTextToken;
    }

    // ===== Badges =====

    public function test_create_badge(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/badges', [
                'name' => 'Course Completer',
                'description' => 'Awarded for completing a course',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Course Completer');
    }

    public function test_issue_badge(): void
    {
        $badge = MBadge::create(['name' => 'Test Badge', 'user_id' => $this->admin->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/badges/{$badge->id}/issue", [
                'user_id' => $this->student->id,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('m_badge_issued', [
            'badge_id' => $badge->id,
            'user_id' => $this->student->id,
        ]);
    }

    public function test_my_badges(): void
    {
        $badge = MBadge::create(['name' => 'My Badge', 'user_id' => $this->admin->id]);
        MBadgeIssued::create(['badge_id' => $badge->id, 'user_id' => $this->student->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
            ->getJson('/api/me/badges');

        $response->assertStatus(200);
    }

    // ===== Competencies =====

    public function test_create_competency_framework(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/competency-frameworks', [
                'name' => 'Teaching Skills',
                'description' => 'Core teaching competencies',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Teaching Skills');
    }

    public function test_create_competency(): void
    {
        $framework = MCompetencyFramework::create(['name' => 'Test Framework']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/competency-frameworks/{$framework->id}/competencies", [
                'shortname' => 'Lesson Planning',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.shortname', 'Lesson Planning');
    }

    public function test_rate_competency(): void
    {
        $framework = MCompetencyFramework::create(['name' => 'Test']);
        $competency = MCompetency::create(['framework_id' => $framework->id, 'shortname' => 'Test Comp']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->putJson("/api/competencies/{$competency->id}/rate", [
                'user_id' => $this->student->id,
                'rating' => 3,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.rating', 3)
            ->assertJsonPath('data.proficiency', 'PROFICIENT');
    }

    // ===== Web Services =====

    public function test_create_webservice_token(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
            ->postJson('/api/webservice/token', [
                'name' => 'Mobile App',
                'service' => 'mobile',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.service', 'mobile');

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_list_tokens(): void
    {
        MWebserviceToken::create([
            'user_id' => $this->student->id,
            'token' => 'testtoken123',
            'service' => 'mobile',
            'active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
            ->getJson('/api/webservice/tokens');

        $response->assertStatus(200);
    }

    public function test_revoke_token(): void
    {
        $token = MWebserviceToken::create([
            'user_id' => $this->student->id,
            'token' => 'revokemetoken',
            'service' => 'rest',
            'active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
            ->deleteJson("/api/webservice/tokens/{$token->id}");

        $response->assertStatus(200);
        $this->assertFalse($token->fresh()->active);
    }

    // ===== Admin Config =====

    public function test_admin_can_view_config(): void
    {
        MConfig::create(['name' => 'site_name', 'value' => 'Butimba LMS', 'type' => 'text']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/admin/config');

        $response->assertStatus(200);
    }

    public function test_admin_can_update_config(): void
    {
        $config = MConfig::create(['name' => 'max_upload_size', 'value' => '10485760', 'type' => 'number']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->putJson("/api/admin/config/max_upload_size", ['value' => '20971520']);

        $response->assertStatus(200);
        $this->assertEquals('20971520', $config->fresh()->value);
    }

    public function test_non_admin_cannot_view_config(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
            ->getJson('/api/admin/config');

        $response->assertStatus(403);
    }

    // ===== Backup/Restore =====

    public function test_create_backup(): void
    {
        $course = Course::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/courses/{$course->id}/backup");

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'COMPLETE');

        $this->assertDatabaseHas('m_backups', ['course_id' => $course->id]);
    }

    public function test_list_backups(): void
    {
        $course = Course::factory()->create();
        \App\Models\MBackup::create([
            'course_id' => $course->id, 'filename' => 'test.json',
            'filepath' => 'backups/test.json', 'filesize' => 100,
            'user_id' => $this->admin->id, 'status' => 'COMPLETE',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson("/api/courses/{$course->id}/backups");

        $response->assertStatus(200);
    }

    public function test_restore_backup(): void
    {
        $course = Course::factory()->create();
        $backupData = [
            'course' => $course->toArray(),
            'sections' => [],
        ];
        $filepath = 'backups/test_restore.json';
        Storage::disk('local')->put($filepath, json_encode($backupData));

        $backup = \App\Models\MBackup::create([
            'course_id' => $course->id, 'filename' => 'test_restore.json',
            'filepath' => $filepath, 'filesize' => 100,
            'user_id' => $this->admin->id, 'status' => 'COMPLETE',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/backups/{$backup->id}/restore");

        $response->assertStatus(200);
    }
}
