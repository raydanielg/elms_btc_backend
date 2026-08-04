<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MEnrol;
use App\Models\MUserEnrolment;
use App\Models\MGroup;
use App\Models\MGroupMember;
use App\Models\MGrouping;
use App\Models\MCohort;
use App\Models\MCohortMember;
use App\Services\EnrolmentService;

class EnrolmentController extends Controller
{
    public function __construct(
        private EnrolmentService $enrolService
    ) {}

    // ===== Enrolment Methods (spec §6) =====

    /** GET /courses/:id/enrolment-methods */
    public function indexMethods(string $courseId)
    {
        $methods = MEnrol::where('course_id', $courseId)->orderBy('sortorder')->get();
        return $this->success($methods);
    }

    /** POST /courses/:id/enrolment-methods — add an enrolment method to a course */
    public function createMethod(Request $request, string $courseId)
    {
        $data = $request->validate([
            'enrol' => 'required|string|in:manual,self,cohort,payment,guest',
            'status' => 'sometimes|string|in:ENABLED,DISABLED',
            'role_id' => 'nullable|uuid|exists:m_roles,id',
            'enrolstartdate' => 'nullable|date',
            'enrolenddate' => 'nullable|date',
            'enrolperiod' => 'nullable|integer',
            'customint1' => 'nullable|string',
            'customchar1' => 'nullable|string',
            'customchar2' => 'nullable|string',
        ]);

        $data['course_id'] = $courseId;
        $method = MEnrol::create($data);
        return $this->success($method, 201);
    }

    /** POST /enrol/manual — manually enrol a user */
    public function manualEnrol(Request $request)
    {
        $data = $request->validate([
            'course_id' => 'required|uuid|exists:courses,id',
            'user_id' => 'required|uuid|exists:users,id',
            'role_id' => 'nullable|uuid|exists:m_roles,id',
            'timeend' => 'nullable|date',
        ]);

        $enrolment = $this->enrolService->manualEnrol(
            $data['course_id'], $data['user_id'], $data['role_id'] ?? null, $data['timeend'] ?? null
        );

        return $this->success($enrolment->load(['user', 'enrol']), 201);
    }

    /** POST /enrol/self — self-enrolment with key */
    public function selfEnrol(Request $request)
    {
        $data = $request->validate([
            'course_id' => 'required|uuid|exists:courses,id',
            'enrolkey' => 'nullable|string',
        ]);

        try {
            $enrolment = $this->enrolService->selfEnrol(
                $data['course_id'], $request->user()->id, $data['enrolkey'] ?? null
            );
            return $this->success($enrolment->load(['user', 'enrol']), 201);
        } catch (\Exception $e) {
            return $this->error('ENROLMENT_FAILED', $e->getMessage(), 400);
        }
    }

    /** POST /enrol/:id/suspend/:userid — suspend a user's enrolment */
    public function suspendEnrolment(string $enrolId, string $userId)
    {
        $this->enrolService->suspendEnrolment($enrolId, $userId);
        return $this->success(null);
    }

    /** GET /courses/:id/enrolled-users — list enrolled users */
    public function enrolledUsers(string $courseId)
    {
        $users = $this->enrolService->getEnrolledUsers($courseId);
        return $this->success($users);
    }

    // ===== Groups (spec §7) =====

    /** GET /courses/:id/groups */
    public function indexGroups(string $courseId)
    {
        $groups = MGroup::where('course_id', $courseId)->withCount('members')->get();
        return $this->success($groups);
    }

    /** POST /courses/:id/groups — create a group */
    public function createGroup(Request $request, string $courseId)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'idnumber' => 'nullable|string|max:100',
            'enrolmentkey' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        $data['course_id'] = $courseId;
        $group = MGroup::create($data);
        return $this->success($group, 201);
    }

    /** POST /groups/:id/members — add member to group */
    public function addGroupMember(Request $request, string $groupId)
    {
        $data = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
        ]);

        $member = MGroupMember::firstOrCreate(
            ['group_id' => $groupId, 'user_id' => $data['user_id']]
        );
        return $this->success($member, 201);
    }

    /** DELETE /groups/:id/members/:userid — remove member from group */
    public function removeGroupMember(string $groupId, string $userId)
    {
        MGroupMember::where('group_id', $groupId)->where('user_id', $userId)->delete();
        return $this->success(null);
    }

    /** POST /courses/:id/groups/auto — auto-create N groups */
    public function autoCreateGroups(Request $request, string $courseId)
    {
        $data = $request->validate([
            'count' => 'sometimes|integer|min:1',
            'per_group' => 'sometimes|integer|min:1',
        ]);

        $count = $data['count'] ?? 0;
        $perGroup = $data['per_group'] ?? 0;

        if ($perGroup > 0) {
            $enrolledUsers = $this->enrolService->getEnrolledUsers($courseId);
            $count = (int) ceil($enrolledUsers->count() / $perGroup);
        }

        $groups = [];
        for ($i = 1; $i <= $count; $i++) {
            $groups[] = MGroup::create([
                'course_id' => $courseId,
                'name' => 'Group ' . $i,
            ]);
        }

        return $this->success($groups, 201);
    }

    // ===== Groupings (spec §7) =====

    /** POST /courses/:id/groupings — create a grouping */
    public function createGrouping(Request $request, string $courseId)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        $data['course_id'] = $courseId;
        $grouping = MGrouping::create($data);
        return $this->success($grouping, 201);
    }

    /** POST /groupings/:id/groups — add group to grouping */
    public function addGroupToGrouping(Request $request, string $groupingId)
    {
        $data = $request->validate([
            'group_id' => 'required|uuid|exists:m_groups,id',
        ]);

        \DB::table('m_groupings_groups')->firstOrCreate(
            ['grouping_id' => $groupingId, 'group_id' => $data['group_id']]
        );

        return $this->success(null, 201);
    }

    // ===== Cohorts (spec §8) =====

    /** GET /cohorts — list cohorts */
    public function indexCohorts()
    {
        $cohorts = MCohort::withCount('members')->orderBy('name')->get();
        return $this->success($cohorts);
    }

    /** POST /cohorts — create a cohort */
    public function createCohort(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'idnumber' => 'nullable|string|max:100',
            'visible' => 'sometimes|boolean',
            'description' => 'nullable|string',
            'context_id' => 'nullable|uuid',
        ]);

        $cohort = MCohort::create($data);
        return $this->success($cohort, 201);
    }

    /** POST /cohorts/:id/members — add member to cohort */
    public function addCohortMember(Request $request, string $cohortId)
    {
        $data = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
        ]);

        $member = MCohortMember::firstOrCreate(
            ['cohort_id' => $cohortId, 'user_id' => $data['user_id']]
        );
        return $this->success($member, 201);
    }

    /** POST /cohorts/:id/sync-to-course — sync cohort to course enrolment */
    public function syncToCourse(Request $request, string $cohortId)
    {
        $data = $request->validate([
            'course_id' => 'required|uuid|exists:courses,id',
            'role_id' => 'nullable|uuid|exists:m_roles,id',
        ]);

        $count = $this->enrolService->cohortSync($cohortId, $data['course_id'], $data['role_id'] ?? null);

        return $this->success(['enrolled_count' => $count]);
    }
}
