<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MGradeItem;
use App\Models\MGradeCategory;
use App\Models\MGradeGrade;
use App\Models\MCourseCompletion;
use App\Models\MCourseCompletionCriteria;
use App\Services\GradebookService;

class GradebookController extends Controller
{
    public function __construct(
        private GradebookService $gradebookService
    ) {}

    /** GET /courses/:id/gradebook — full gradebook view (spec §12.3) */
    public function gradebook(string $courseId)
    {
        $data = $this->gradebookService->getCourseGradebook($courseId);
        return $this->success($data);
    }

    /** POST /courses/:id/grade-categories — create a grade category */
    public function createCategory(Request $request, string $courseId)
    {
        $data = $request->validate([
            'fullname' => 'required|string|max:200',
            'aggregation' => 'sometimes|string|in:natural,weighted,mean,sum,min,max',
            'aggregateonlygraded' => 'sometimes|boolean',
            'aggregateoutcomes' => 'sometimes|boolean',
            'droplow' => 'sometimes|boolean',
            'keephigh' => 'sometimes|integer',
        ]);

        $data['course_id'] = $courseId;
        $category = MGradeCategory::create($data);
        return $this->success($category, 201);
    }

    /** POST /courses/:id/grade-items — create a manual grade item */
    public function createItem(Request $request, string $courseId)
    {
        $data = $request->validate([
            'itemname' => 'required|string|max:200',
            'grademax' => 'sometimes|numeric|min:0',
            'grademin' => 'sometimes|numeric',
            'gradepass' => 'sometimes|numeric',
            'category_id' => 'nullable|uuid|exists:m_grade_categories,id',
        ]);

        $item = $this->gradebookService->createGradeItem(
            $courseId, 'manual', null, null, $data['itemname'], $data['grademax'] ?? 100
        );

        if (!empty($data['category_id'])) {
            $item->update(['category_id' => $data['category_id']]);
        }

        return $this->success($item, 201);
    }

    /** PUT /grade-items/:id/grades/:userid — set/update a grade (spec §12.3) */
    public function updateGrade(Request $request, string $itemId, string $userId)
    {
        $data = $request->validate([
            'grade' => 'nullable|numeric',
            'feedback' => 'nullable|string',
        ]);

        $grade = $this->gradebookService->updateGrade(
            $itemId, $userId, $data['grade'] ?? null, $data['feedback'] ?? null
        );

        return $this->success($grade);
    }

    /** GET /courses/:id/my-grades — student's own grades */
    public function myGrades(Request $request, string $courseId)
    {
        $grades = $this->gradebookService->getStudentGrades($courseId, $request->user()->id);
        return $this->success($grades);
    }

    /** GET /courses/:id/grades/:userid — view a specific student's grades */
    public function studentGrades(string $courseId, string $userId)
    {
        $grades = $this->gradebookService->getStudentGrades($courseId, $userId);
        return $this->success($grades);
    }

    /** GET /courses/:id/grades/export — export as CSV */
    public function exportCsv(string $courseId)
    {
        $csv = $this->gradebookService->exportCsv($courseId);
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="gradebook_' . $courseId . '.csv"',
        ]);
    }

    /** POST /courses/:id/completion-criteria — set completion criteria (spec §14.2) */
    public function setCompletionCriteria(Request $request, string $courseId)
    {
        $data = $request->validate([
            'criteria' => 'required|array',
            'criteria.*.criteriatype' => 'required|string|in:activity,grade,course,self,date',
            'criteria.*.module_instance' => 'nullable|uuid',
            'criteria.*.gradepass' => 'nullable|numeric',
            'criteria.*.date' => 'nullable|date',
        ]);

        // Clear existing
        MCourseCompletionCriteria::where('course_id', $courseId)->delete();

        foreach ($data['criteria'] as $criterion) {
            MCourseCompletionCriteria::create(array_merge(
                ['course_id' => $courseId],
                $criterion
            ));
        }

        return $this->success(['message' => 'Completion criteria set.']);
    }

    /** GET /courses/:id/completion/:userid — check course completion status */
    public function completionStatus(string $courseId, string $userId)
    {
        $completion = MCourseCompletion::where('course_id', $courseId)
            ->where('user_id', $userId)
            ->first();

        return $this->success([
            'status' => $completion ? $completion->status : 'INPROGRESS',
            'timecompleted' => $completion ? $completion->timecompleted : null,
        ]);
    }

    /** POST /modules/:cmid/complete — mark a module as complete (spec §14.1) */
    public function markComplete(Request $request, string $cmid)
    {
        $this->gradebookService->markModuleComplete(
            $cmid, $request->user()->id, 'COMPLETE'
        );
        return $this->success(['message' => 'Module marked as complete.']);
    }
}
