<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Course;
use App\Models\MGradeItem;
use App\Models\MGradeCategory;
use App\Models\MGradeGrade;
use App\Models\MCourseModule;
use App\Models\MCourseCompletionCriteria;
use App\Services\GradebookService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GradebookCompletionTest extends TestCase
{
    use RefreshDatabase;

    private User $tutor;
    private User $student;
    private string $tutorToken;
    private string $studentToken;
    private GradebookService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tutor = User::factory()->create(['role' => 'TUTOR', 'status' => 'ACTIVE']);
        $this->student = User::factory()->create(['role' => 'STUDENT', 'status' => 'ACTIVE']);
        $this->tutorToken = $this->tutor->createToken('test')->plainTextToken;
        $this->studentToken = $this->student->createToken('test')->plainTextToken;
        $this->service = app(GradebookService::class);
    }

    // ===== Gradebook =====

    public function test_create_grade_category(): void
    {
        $course = Course::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tutorToken)
            ->postJson("/api/courses/{$course->id}/grade-categories", [
                'fullname' => 'Assignments',
                'aggregation' => 'natural',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.fullname', 'Assignments');
    }

    public function test_create_grade_item(): void
    {
        $course = Course::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tutorToken)
            ->postJson("/api/courses/{$course->id}/grade-items", [
                'itemname' => 'Midterm Exam',
                'grademax' => 50,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.itemname', 'Midterm Exam')
            ->assertJsonPath('data.grademax', 50);
    }

    public function test_update_grade(): void
    {
        $course = Course::factory()->create();
        $item = $this->service->createGradeItem($course->id, 'manual', null, null, 'Test Item', 100);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tutorToken)
            ->putJson("/api/grade-items/{$item->id}/grades/{$this->student->id}", [
                'grade' => 85,
                'feedback' => 'Good work',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.rawgrade', 85);

        $this->assertDatabaseHas('m_grade_grades', [
            'itemid' => $item->id,
            'user_id' => $this->student->id,
            'rawgrade' => 85,
        ]);
    }

    public function test_grade_history_recorded(): void
    {
        $course = Course::factory()->create();
        $item = $this->service->createGradeItem($course->id, 'manual', null, null, 'Test', 100);

        $this->service->updateGrade($item->id, $this->student->id, 70);
        $this->service->updateGrade($item->id, $this->student->id, 85);

        $this->assertDatabaseHas('m_grade_grades_history', [
            'itemid' => $item->id,
            'user_id' => $this->student->id,
            'oldgrade' => 70,
            'newgrade' => 85,
        ]);
    }

    public function test_my_grades(): void
    {
        $course = Course::factory()->create();
        $item = $this->service->createGradeItem($course->id, 'manual', null, null, 'Test', 100);
        $this->service->updateGrade($item->id, $this->student->id, 75);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
            ->getJson("/api/courses/{$course->id}/my-grades");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.grade.rawgrade', 75)
            ->assertJsonPath('data.0.percentage', 75);
    }

    public function test_gradebook_view(): void
    {
        $course = Course::factory()->create();
        $this->service->createGradeItem($course->id, 'manual', null, null, 'Item 1', 100);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tutorToken)
            ->getJson("/api/courses/{$course->id}/gradebook");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_csv_export(): void
    {
        $course = Course::factory()->create();
        $item = $this->service->createGradeItem($course->id, 'manual', null, null, 'Export Test', 100);
        $this->service->updateGrade($item->id, $this->student->id, 90);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tutorToken)
            ->getJson("/api/courses/{$course->id}/grades/export");

        $response->assertStatus(200);
        $this->assertStringContainsString('Export Test', $response->getContent());
    }

    // ===== Aggregation =====

    public function test_aggregation_natural_sum(): void
    {
        $course = Course::factory()->create();
        $cat = MGradeCategory::create([
            'course_id' => $course->id, 'fullname' => 'Test Cat', 'aggregation' => 'natural',
        ]);
        $item1 = MGradeItem::create(['course_id' => $course->id, 'category_id' => $cat->id, 'itemtype' => 'manual', 'grademax' => 50, 'sortorder' => 1]);
        $item2 = MGradeItem::create(['course_id' => $course->id, 'category_id' => $cat->id, 'itemtype' => 'manual', 'grademax' => 50, 'sortorder' => 2]);

        $this->service->updateGrade($item1->id, $this->student->id, 40);
        $this->service->updateGrade($item2->id, $this->student->id, 35);

        $result = $this->service->aggregateCategory($cat->id, $this->student->id);
        $this->assertEquals(75, $result);
    }

    public function test_aggregation_mean(): void
    {
        $course = Course::factory()->create();
        $cat = MGradeCategory::create([
            'course_id' => $course->id, 'fullname' => 'Mean Cat', 'aggregation' => 'mean',
        ]);
        $item1 = MGradeItem::create(['course_id' => $course->id, 'category_id' => $cat->id, 'itemtype' => 'manual', 'grademax' => 100, 'sortorder' => 1]);
        $item2 = MGradeItem::create(['course_id' => $course->id, 'category_id' => $cat->id, 'itemtype' => 'manual', 'grademax' => 100, 'sortorder' => 2]);

        $this->service->updateGrade($item1->id, $this->student->id, 80);
        $this->service->updateGrade($item2->id, $this->student->id, 60);

        $result = $this->service->aggregateCategory($cat->id, $this->student->id);
        $this->assertEquals(70, $result);
    }

    // ===== Completion =====

    public function test_mark_module_complete(): void
    {
        $course = Course::factory()->create();
        $module = MCourseModule::create([
            'course_id' => $course->id, 'module_id' => \App\Models\MModule::create(['name' => 'assign'])->id,
            'completion' => 'MANUAL', 'groupmode' => 'NOGROUPS',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
            ->postJson("/api/modules/{$module->id}/complete");

        $response->assertStatus(200);
        $this->assertDatabaseHas('m_course_modules_completion', [
            'course_module_id' => $module->id,
            'user_id' => $this->student->id,
            'completionstate' => 'COMPLETE',
        ]);
    }

    public function test_completion_criteria_and_status(): void
    {
        $course = Course::factory()->create();
        $module = MCourseModule::create([
            'course_id' => $course->id, 'module_id' => \App\Models\MModule::create(['name' => 'page'])->id,
            'completion' => 'MANUAL', 'groupmode' => 'NOGROUPS',
        ]);

        // Set criteria
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tutorToken)
            ->postJson("/api/courses/{$course->id}/completion-criteria", [
                'criteria' => [
                    ['criteriatype' => 'activity', 'module_instance' => $module->id],
                ],
            ]);
        $response->assertStatus(200);

        // Check status before completion
        $statusResp = $this->withHeader('Authorization', 'Bearer ' . $this->tutorToken)
            ->getJson("/api/courses/{$course->id}/completion/{$this->student->id}");
        $statusResp->assertStatus(200)
            ->assertJsonPath('data.status', 'INPROGRESS');

        // Mark module complete
        $this->service->markModuleComplete($module->id, $this->student->id);

        // Check status after completion
        $statusResp2 = $this->withHeader('Authorization', 'Bearer ' . $this->tutorToken)
            ->getJson("/api/courses/{$course->id}/completion/{$this->student->id}");
        $statusResp2->assertJsonPath('data.status', 'COMPLETE');
    }
}
