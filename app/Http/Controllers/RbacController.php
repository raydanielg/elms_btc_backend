<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MRole;
use App\Models\MCapability;
use App\Models\MContext;
use App\Models\MRoleAssignment;
use App\Models\MRoleCapability;
use App\Models\MRoleContextLevel;
use App\Services\CapabilityService;

class RbacController extends Controller
{
    public function __construct(
        private CapabilityService $capService
    ) {}

    /** GET /roles — list all roles */
    public function indexRoles()
    {
        return $this->success(MRole::with('contextLevels')->orderBy('sortorder')->get());
    }

    /** POST /roles — create a new role */
    public function createRole(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'shortname' => 'required|string|max:50|unique:m_roles,shortname',
            'archetype' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        $role = MRole::create($data);
        return $this->success($role, 201);
    }

    /** POST /roles/:id/assign — assign role to user in context */
    public function assignRole(Request $request, string $roleId)
    {
        $data = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'context_id' => 'required|uuid|exists:m_contexts,id',
        ]);

        $assignment = $this->capService->assignRole(
            $data['user_id'], $roleId, $data['context_id'], $request->user()?->id
        );

        $this->capService->clearCache($data['user_id']);

        return $this->success($assignment->load(['role', 'context']), 201);
    }

    /** POST /roles/:id/capabilities — set capability override */
    public function setCapabilityOverride(Request $request, string $roleId)
    {
        $data = $request->validate([
            'capability_id' => 'required|uuid|exists:m_capabilities,id',
            'context_id' => 'required|uuid|exists:m_contexts,id',
            'permission' => 'required|integer|in:1,-1,-1000,0',
        ]);

        $override = $this->capService->setCapabilityOverride(
            $roleId, $data['capability_id'], $data['context_id'], $data['permission'], $request->user()?->id
        );

        return $this->success($override->load(['capability', 'context']));
    }

    /** GET /context/:id/permissions/:userid — resolved permissions for a user in a context */
    public function getPermissions(string $contextId, string $userId)
    {
        $context = MContext::findOrFail($contextId);
        $capabilities = $this->capService->getUserCapabilities($userId, $context);

        $result = [];
        foreach ($capabilities as $capName => $permission) {
            $result[] = [
                'capability' => $capName,
                'permission' => $permission,
                'allowed' => $permission === MCapability::ALLOW,
                'prohibited' => $permission === MCapability::PROHIBIT,
            ];
        }

        return $this->success($result);
    }

    /** GET /capabilities — list all capabilities */
    public function indexCapabilities(Request $request)
    {
        $query = MCapability::query();

        if ($component = $request->get('component')) {
            $query->where('component', $component);
        }
        if ($level = $request->get('contextlevel')) {
            $query->where('contextlevel', $level);
        }

        return $this->success($query->orderBy('component')->orderBy('name')->get());
    }

    /** GET /contexts — list contexts (with optional filtering) */
    public function indexContexts(Request $request)
    {
        $query = MContext::query();

        if ($level = $request->get('contextlevel')) {
            $query->where('contextlevel', $level);
        }
        if ($instanceId = $request->get('instanceid')) {
            $query->where('instanceid', $instanceId);
        }

        return $this->success($query->orderBy('depth')->orderBy('contextlevel')->get());
    }

    /** POST /contexts — create or get a context for a level + instance */
    public function createContext(Request $request)
    {
        $data = $request->validate([
            'contextlevel' => 'required|integer|in:10,30,40,50,70,80',
            'instanceid' => 'nullable|uuid',
        ]);

        $context = $this->capService->getOrCreateContext($data['contextlevel'], $data['instanceid'] ?? null);

        return $this->success($context, 201);
    }

    /** GET /roles/:id/assignments — list all assignments for a role */
    public function roleAssignments(string $roleId)
    {
        $assignments = MRoleAssignment::where('role_id', $roleId)
            ->with(['user:id,full_name,email,role,status', 'context'])
            ->get();

        return $this->success($assignments);
    }

    /** DELETE /roles/:id/assign — unassign role from user in context */
    public function unassignRole(Request $request, string $roleId)
    {
        $data = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'context_id' => 'required|uuid|exists:m_contexts,id',
        ]);

        $removed = $this->capService->unassignRole($data['user_id'], $roleId, $data['context_id']);
        $this->capService->clearCache($data['user_id']);

        return $this->success(['removed' => $removed]);
    }
}
