<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Course;
use App\Models\Program;
use App\Models\MCertificateTemplate;
use App\Models\MCertificatePage;
use App\Models\MCertificateIssue;
use App\Models\MAward;
use App\Models\MCohort;
use App\Models\MCohortMember;
use App\Services\CertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CertificateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $student;
    private string $adminToken;
    private string $studentToken;
    private CertificateService $certService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'SUPER_ADMIN', 'status' => 'ACTIVE']);
        $this->student = User::factory()->create(['role' => 'STUDENT', 'status' => 'ACTIVE']);
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
        $this->studentToken = $this->student->createToken('test')->plainTextToken;
        $this->certService = app(CertificateService::class);
    }

    // ===== Templates =====

    public function test_create_certificate_template(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/certificate-templates', [
                'name' => 'Diploma Completion 2026',
                'type' => 'COMPLETION',
                'trigger_type' => 'COURSE_COMPLETION',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Diploma Completion 2026')
            ->assertJsonPath('data.pages.0.page_no', 1);
    }

    public function test_list_templates(): void
    {
        MCertificateTemplate::create(['name' => 'T1', 'type' => 'COMPLETION']);
        MCertificateTemplate::create(['name' => 'T2', 'type' => 'AWARD']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/certificate-templates');

        $response->assertStatus(200);
    }

    public function test_show_template_with_pages_and_elements(): void
    {
        $template = MCertificateTemplate::create(['name' => 'Test', 'type' => 'COMPLETION']);
        $page = MCertificatePage::create(['template_id' => $template->id, 'page_no' => 1]);
        \App\Models\MCertificateElement::create([
            'page_id' => $page->id, 'element_type' => 'recipient_name',
            'pos_x' => 100, 'pos_y' => 200, 'font_size' => 24,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson("/api/certificate-templates/{$template->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.pages.0.elements.0.element_type', 'recipient_name');
    }

    public function test_duplicate_template(): void
    {
        $template = MCertificateTemplate::create(['name' => 'Original', 'type' => 'AWARD']);
        $page = MCertificatePage::create(['template_id' => $template->id, 'page_no' => 1]);
        \App\Models\MCertificateElement::create([
            'page_id' => $page->id, 'element_type' => 'static_text',
            'data' => ['text' => 'Hello'],
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/certificate-templates/{$template->id}/duplicate");

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Original (Copy)')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_add_element_to_page(): void
    {
        $template = MCertificateTemplate::create(['name' => 'Test', 'type' => 'COMPLETION']);
        $page = MCertificatePage::create(['template_id' => $template->id, 'page_no' => 1]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/pages/{$page->id}/elements", [
                'element_type' => 'qr_code',
                'pos_x' => 400,
                'pos_y' => 500,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.element_type', 'qr_code');
    }

    // ===== Issuing =====

    public function test_issue_certificate(): void
    {
        $template = MCertificateTemplate::create([
            'name' => 'Test Cert', 'type' => 'COMPLETION', 'trigger_type' => 'MANUAL',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/certificates/issue', [
                'template_id' => $template->id,
                'user_id' => $this->student->id,
                'grade' => 'Distinction',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'ISSUED')
            ->assertJsonPath('data.grade', 'Distinction');

        $this->assertNotEmpty($response->json('data.code'));
        $this->assertNotEmpty($response->json('data.hash'));

        // Notification should be created
        $this->assertDatabaseHas('m_notifications', [
            'user_id' => $this->student->id,
            'event_type' => 'certificate_issued',
        ]);
    }

    public function test_duplicate_issue_blocked(): void
    {
        $template = MCertificateTemplate::create(['name' => 'Test', 'type' => 'COMPLETION']);
        $this->certService->issue($template->id, $this->student->id, $this->admin->id);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/certificates/issue', [
                'template_id' => $template->id,
                'user_id' => $this->student->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'NOT_ELIGIBLE');
    }

    public function test_revoke_certificate(): void
    {
        $template = MCertificateTemplate::create(['name' => 'Test', 'type' => 'COMPLETION']);
        $issue = $this->certService->issue($template->id, $this->student->id, $this->admin->id);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->patchJson("/api/certificates/{$issue->id}/revoke", [
                'reason' => 'Academic misconduct',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'REVOKED');

        $this->assertDatabaseHas('m_certificate_issues', [
            'id' => $issue->id,
            'status' => 'REVOKED',
            'revoke_reason' => 'Academic misconduct',
        ]);
    }

    public function test_reissue_certificate(): void
    {
        $template = MCertificateTemplate::create(['name' => 'Test', 'type' => 'COMPLETION']);
        $issue = $this->certService->issue($template->id, $this->student->id, $this->admin->id);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/certificates/{$issue->id}/reissue");

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'ISSUED');

        // Old issue should be marked REISSUED
        $this->assertEquals('REISSUED', $issue->fresh()->status);
    }

    // ===== Verification =====

    public function test_public_verify_valid(): void
    {
        $template = MCertificateTemplate::create(['name' => 'Test Cert', 'type' => 'COMPLETION']);
        $issue = $this->certService->issue($template->id, $this->student->id, $this->admin->id);

        $response = $this->getJson("/api/verify/{$issue->code}");

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.status', 'VALID')
            ->assertJsonPath('data.name', $this->student->full_name);
    }

    public function test_public_verify_revoked(): void
    {
        $template = MCertificateTemplate::create(['name' => 'Test', 'type' => 'COMPLETION']);
        $issue = $this->certService->issue($template->id, $this->student->id, $this->admin->id);
        $this->certService->revoke($issue->id, 'Test revoke');

        $response = $this->getJson("/api/verify/{$issue->code}");

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.status', 'REVOKED');
    }

    public function test_public_verify_not_found(): void
    {
        $response = $this->getJson('/api/verify/INVALIDCODE123');

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.status', 'NOT_FOUND');
    }

    // ===== My Certificates =====

    public function test_my_certificates(): void
    {
        $template = MCertificateTemplate::create(['name' => 'Test', 'type' => 'COMPLETION']);
        $this->certService->issue($template->id, $this->student->id, $this->admin->id);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
            ->getJson('/api/me/certificates');

        $response->assertStatus(200);
    }

    // ===== Eligibility =====

    public function test_fees_cleared_gate(): void
    {
        $template = MCertificateTemplate::create([
            'name' => 'Test', 'type' => 'COMPLETION',
            'requires_fees_cleared' => true,
        ]);

        // Create debt
        \DB::table('debts')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'student_id' => $this->student->id,
            'description' => 'Tuition fee',
            'amount_due' => 50000,
            'amount_paid' => 0,
            'balance' => 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/certificates/issue', [
                'template_id' => $template->id,
                'user_id' => $this->student->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'NOT_ELIGIBLE');
    }

    // ===== Awards =====

    public function test_create_award(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/awards', [
                'name' => 'Best Student Award',
                'description' => 'Awarded to the top-performing student',
                'criteria' => ['rule' => 'top_1_by_grade'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Best Student Award');
    }

    // ===== Transcript =====

    public function test_generate_transcript(): void
    {
        $course = Course::factory()->create();
        \DB::table('results')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'student_id' => $this->student->id,
            'course_id' => $course->id,
            'ca_marks' => 40,
            'exam_marks' => 50,
            'total' => 90,
            'grade' => 'A',
            'academic_year' => '2025/2026',
            'semester' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/transcripts/generate', [
                'student_id' => $this->student->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.student.name', $this->student->full_name)
            ->assertJsonPath('data.courses.0.total', 90)
            ->assertJsonPath('data.average', 90);
    }
}
