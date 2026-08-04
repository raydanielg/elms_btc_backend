<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Course;
use App\Models\MEnrol;
use App\Models\MUserEnrolment;
use App\Models\MGroup;
use App\Models\MGroupMember;
use App\Models\MCohort;
use App\Models\MCohortMember;
use App\Models\Payment;
use App\Services\EnrolmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EnrolmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $student;
    private string $token;
    private EnrolmentService $enrolService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'SUPER_ADMIN', 'status' => 'ACTIVE']);
        $this->student = User::factory()->create(['role' => 'STUDENT', 'status' => 'ACTIVE']);
        $this->token = $this->admin->createToken('test')->plainTextToken;
        $this->enrolService = app(EnrolmentService::class);
    }

    // ===== Enrolment Methods =====

    public function test_create_enrolment_method(): void
    {
        $course = Course::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/courses/{$course->id}/enrolment-methods", [
                'enrol' => 'manual',
                'status' => 'ENABLED',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.enrol', 'manual');
    }

    public function test_manual_enrol(): void
    {
        $course = Course::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/enrol/manual', [
                'course_id' => $course->id,
                'user_id' => $this->student->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'ACTIVE');

        $this->assertDatabaseHas('m_user_enrolments', [
            'user_id' => $this->student->id,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_self_enrol_with_key(): void
    {
        $course = Course::factory()->create();
        MEnrol::create([
            'course_id' => $course->id,
            'enrol' => 'self',
            'status' => 'ENABLED',
            'customchar1' => 'secret123',
        ]);

        $token = $this->student->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/enrol/self', [
                'course_id' => $course->id,
                'enrolkey' => 'secret123',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'ACTIVE');
    }

    public function test_self_enrol_wrong_key(): void
    {
        $course = Course::factory()->create();
        MEnrol::create([
            'course_id' => $course->id,
            'enrol' => 'self',
            'status' => 'ENABLED',
            'customchar1' => 'secret123',
        ]);

        $token = $this->student->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/enrol/self', [
                'course_id' => $course->id,
                'enrolkey' => 'wrongkey',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'ENROLMENT_FAILED');
    }

    public function test_suspend_enrolment(): void
    {
        $course = Course::factory()->create();
        $enrolment = $this->enrolService->manualEnrol($course->id, $this->student->id);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/enrol/{$enrolment->enrol_id}/suspend/{$this->student->id}");

        $response->assertStatus(200);
        $this->assertEquals('SUSPENDED', $enrolment->fresh()->status);
    }

    public function test_enrolled_users_list(): void
    {
        $course = Course::factory()->create();
        $this->enrolService->manualEnrol($course->id, $this->student->id);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/courses/{$course->id}/enrolled-users");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // ===== Payment Enrolment =====

    public function test_payment_enrol_without_payment_is_suspended(): void
    {
        $course = Course::factory()->create();

        $enrolment = $this->enrolService->paymentEnrol($course->id, $this->student->id);

        $this->assertEquals('SUSPENDED', $enrolment->status);
    }

    public function test_payment_enrol_with_payment_is_active(): void
    {
        $course = Course::factory()->create();
        Payment::create([
            'student_id' => $this->student->id,
            'fee_item' => 'Registration',
            'amount' => 50000,
            'status' => 'CONFIRMED',
            'control_number' => 'CTRL123',
        ]);

        $enrolment = $this->enrolService->paymentEnrol($course->id, $this->student->id);

        $this->assertEquals('ACTIVE', $enrolment->status);
    }

    public function test_payment_confirmed_activates_enrolment(): void
    {
        $course = Course::factory()->create();
        $enrolment = $this->enrolService->paymentEnrol($course->id, $this->student->id);
        $this->assertEquals('SUSPENDED', $enrolment->status);

        // Simulate payment confirmation
        Payment::create([
            'student_id' => $this->student->id,
            'fee_item' => 'Registration',
            'amount' => 50000,
            'status' => 'CONFIRMED',
            'control_number' => 'CTRL456',
        ]);

        $this->enrolService->onPaymentConfirmed($this->student->id, 'Registration');

        $this->assertEquals('ACTIVE', $enrolment->fresh()->status);
    }

    public function test_payment_confirmed_upgrades_applicant_to_student(): void
    {
        $applicant = User::factory()->create(['role' => 'APPLICANT', 'status' => 'ACTIVE']);

        $this->enrolService->onPaymentConfirmed($applicant->id, 'Registration');

        $this->assertEquals('STUDENT', $applicant->fresh()->role);
    }

    // ===== Groups =====

    public function test_create_group(): void
    {
        $course = Course::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/courses/{$course->id}/groups", [
                'name' => 'Group A',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Group A');
    }

    public function test_add_group_member(): void
    {
        $course = Course::factory()->create();
        $group = MGroup::create(['course_id' => $course->id, 'name' => 'Group A']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/groups/{$group->id}/members", [
                'user_id' => $this->student->id,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('m_groups_members', [
            'group_id' => $group->id,
            'user_id' => $this->student->id,
        ]);
    }

    public function test_auto_create_groups(): void
    {
        $course = Course::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/courses/{$course->id}/groups/auto", [
                'count' => 3,
            ]);

        $response->assertStatus(201);
        $this->assertEquals(3, MGroup::where('course_id', $course->id)->count());
    }

    // ===== Cohorts =====

    public function test_create_cohort(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/cohorts', [
                'name' => '2026 Intake',
                'idnumber' => 'INTAKE-2026',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', '2026 Intake');
    }

    public function test_add_cohort_member(): void
    {
        $cohort = MCohort::create(['name' => 'Test Cohort']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/cohorts/{$cohort->id}/members", [
                'user_id' => $this->student->id,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('m_cohort_members', [
            'cohort_id' => $cohort->id,
            'user_id' => $this->student->id,
        ]);
    }

    public function test_cohort_sync_to_course(): void
    {
        $course = Course::factory()->create();
        $cohort = MCohort::create(['name' => 'Test Cohort']);
        MCohortMember::create(['cohort_id' => $cohort->id, 'user_id' => $this->student->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/cohorts/{$cohort->id}/sync-to-course", [
                'course_id' => $course->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.enrolled_count', 1);

        $this->assertTrue($this->enrolService->isEnrolled($this->student->id, $course->id));
    }

    public function test_is_enrolled(): void
    {
        $course = Course::factory()->create();
        $this->enrolService->manualEnrol($course->id, $this->student->id);

        $this->assertTrue($this->enrolService->isEnrolled($this->student->id, $course->id));
        $this->assertFalse($this->enrolService->isEnrolled($this->admin->id, $course->id));
    }
}
