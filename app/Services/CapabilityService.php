<?php

namespace App\Services;

use App\Models\MContext;
use App\Models\MRoleAssignment;
use App\Models\MRoleCapability;
use App\Models\MCapability;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CapabilityService
{
    /**
     * Permission resolution algorithm (spec §3.2)
     *
     * has_capability(userid, capability, context):
     *   contexts = ancestors(context) + context  # by context.path
     *   verdict = INHERIT
     *   for ctx in contexts (system → leaf):
     *     for each role the user has in ctx:
     *       p = role_capabilities[role, capability, ctx]
     *       if p == PROHIBIT: return DENY          # cannot be overridden
     *       if p != INHERIT: verdict = p           # nearer context wins
     *   return verdict == ALLOW
     */
    public function hasCapability(string $userId, string $capabilityName, MContext $context): bool
    {
        $cacheKey = "cap:{$userId}:{$capabilityName}:{$context->id}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($userId, $capabilityName, $context) {
            // Get the capability
            $capability = MCapability::where('name', $capabilityName)->first();
            if (!$capability) return false;

            // Get all ancestor context IDs (including self) from path
            $ancestorIds = $context->ancestorIds();
            if (empty($ancestorIds)) $ancestorIds = [$context->id];

            // Get all roles the user has in any ancestor context
            $roleAssignments = MRoleAssignment::whereIn('context_id', $ancestorIds)
                ->where('user_id', $userId)
                ->pluck('role_id')
                ->unique();

            if ($roleAssignments->isEmpty()) return false;

            // Get role capabilities for this capability in all ancestor contexts
            $roleCaps = MRoleCapability::whereIn('role_id', $roleAssignments)
                ->whereIn('context_id', $ancestorIds)
                ->where('capability_id', $capability->id)
                ->orderByRaw('FIELD(context_id, ' . implode(',', array_reverse($ancestorIds)) . ')')
                ->get();

            if ($roleCaps->isEmpty()) return false;

            // Sort contexts from system → leaf (shallowest depth first)
            $contextsByDepth = MContext::whereIn('id', $ancestorIds)->orderBy('depth')->pluck('id')->toArray();

            $verdict = MCapability::INHERIT;

            foreach ($contextsByDepth as $ctxId) {
                $capsForContext = $roleCaps->where('context_id', $ctxId);
                foreach ($capsForContext as $cap) {
                    if ($cap->permission === MCapability::PROHIBIT) {
                        return false; // PROHIBIT always wins, cannot be overridden
                    }
                    if ($cap->permission !== MCapability::INHERIT) {
                        $verdict = $cap->permission; // nearer context wins
                    }
                }
            }

            return $verdict === MCapability::ALLOW;
        });
    }

    /**
     * Check if user has any of the given capabilities in a context.
     */
    public function hasAnyCapability(string $userId, array $capabilityNames, MContext $context): bool
    {
        foreach ($capabilityNames as $cap) {
            if ($this->hasCapability($userId, $cap, $context)) return true;
        }
        return false;
    }

    /**
     * Check if user has all of the given capabilities in a context.
     */
    public function hasAllCapabilities(string $userId, array $capabilityNames, MContext $context): bool
    {
        foreach ($capabilityNames as $cap) {
            if (!$this->hasCapability($userId, $cap, $context)) return false;
        }
        return true;
    }

    /**
     * Get or create a context for a given level + instance.
     */
    public function getOrCreateContext(int $contextlevel, ?string $instanceid = null): MContext
    {
        $query = MContext::where('contextlevel', $contextlevel);
        if ($instanceid) {
            $query->where('instanceid', $instanceid);
        } else {
            $query->whereNull('instanceid');
        }

        $context = $query->first();

        if ($context) return $context;

        // Create new context
        $context = new MContext();
        $context->contextlevel = $contextlevel;
        $context->instanceid = $instanceid;
        $context->depth = 1;
        $context->path = '';
        $context->save();

        // Build path based on parent context
        $parentContext = $this->getParentContext($contextlevel, $instanceid);
        if ($parentContext) {
            $context->path = $parentContext->path . '/' . $context->id;
            $context->depth = $parentContext->depth + 1;
        } else {
            $context->path = '/' . $context->id;
            $context->depth = 1;
        }
        $context->save();

        return $context;
    }

    /**
     * Get the system context (level 10).
     */
    public function getSystemContext(): MContext
    {
        return $this->getOrCreateContext(MContext::LEVEL_SYSTEM);
    }

    /**
     * Get parent context for a given level.
     */
    private function getParentContext(int $contextlevel, ?string $instanceid): ?MContext
    {
        switch ($contextlevel) {
            case MContext::LEVEL_SYSTEM:
                return null; // System is root
            case MContext::LEVEL_USER:
                return $this->getSystemContext();
            case MContext::LEVEL_COURSECAT:
                // Parent is system or another category — for now, system
                return $this->getSystemContext();
            case MContext::LEVEL_COURSE:
                // Parent is a course category — for now, system
                return $this->getSystemContext();
            case MContext::LEVEL_MODULE:
                // Parent is the course context — needs course_id
                // This will be resolved when we have course data
                return $this->getSystemContext();
            default:
                return $this->getSystemContext();
        }
    }

    /**
     * Assign a role to a user in a context.
     */
    public function assignRole(string $userId, string $roleId, string $contextId, ?string $modifierId = null): MRoleAssignment
    {
        return MRoleAssignment::firstOrCreate(
            [
                'role_id' => $roleId,
                'context_id' => $contextId,
                'user_id' => $userId,
            ],
            [
                'modifier_id' => $modifierId,
                'timemodified' => now(),
            ]
        );
    }

    /**
     * Unassign a role from a user in a context.
     */
    public function unassignRole(string $userId, string $roleId, string $contextId): bool
    {
        return MRoleAssignment::where('user_id', $userId)
            ->where('role_id', $roleId)
            ->where('context_id', $contextId)
            ->delete() > 0;
    }

    /**
     * Set a capability override for a role in a context.
     */
    public function setCapabilityOverride(string $roleId, string $capabilityId, string $contextId, int $permission, ?string $modifierId = null): MRoleCapability
    {
        return MRoleCapability::updateOrCreate(
            [
                'role_id' => $roleId,
                'capability_id' => $capabilityId,
                'context_id' => $contextId,
            ],
            [
                'permission' => $permission,
                'modifier_id' => $modifierId,
                'timemodified' => now(),
            ]
        );
    }

    /**
     * Get all capabilities for a user in a context (resolved).
     */
    public function getUserCapabilities(string $userId, MContext $context): array
    {
        $ancestorIds = $context->ancestorIds();
        if (empty($ancestorIds)) $ancestorIds = [$context->id];

        $roleAssignments = MRoleAssignment::whereIn('context_id', $ancestorIds)
            ->where('user_id', $userId)
            ->pluck('role_id')
            ->unique();

        if ($roleAssignments->isEmpty()) return [];

        $roleCaps = MRoleCapability::whereIn('role_id', $roleAssignments)
            ->whereIn('context_id', $ancestorIds)
            ->with('capability')
            ->get();

        $capabilities = [];
        $contextsByDepth = MContext::whereIn('id', $ancestorIds)->orderBy('depth')->pluck('id')->toArray();

        foreach ($roleCaps as $cap) {
            $capName = $cap->capability->name;
            if (!isset($capabilities[$capName])) {
                $capabilities[$capName] = MCapability::INHERIT;
            }
            if ($cap->permission === MCapability::PROHIBIT) {
                $capabilities[$capName] = MCapability::PROHIBIT;
            } elseif ($cap->permission !== MCapability::INHERIT) {
                $capabilities[$capName] = $cap->permission;
            }
        }

        return $capabilities;
    }

    /**
     * Clear capability cache for a user.
     */
    public function clearCache(string $userId): void
    {
        Cache::flush(); // Simple approach — can be optimized with tag-based cache
    }
}
