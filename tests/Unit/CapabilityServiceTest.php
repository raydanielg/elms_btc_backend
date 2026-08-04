<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\MContext;
use App\Models\MRole;
use App\Models\MCapability;
use App\Models\MRoleCapability;
use App\Models\MRoleAssignment;
use App\Models\User;
use App\Services\CapabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CapabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private CapabilityService $service;
    private MContext $systemContext;
    private MContext $courseContext;
    private MContext $moduleContext;
    private MRole $managerRole;
    private MRole $studentRole;
    private MRole $teacherRole;
    private MCapability $viewCourseCap;
    private MCapability $manageActivitiesCap;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CapabilityService::class);

        // Create contexts with hierarchy
        $this->systemContext = MContext::create([
            'contextlevel' => MContext::LEVEL_SYSTEM,
            'instanceid' => null,
            'path' => '',
            'depth' => 1,
        ]);
        $this->systemContext->path = '/' . $this->systemContext->id;
        $this->systemContext->save();

        $this->courseContext = MContext::create([
            'contextlevel' => MContext::LEVEL_COURSE,
            'instanceid' => fake()->uuid(),
            'path' => $this->systemContext->path . '/',
            'depth' => 2,
        ]);
        $this->courseContext->path = $this->systemContext->path . '/' . $this->courseContext->id;
        $this->courseContext->save();

        $this->moduleContext = MContext::create([
            'contextlevel' => MContext::LEVEL_MODULE,
            'instanceid' => fake()->uuid(),
            'path' => $this->courseContext->path . '/',
            'depth' => 3,
        ]);
        $this->moduleContext->path = $this->courseContext->path . '/' . $this->moduleContext->id;
        $this->moduleContext->save();

        // Create roles
        $this->managerRole = MRole::create(['name' => 'Manager', 'shortname' => 'manager', 'sortorder' => 1]);
        $this->studentRole = MRole::create(['name' => 'Student', 'shortname' => 'student', 'sortorder' => 5]);
        $this->teacherRole = MRole::create(['name' => 'Editing Teacher', 'shortname' => 'editingteacher', 'sortorder' => 3]);

        // Create capabilities
        $this->viewCourseCap = MCapability::create([
            'name' => 'moodle/course:view', 'contextlevel' => MContext::LEVEL_COURSE, 'riskbitmask' => 0,
        ]);
        $this->manageActivitiesCap = MCapability::create([
            'name' => 'moodle/course:manageactivities', 'contextlevel' => MContext::LEVEL_COURSE, 'riskbitmask' => 1,
        ]);

        // Create test user
        $this->user = User::factory()->create();
    }

    public function test_allow_permission_grants_access(): void
    {
        // Assign manager role at system context
        MRoleAssignment::create([
            'role_id' => $this->managerRole->id,
            'context_id' => $this->systemContext->id,
            'user_id' => $this->user->id,
        ]);

        // Allow view course at system context
        MRoleCapability::create([
            'role_id' => $this->managerRole->id,
            'capability_id' => $this->viewCourseCap->id,
            'context_id' => $this->systemContext->id,
            'permission' => MCapability::ALLOW,
        ]);

        $this->assertTrue($this->service->hasCapability($this->user->id, 'moodle/course:view', $this->courseContext));
    }

    public function test_inherit_permission_denies_access(): void
    {
        MRoleAssignment::create([
            'role_id' => $this->studentRole->id,
            'context_id' => $this->systemContext->id,
            'user_id' => $this->user->id,
        ]);

        // INHERIT means no explicit permission → should deny
        MRoleCapability::create([
            'role_id' => $this->studentRole->id,
            'capability_id' => $this->manageActivitiesCap->id,
            'context_id' => $this->systemContext->id,
            'permission' => MCapability::INHERIT,
        ]);

        $this->assertFalse($this->service->hasCapability($this->user->id, 'moodle/course:manageactivities', $this->courseContext));
    }

    public function test_prevent_permission_denies_access(): void
    {
        MRoleAssignment::create([
            'role_id' => $this->teacherRole->id,
            'context_id' => $this->systemContext->id,
            'user_id' => $this->user->id,
        ]);

        // PREVENT at course context
        MRoleCapability::create([
            'role_id' => $this->teacherRole->id,
            'capability_id' => $this->manageActivitiesCap->id,
            'context_id' => $this->courseContext->id,
            'permission' => MCapability::PREVENT,
        ]);

        $this->assertFalse($this->service->hasCapability($this->user->id, 'moodle/course:manageactivities', $this->courseContext));
    }

    public function test_prohibit_always_wins(): void
    {
        // Assign both manager and student roles
        MRoleAssignment::create([
            'role_id' => $this->managerRole->id,
            'context_id' => $this->systemContext->id,
            'user_id' => $this->user->id,
        ]);
        MRoleAssignment::create([
            'role_id' => $this->studentRole->id,
            'context_id' => $this->courseContext->id,
            'user_id' => $this->user->id,
        ]);

        // Manager ALLOW at system context
        MRoleCapability::create([
            'role_id' => $this->managerRole->id,
            'capability_id' => $this->manageActivitiesCap->id,
            'context_id' => $this->systemContext->id,
            'permission' => MCapability::ALLOW,
        ]);

        // Student PROHIBIT at course context — must override ALLOW
        MRoleCapability::create([
            'role_id' => $this->studentRole->id,
            'capability_id' => $this->manageActivitiesCap->id,
            'context_id' => $this->courseContext->id,
            'permission' => MCapability::PROHIBIT,
        ]);

        $this->assertFalse($this->service->hasCapability($this->user->id, 'moodle/course:manageactivities', $this->courseContext));
    }

    public function test_nearer_context_wins(): void
    {
        MRoleAssignment::create([
            'role_id' => $this->teacherRole->id,
            'context_id' => $this->systemContext->id,
            'user_id' => $this->user->id,
        ]);

        // ALLOW at system context
        MRoleCapability::create([
            'role_id' => $this->teacherRole->id,
            'capability_id' => $this->manageActivitiesCap->id,
            'context_id' => $this->systemContext->id,
            'permission' => MCapability::ALLOW,
        ]);

        // PREVENT at course context — nearer context should win
        MRoleCapability::create([
            'role_id' => $this->teacherRole->id,
            'capability_id' => $this->manageActivitiesCap->id,
            'context_id' => $this->courseContext->id,
            'permission' => MCapability::PREVENT,
        ]);

        $this->assertFalse($this->service->hasCapability($this->user->id, 'moodle/course:manageactivities', $this->courseContext));
    }

    public function test_context_cascading_from_system_to_course(): void
    {
        // Assign student role at system context
        MRoleAssignment::create([
            'role_id' => $this->studentRole->id,
            'context_id' => $this->systemContext->id,
            'user_id' => $this->user->id,
        ]);

        // ALLOW view course at system context — should cascade to course context
        MRoleCapability::create([
            'role_id' => $this->studentRole->id,
            'capability_id' => $this->viewCourseCap->id,
            'context_id' => $this->systemContext->id,
            'permission' => MCapability::ALLOW,
        ]);

        $this->assertTrue($this->service->hasCapability($this->user->id, 'moodle/course:view', $this->courseContext));
        $this->assertTrue($this->service->hasCapability($this->user->id, 'moodle/course:view', $this->moduleContext));
    }

    public function test_no_role_assignment_denies_access(): void
    {
        $this->assertFalse($this->service->hasCapability($this->user->id, 'moodle/course:view', $this->courseContext));
    }

    public function test_nonexistent_capability_denies_access(): void
    {
        MRoleAssignment::create([
            'role_id' => $this->managerRole->id,
            'context_id' => $this->systemContext->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertFalse($this->service->hasCapability($this->user->id, 'nonexistent/capability', $this->courseContext));
    }

    public function test_assign_and_unassign_role(): void
    {
        $assignment = $this->service->assignRole(
            $this->user->id, $this->studentRole->id, $this->courseContext->id
        );

        $this->assertNotNull($assignment);
        $this->assertEquals($this->user->id, $assignment->user_id);

        $removed = $this->service->unassignRole(
            $this->user->id, $this->studentRole->id, $this->courseContext->id
        );

        $this->assertTrue($removed);
        $this->assertFalse(MRoleAssignment::where('user_id', $this->user->id)->exists());
    }

    public function test_get_or_create_system_context(): void
    {
        $ctx = $this->service->getSystemContext();
        $this->assertEquals(MContext::LEVEL_SYSTEM, $ctx->contextlevel);
        $this->assertNotEmpty($ctx->path);
    }
}
