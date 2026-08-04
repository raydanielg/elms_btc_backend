<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MContext;
use App\Models\MRole;
use App\Models\MCapability;
use App\Models\MRoleCapability;
use App\Models\MRoleContextLevel;
use App\Services\CapabilityService;

class MoodleRbacSeeder extends Seeder
{
    public function run(): void
    {
        $capService = app(CapabilityService::class);

        // 1. Create system context
        $systemContext = $capService->getSystemContext();

        // 2. Create roles (spec §3.1 — seed roles)
        $roles = [
            ['shortname' => MRole::SHORT_MANAGER, 'name' => 'Manager', 'archetype' => MRole::ARCHETYPE_SUPER_ADMIN, 'sortorder' => 1],
            ['shortname' => MRole::SHORT_COURSE_CREATOR, 'name' => 'Course Creator', 'archetype' => MRole::ARCHETYPE_REGISTRAR, 'sortorder' => 2],
            ['shortname' => MRole::SHORT_EDITING_TEACHER, 'name' => 'Editing Teacher (Tutor)', 'archetype' => MRole::ARCHETYPE_TUTOR, 'sortorder' => 3],
            ['shortname' => MRole::SHORT_TEACHER, 'name' => 'Non-editing Teacher', 'archetype' => null, 'sortorder' => 4],
            ['shortname' => MRole::SHORT_STUDENT, 'name' => 'Student', 'archetype' => MRole::ARCHETYPE_STUDENT, 'sortorder' => 5],
            ['shortname' => MRole::SHORT_GUEST, 'name' => 'Guest', 'archetype' => MRole::ARCHETYPE_APPLICANT, 'sortorder' => 6],
            ['shortname' => MRole::SHORT_AUTHENTICATED, 'name' => 'Authenticated User', 'archetype' => null, 'sortorder' => 7],
        ];

        $roleMap = [];
        foreach ($roles as $r) {
            $role = MRole::firstOrCreate(['shortname' => $r['shortname']], $r);
            $roleMap[$r['shortname']] = $role;
        }

        // 3. Create core capabilities (spec §3.1 — master list)
        $capabilities = [
            // Site-level capabilities
            ['name' => 'moodle/site:config', 'contextlevel' => MContext::LEVEL_SYSTEM, 'riskbitmask' => MCapability::RISK_CONFIG, 'component' => 'core'],
            ['name' => 'moodle/site:viewreports', 'contextlevel' => MContext::LEVEL_SYSTEM, 'riskbitmask' => MCapability::RISK_PERSONAL, 'component' => 'core'],
            ['name' => 'moodle/site:backup', 'contextlevel' => MContext::LEVEL_SYSTEM, 'riskbitmask' => MCapability::RISK_DATALOSS, 'component' => 'core'],
            ['name' => 'moodle/site:restore', 'contextlevel' => MContext::LEVEL_SYSTEM, 'riskbitmask' => MCapability::RISK_DATALOSS, 'component' => 'core'],
            ['name' => 'moodle/site:manageallusers', 'contextlevel' => MContext::LEVEL_SYSTEM, 'riskbitmask' => MCapability::RISK_PERSONAL, 'component' => 'core'],

            // Course category capabilities
            ['name' => 'moodle/category:manage', 'contextlevel' => MContext::LEVEL_COURSECAT, 'riskbitmask' => MCapability::RISK_CONFIG, 'component' => 'core'],
            ['name' => 'moodle/category:view', 'contextlevel' => MContext::LEVEL_COURSECAT, 'riskbitmask' => 0, 'component' => 'core'],
            ['name' => 'moodle/category:create', 'contextlevel' => MContext::LEVEL_COURSECAT, 'riskbitmask' => MCapability::RISK_XSS, 'component' => 'core'],

            // Course capabilities
            ['name' => 'moodle/course:view', 'contextlevel' => MContext::LEVEL_COURSE, 'riskbitmask' => 0, 'component' => 'core'],
            ['name' => 'moodle/course:manageactivities', 'contextlevel' => MContext::LEVEL_COURSE, 'riskbitmask' => MCapability::RISK_XSS, 'component' => 'core'],
            ['name' => 'moodle/course:managegroups', 'contextlevel' => MContext::LEVEL_COURSE, 'riskbitmask' => MCapability::RISK_XSS, 'component' => 'core'],
            ['name' => 'moodle/course:enrolreview', 'contextlevel' => MContext::LEVEL_COURSE, 'riskbitmask' => MCapability::RISK_PERSONAL, 'component' => 'core'],
            ['name' => 'moodle/course:reset', 'contextlevel' => MContext::LEVEL_COURSE, 'riskbitmask' => MCapability::RISK_DATALOSS, 'component' => 'core'],
            ['name' => 'moodle/course:sectionvisible', 'contextlevel' => MContext::LEVEL_COURSE, 'riskbitmask' => 0, 'component' => 'core'],
            ['name' => 'moodle/course:update', 'contextlevel' => MContext::LEVEL_COURSE, 'riskbitmask' => MCapability::RISK_XSS, 'component' => 'core'],
            ['name' => 'moodle/course:delete', 'contextlevel' => MContext::LEVEL_COURSE, 'riskbitmask' => MCapability::RISK_DATALOSS, 'component' => 'core'],
            ['name' => 'moodle/course:backup', 'contextlevel' => MContext::LEVEL_COURSE, 'riskbitmask' => 0, 'component' => 'core'],
            ['name' => 'moodle/course:restore', 'contextlevel' => MContext::LEVEL_COURSE, 'riskbitmask' => MCapability::RISK_XSS, 'component' => 'core'],

            // Activity module capabilities
            ['name' => 'moodle/course:viewhiddenactivities', 'contextlevel' => MContext::LEVEL_COURSE, 'riskbitmask' => 0, 'component' => 'core'],
            ['name' => 'mod/assign:view', 'contextlevel' => MContext::LEVEL_MODULE, 'riskbitmask' => 0, 'component' => 'mod_assign'],
            ['name' => 'mod/assign:submit', 'contextlevel' => MContext::LEVEL_MODULE, 'riskbitmask' => MCapability::RISK_SPAM, 'component' => 'mod_assign'],
            ['name' => 'mod/assign:grade', 'contextlevel' => MContext::LEVEL_MODULE, 'riskbitmask' => MCapability::RISK_PERSONAL, 'component' => 'mod_assign'],
            ['name' => 'mod/quiz:view', 'contextlevel' => MContext::LEVEL_MODULE, 'riskbitmask' => 0, 'component' => 'mod_quiz'],
            ['name' => 'mod/quiz:attempt', 'contextlevel' => MContext::LEVEL_MODULE, 'riskbitmask' => MCapability::RISK_SPAM, 'component' => 'mod_quiz'],
            ['name' => 'mod/quiz:grade', 'contextlevel' => MContext::LEVEL_MODULE, 'riskbitmask' => MCapability::RISK_PERSONAL, 'component' => 'mod_quiz'],
            ['name' => 'mod/quiz:manage', 'contextlevel' => MContext::LEVEL_MODULE, 'riskbitmask' => MCapability::RISK_XSS, 'component' => 'mod_quiz'],
            ['name' => 'mod/forum:view', 'contextlevel' => MContext::LEVEL_MODULE, 'riskbitmask' => 0, 'component' => 'mod_forum'],
            ['name' => 'mod/forum:startdiscussion', 'contextlevel' => MContext::LEVEL_MODULE, 'riskbitmask' => MCapability::RISK_SPAM, 'component' => 'mod_forum'],
            ['name' => 'mod/forum:replypost', 'contextlevel' => MContext::LEVEL_MODULE, 'riskbitmask' => MCapability::RISK_SPAM, 'component' => 'mod_forum'],
            ['name' => 'mod/forum:deleteanypost', 'contextlevel' => MContext::LEVEL_MODULE, 'riskbitmask' => MCapability::RISK_DATALOSS, 'component' => 'mod_forum'],

            // User capabilities
            ['name' => 'moodle/user:viewdetails', 'contextlevel' => MContext::LEVEL_USER, 'riskbitmask' => MCapability::RISK_PERSONAL, 'component' => 'core'],
            ['name' => 'moodle/user:editprofile', 'contextlevel' => MContext::LEVEL_USER, 'riskbitmask' => MCapability::RISK_PERSONAL, 'component' => 'core'],
            ['name' => 'moodle/user:manageownprofile', 'contextlevel' => MContext::LEVEL_USER, 'riskbitmask' => MCapability::RISK_SPAM, 'component' => 'core'],

            // Gradebook capabilities
            ['name' => 'moodle/grade:view', 'contextlevel' => MContext::LEVEL_COURSE, 'riskbitmask' => MCapability::RISK_PERSONAL, 'component' => 'core_grades'],
            ['name' => 'moodle/grade:edit', 'contextlevel' => MContext::LEVEL_COURSE, 'riskbitmask' => MCapability::RISK_PERSONAL, 'component' => 'core_grades'],
            ['name' => 'moodle/grade:manage', 'contextlevel' => MContext::LEVEL_COURSE, 'riskbitmask' => MCapability::RISK_PERSONAL, 'component' => 'core_grades'],
            ['name' => 'moodle/grade:viewhidden', 'contextlevel' => MContext::LEVEL_COURSE, 'riskbitmask' => MCapability::RISK_PERSONAL, 'component' => 'core_grades'],

            // Enrolment capabilities
            ['name' => 'moodle/enrol:manualenrol', 'contextlevel' => MContext::LEVEL_COURSE, 'riskbitmask' => MCapability::RISK_PERSONAL, 'component' => 'core_enrol'],
            ['name' => 'moodle/enrol:config', 'contextlevel' => MContext::LEVEL_SYSTEM, 'riskbitmask' => MCapability::RISK_CONFIG, 'component' => 'core_enrol'],

            // Webservices
            ['name' => 'moodle/webservice:createtoken', 'contextlevel' => MContext::LEVEL_SYSTEM, 'riskbitmask' => MCapability::RISK_CONFIG, 'component' => 'core_webservices'],
        ];

        $capMap = [];
        foreach ($capabilities as $c) {
            $cap = MCapability::firstOrCreate(['name' => $c['name']], $c);
            $capMap[$c['name']] = $cap;
        }

        // 4. Set role context levels (where each role can be assigned)
        $roleContextLevels = [
            MRole::SHORT_MANAGER => [MContext::LEVEL_SYSTEM, MContext::LEVEL_COURSECAT, MContext::LEVEL_COURSE],
            MRole::SHORT_COURSE_CREATOR => [MContext::LEVEL_SYSTEM, MContext::LEVEL_COURSECAT],
            MRole::SHORT_EDITING_TEACHER => [MContext::LEVEL_COURSECAT, MContext::LEVEL_COURSE, MContext::LEVEL_MODULE],
            MRole::SHORT_TEACHER => [MContext::LEVEL_COURSECAT, MContext::LEVEL_COURSE, MContext::LEVEL_MODULE],
            MRole::SHORT_STUDENT => [MContext::LEVEL_COURSE, MContext::LEVEL_MODULE],
            MRole::SHORT_GUEST => [MContext::LEVEL_COURSE],
            MRole::SHORT_AUTHENTICATED => [MContext::LEVEL_SYSTEM],
        ];

        foreach ($roleContextLevels as $shortname => $levels) {
            $role = $roleMap[$shortname];
            foreach ($levels as $level) {
                MRoleContextLevel::firstOrCreate([
                    'role_id' => $role->id,
                    'contextlevel' => $level,
                ]);
            }
        }

        // 5. Assign default capabilities to roles at system context
        $roleDefaultCaps = [
            // Manager — nearly everything
            MRole::SHORT_MANAGER => [
                'moodle/site:config' => MCapability::ALLOW,
                'moodle/site:viewreports' => MCapability::ALLOW,
                'moodle/site:backup' => MCapability::ALLOW,
                'moodle/site:restore' => MCapability::ALLOW,
                'moodle/site:manageallusers' => MCapability::ALLOW,
                'moodle/category:manage' => MCapability::ALLOW,
                'moodle/category:view' => MCapability::ALLOW,
                'moodle/category:create' => MCapability::ALLOW,
                'moodle/course:view' => MCapability::ALLOW,
                'moodle/course:manageactivities' => MCapability::ALLOW,
                'moodle/course:managegroups' => MCapability::ALLOW,
                'moodle/course:enrolreview' => MCapability::ALLOW,
                'moodle/course:reset' => MCapability::ALLOW,
                'moodle/course:update' => MCapability::ALLOW,
                'moodle/course:delete' => MCapability::ALLOW,
                'moodle/course:backup' => MCapability::ALLOW,
                'moodle/course:restore' => MCapability::ALLOW,
                'moodle/grade:view' => MCapability::ALLOW,
                'moodle/grade:edit' => MCapability::ALLOW,
                'moodle/grade:manage' => MCapability::ALLOW,
                'moodle/grade:viewhidden' => MCapability::ALLOW,
                'moodle/enrol:manualenrol' => MCapability::ALLOW,
                'moodle/enrol:config' => MCapability::ALLOW,
                'moodle/webservice:createtoken' => MCapability::ALLOW,
            ],
            // Course Creator — create + manage categories
            MRole::SHORT_COURSE_CREATOR => [
                'moodle/category:manage' => MCapability::ALLOW,
                'moodle/category:view' => MCapability::ALLOW,
                'moodle/category:create' => MCapability::ALLOW,
                'moodle/course:view' => MCapability::ALLOW,
                'moodle/course:update' => MCapability::ALLOW,
                'moodle/course:backup' => MCapability::ALLOW,
                'moodle/course:restore' => MCapability::ALLOW,
            ],
            // Editing Teacher — manage course content
            MRole::SHORT_EDITING_TEACHER => [
                'moodle/course:view' => MCapability::ALLOW,
                'moodle/course:manageactivities' => MCapability::ALLOW,
                'moodle/course:managegroups' => MCapability::ALLOW,
                'moodle/course:enrolreview' => MCapability::ALLOW,
                'moodle/course:update' => MCapability::ALLOW,
                'moodle/course:backup' => MCapability::ALLOW,
                'moodle/course:viewhiddenactivities' => MCapability::ALLOW,
                'moodle/grade:view' => MCapability::ALLOW,
                'moodle/grade:edit' => MCapability::ALLOW,
                'moodle/grade:manage' => MCapability::ALLOW,
                'moodle/grade:viewhidden' => MCapability::ALLOW,
                'moodle/enrol:manualenrol' => MCapability::ALLOW,
                'mod/assign:view' => MCapability::ALLOW,
                'mod/assign:grade' => MCapability::ALLOW,
                'mod/quiz:view' => MCapability::ALLOW,
                'mod/quiz:grade' => MCapability::ALLOW,
                'mod/quiz:manage' => MCapability::ALLOW,
                'mod/forum:view' => MCapability::ALLOW,
                'mod/forum:startdiscussion' => MCapability::ALLOW,
                'mod/forum:replypost' => MCapability::ALLOW,
                'mod/forum:deleteanypost' => MCapability::ALLOW,
            ],
            // Non-editing Teacher — view + grade but not edit
            MRole::SHORT_TEACHER => [
                'moodle/course:view' => MCapability::ALLOW,
                'moodle/course:enrolreview' => MCapability::ALLOW,
                'moodle/course:viewhiddenactivities' => MCapability::ALLOW,
                'moodle/grade:view' => MCapability::ALLOW,
                'moodle/grade:edit' => MCapability::ALLOW,
                'mod/assign:view' => MCapability::ALLOW,
                'mod/assign:grade' => MCapability::ALLOW,
                'mod/quiz:view' => MCapability::ALLOW,
                'mod/quiz:grade' => MCapability::ALLOW,
                'mod/forum:view' => MCapability::ALLOW,
                'mod/forum:startdiscussion' => MCapability::ALLOW,
                'mod/forum:replypost' => MCapability::ALLOW,
            ],
            // Student — view + submit
            MRole::SHORT_STUDENT => [
                'moodle/course:view' => MCapability::ALLOW,
                'moodle/user:manageownprofile' => MCapability::ALLOW,
                'mod/assign:view' => MCapability::ALLOW,
                'mod/assign:submit' => MCapability::ALLOW,
                'mod/quiz:view' => MCapability::ALLOW,
                'mod/quiz:attempt' => MCapability::ALLOW,
                'mod/forum:view' => MCapability::ALLOW,
                'mod/forum:startdiscussion' => MCapability::ALLOW,
                'mod/forum:replypost' => MCapability::ALLOW,
            ],
            // Guest — view only
            MRole::SHORT_GUEST => [
                'moodle/course:view' => MCapability::ALLOW,
                'mod/forum:view' => MCapability::ALLOW,
            ],
            // Authenticated user — basic access
            MRole::SHORT_AUTHENTICATED => [
                'moodle/user:manageownprofile' => MCapability::ALLOW,
            ],
        ];

        foreach ($roleDefaultCaps as $shortname => $caps) {
            $role = $roleMap[$shortname];
            foreach ($caps as $capName => $permission) {
                if (isset($capMap[$capName])) {
                    MRoleCapability::firstOrCreate(
                        [
                            'role_id' => $role->id,
                            'capability_id' => $capMap[$capName]->id,
                            'context_id' => $systemContext->id,
                        ],
                        ['permission' => $permission, 'timemodified' => now()]
                    );
                }
            }
        }
    }
}
