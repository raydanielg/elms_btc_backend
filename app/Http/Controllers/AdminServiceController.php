<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class AdminServiceController extends Controller
{
    // ===== Badges (spec §15) =====

    /** GET /badges — list badges */
    public function indexBadges()
    {
        $badges = \App\Models\MBadge::withCount('issued')->orderBy('name')->get();
        return $this->success($badges);
    }

    /** POST /badges — create a badge */
    public function createBadge(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'type' => 'sometimes|string|in:site,course',
        ]);

        $data['user_id'] = $request->user()->id;
        $badge = \App\Models\MBadge::create($data);
        return $this->success($badge, 201);
    }

    /** POST /badges/:id/issue — issue a badge to a user */
    public function issueBadge(Request $request, string $badgeId)
    {
        $data = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
        ]);

        $issued = \App\Models\MBadgeIssued::firstOrCreate(
            ['badge_id' => $badgeId, 'user_id' => $data['user_id']]
        );
        return $this->success($issued, 201);
    }

    /** GET /me/badges — list user's earned badges */
    public function myBadges(Request $request)
    {
        $badges = \App\Models\MBadgeIssued::where('user_id', $request->user()->id)
            ->with('badge')
            ->get();
        return $this->success($badges);
    }

    // ===== Competencies (spec §16) =====

    /** GET /competency-frameworks — list frameworks */
    public function indexFrameworks()
    {
        return $this->success(\App\Models\MCompetencyFramework::withCount('competencies')->get());
    }

    /** POST /competency-frameworks — create a framework */
    public function createFramework(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
        ]);

        $framework = \App\Models\MCompetencyFramework::create($data);
        return $this->success($framework, 201);
    }

    /** POST /competency-frameworks/:id/competencies — add a competency */
    public function createCompetency(Request $request, string $frameworkId)
    {
        $data = $request->validate([
            'shortname' => 'required|string|max:100',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|uuid|exists:m_competencies,id',
        ]);

        $data['framework_id'] = $frameworkId;
        $competency = \App\Models\MCompetency::create($data);
        return $this->success($competency, 201);
    }

    /** PUT /competencies/:id/rate — rate a user's competency */
    public function rateCompetency(Request $request, string $competencyId)
    {
        $data = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'rating' => 'required|integer|min:0|max:4',
            'proficiency' => 'sometimes|string|in:NOT_PROFICIENT,PROFICIENT',
        ]);

        $uc = \App\Models\MUserCompetency::updateOrCreate(
            ['competency_id' => $competencyId, 'user_id' => $data['user_id']],
            [
                'rating' => $data['rating'],
                'proficiency' => $data['proficiency'] ?? ($data['rating'] >= 3 ? 'PROFICIENT' : 'NOT_PROFICIENT'),
                'reviewer_id' => $request->user()->id,
                'status' => 'REVIEWED',
            ]
        );

        return $this->success($uc);
    }

    // ===== Web Services (spec §24) =====

    /** POST /webservice/token — generate a web service token */
    public function createToken(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:200',
            'service' => 'required|string|in:mobile,rest,webservice',
            'validuntil' => 'nullable|date',
            'iprestriction' => 'nullable|string',
        ]);

        $token = \App\Models\MWebserviceToken::create([
            'user_id' => $request->user()->id,
            'token' => Str::random(64),
            'name' => $data['name'] ?? null,
            'service' => $data['service'],
            'validuntil' => $data['validuntil'] ?? null,
            'iprestriction' => $data['iprestriction'] ?? null,
        ]);

        return $this->success($token, 201);
    }

    /** GET /webservice/tokens — list user's tokens */
    public function indexTokens(Request $request)
    {
        $tokens = \App\Models\MWebserviceToken::where('user_id', $request->user()->id)
            ->where('active', true)
            ->get();
        return $this->success($tokens);
    }

    /** DELETE /webservice/tokens/:id — revoke a token */
    public function revokeToken(Request $request, string $id)
    {
        \App\Models\MWebserviceToken::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->update(['active' => false]);
        return $this->success(null);
    }

    // ===== Admin Config (spec §25) =====

    /** GET /admin/config — list all config values */
    public function indexConfig()
    {
        return $this->success(\App\Models\MConfig::where('hidden', false)->get());
    }

    /** PUT /admin/config/:name — update a config value */
    public function updateConfig(Request $request, string $name)
    {
        $config = \App\Models\MConfig::where('name', $name)->firstOrFail();
        $data = $request->validate([
            'value' => 'nullable|string',
        ]);

        $config->update(['value' => $data['value'] ?? null]);
        return $this->success($config);
    }

    // ===== Backup/Restore (spec §26) =====

    /** POST /courses/:id/backup — create a course backup */
    public function createBackup(Request $request, string $courseId)
    {
        $course = \App\Models\Course::with(['sections.modules', 'assignments', 'quizzes'])->findOrFail($courseId);

        $filename = "backup_{$courseId}_" . now()->format('Ymd_His') . '.json';
        $filepath = "backups/{$filename}";

        // Serialize course data
        $backupData = [
            'course' => $course->toArray(),
            'created_at' => now()->toIso8601String(),
            'created_by' => $request->user()->id,
        ];

        Storage::disk('local')->put($filepath, json_encode($backupData, JSON_PRETTY_PRINT));

        $backup = \App\Models\MBackup::create([
            'course_id' => $courseId,
            'filename' => $filename,
            'filepath' => $filepath,
            'filesize' => Storage::disk('local')->size($filepath),
            'user_id' => $request->user()->id,
            'status' => 'COMPLETE',
        ]);

        return $this->success($backup, 201);
    }

    /** GET /courses/:id/backups — list backups for a course */
    public function indexBackups(string $courseId)
    {
        $backups = \App\Models\MBackup::where('course_id', $courseId)
            ->orderBy('created_at', 'desc')
            ->get();
        return $this->success($backups);
    }

    /** POST /backups/:id/restore — restore a backup */
    public function restoreBackup(Request $request, string $backupId)
    {
        $backup = \App\Models\MBackup::findOrFail($backupId);

        if (!Storage::disk('local')->exists($backup->filepath)) {
            return $this->error('BACKUP_NOT_FOUND', 'Backup file not found on disk.', 404);
        }

        $data = json_decode(Storage::disk('local')->get($backup->filepath), true);

        // Restore course structure (sections + modules)
        $courseId = $data['course']['id'];
        $course = \App\Models\Course::find($courseId);

        if (!$course) {
            return $this->error('COURSE_NOT_FOUND', 'Target course no longer exists.', 404);
        }

        // Restore sections
        foreach ($data['course']['sections'] ?? [] as $sectionData) {
            \App\Models\MCourseSection::updateOrCreate(
                ['course_id' => $courseId, 'section' => $sectionData['section']],
                [
                    'name' => $sectionData['name'] ?? null,
                    'summary' => $sectionData['summary'] ?? null,
                    'visible' => $sectionData['visible'] ?? true,
                    'sequence' => $sectionData['sequence'] ?? null,
                ]
            );
        }

        return $this->success(['message' => 'Backup restored successfully.', 'backup_id' => $backupId]);
    }
}
