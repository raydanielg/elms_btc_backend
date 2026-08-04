<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Course;
use App\Models\MCourseCategory;
use App\Models\MCourseSection;
use App\Models\MModule;
use App\Models\MCourseModule;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CourseStructureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'SUPER_ADMIN', 'status' => 'ACTIVE']);
        $this->token = $this->admin->createToken('test')->plainTextToken;
    }

    // ===== Categories =====

    public function test_list_categories(): void
    {
        MCourseCategory::create([
            'name' => 'Diploma Programs', 'idnumber' => 'DIP',
            'path' => '/1', 'depth' => 1, 'visible' => true, 'sortorder' => 1,
        ]);

        $response = $this->getJson('/api/categories');
        $response->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_create_category(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/categories', [
                'name' => 'Diploma Programs',
                'idnumber' => 'DIP',
                'description' => 'All diploma programs',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Diploma Programs')
            ->assertJsonPath('data.depth', 1);

        $this->assertNotEmpty($response->json('data.path'));
    }

    public function test_create_subcategory(): void
    {
        $parent = MCourseCategory::create([
            'name' => 'Parent', 'path' => '/1', 'depth' => 1, 'sortorder' => 1,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/categories', [
                'name' => 'Child',
                'parent_id' => $parent->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.depth', 2);
    }

    public function test_delete_category_with_children_fails(): void
    {
        $parent = MCourseCategory::create([
            'name' => 'Parent', 'path' => '/1', 'depth' => 1, 'sortorder' => 1,
        ]);
        MCourseCategory::create([
            'name' => 'Child', 'parent_id' => $parent->id,
            'path' => $parent->path . '/2', 'depth' => 2, 'sortorder' => 1,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/categories/' . $parent->id);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'HAS_CHILDREN');
    }

    // ===== Sections =====

    public function test_create_section(): void
    {
        $course = Course::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/courses/{$course->id}/sections", [
                'name' => 'Topic 1',
                'summary' => 'Introduction to the course',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.section', 1)
            ->assertJsonPath('data.name', 'Topic 1');
    }

    public function test_update_section(): void
    {
        $course = Course::factory()->create();
        $section = MCourseSection::create([
            'course_id' => $course->id, 'section' => 1, 'name' => 'Old Name',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson("/api/courses/{$course->id}/sections/1", [
                'name' => 'New Name',
                'visible' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.visible', false);
    }

    public function test_move_section(): void
    {
        $course = Course::factory()->create();
        $s1 = MCourseSection::create(['course_id' => $course->id, 'section' => 1, 'name' => 'A']);
        $s2 = MCourseSection::create(['course_id' => $course->id, 'section' => 2, 'name' => 'B']);
        $s3 = MCourseSection::create(['course_id' => $course->id, 'section' => 3, 'name' => 'C']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/courses/{$course->id}/sections/1/move", ['new_position' => 3]);

        $response->assertStatus(200);
        $this->assertEquals(3, $s1->fresh()->section);
    }

    // ===== Course Modules =====

    public function test_list_modules(): void
    {
        MModule::create(['name' => 'assign', 'active' => true]);
        MModule::create(['name' => 'quiz', 'active' => true]);

        $response = $this->getJson('/api/modules');
        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_add_module_to_section(): void
    {
        $course = Course::factory()->create();
        $section = MCourseSection::create(['course_id' => $course->id, 'section' => 0, 'name' => 'General']);
        $module = MModule::create(['name' => 'assign', 'active' => true]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/courses/{$course->id}/modules", [
                'module_id' => $module->id,
                'section_id' => $section->id,
                'visible' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.module_id', $module->id);

        // Verify module was added to section sequence
        $section->refresh();
        $this->assertStringContainsString($response->json('data.id'), $section->sequence);
    }

    public function test_update_module_visibility(): void
    {
        $course = Course::factory()->create();
        $module = MModule::create(['name' => 'quiz', 'active' => true]);
        $cm = MCourseModule::create([
            'course_id' => $course->id, 'module_id' => $module->id,
            'visible' => true, 'completion' => 'NONE', 'groupmode' => 'NOGROUPS',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson("/api/modules/{$cm->id}", ['visible' => false]);

        $response->assertStatus(200)
            ->assertJsonPath('data.visible', false);
    }

    public function test_delete_module(): void
    {
        $course = Course::factory()->create();
        $section = MCourseSection::create(['course_id' => $course->id, 'section' => 0, 'name' => 'General']);
        $module = MModule::create(['name' => 'forum', 'active' => true]);
        $cm = MCourseModule::create([
            'course_id' => $course->id, 'module_id' => $module->id, 'section_id' => $section->id,
        ]);
        $section->update(['sequence' => $cm->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/modules/{$cm->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('m_course_modules', ['id' => $cm->id]);
        $section->refresh();
        $this->assertStringNotContainsString($cm->id, $section->sequence ?? '');
    }

    public function test_course_contents(): void
    {
        $course = Course::factory()->create();
        $section = MCourseSection::create(['course_id' => $course->id, 'section' => 0, 'name' => 'General']);
        $module = MModule::create(['name' => 'assign', 'active' => true]);
        MCourseModule::create([
            'course_id' => $course->id, 'module_id' => $module->id, 'section_id' => $section->id,
        ]);

        $response = $this->getJson("/api/courses/{$course->id}/contents");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $course->id)
            ->assertJsonPath('data.sections.0.name', 'General');
    }

    public function test_reset_course(): void
    {
        $course = Course::factory()->create();
        \App\Models\Enrollment::create([
            'student_id' => User::factory()->create()->id,
            'course_id' => $course->id,
            'status' => 'ACTIVE',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/courses/{$course->id}/reset");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('enrollments', ['course_id' => $course->id]);
    }
}
