<?php

namespace App\Services;

use App\Models\MGradeItem;
use App\Models\MGradeCategory;
use App\Models\MGradeGrade;
use App\Models\MCourseModuleCompletion;
use App\Models\MCourseCompletion;
use App\Models\MCourseCompletionCriteria;
use App\Models\MCourseModule;
use Illuminate\Support\Facades\DB;

class GradebookService
{
    /**
     * Create a grade item for a module instance (spec §12.3).
     */
    public function createGradeItem(string $courseId, string $itemType, ?string $itemModule = null, ?string $itemInstance = null, ?string $itemname = null, float $grademax = 100): MGradeItem
    {
        return MGradeItem::create([
            'course_id' => $courseId,
            'itemtype' => $itemType,
            'itemmodule' => $itemModule,
            'iteminstance' => $itemInstance,
            'itemname' => $itemname,
            'grademax' => $grademax,
            'sortorder' => MGradeItem::where('course_id', $courseId)->max('sortorder') + 1,
        ]);
    }

    /**
     * Update a student's grade (spec §12.3).
     */
    public function updateGrade(string $itemId, string $userId, ?float $rawgrade, ?string $feedback = null): MGradeGrade
    {
        $grade = MGradeGrade::firstOrNew(['itemid' => $itemId, 'user_id' => $userId]);

        // Record history
        if ($grade->exists && $grade->rawgrade !== $rawgrade) {
            DB::table('m_grade_grades_history')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'itemid' => $itemId,
                'user_id' => $userId,
                'oldgrade' => $grade->rawgrade,
                'newgrade' => $rawgrade,
                'timemodified' => now(),
            ]);
        }

        $grade->rawgrade = $rawgrade;
        $grade->finalgrade = $rawgrade;
        $grade->feedback = $feedback;
        $grade->overridden = true;
        $grade->timemodified = now();
        $grade->save();

        // Mark parent items as needing update
        $item = MGradeItem::find($itemId);
        if ($item && $item->category_id) {
            MGradeItem::where('id', $item->category_id)->update(['needsupdate' => true]);
        }

        return $grade;
    }

    /**
     * Aggregate grades for a category (spec §12.2).
     */
    public function aggregateCategory(string $categoryId, string $userId): ?float
    {
        $category = MGradeCategory::findOrFail($categoryId);
        $items = MGradeItem::where('category_id', $categoryId)->where('hidden', false)->get();

        $grades = [];
        foreach ($items as $item) {
            $grade = MGradeGrade::where('itemid', $item->id)
                ->where('user_id', $userId)
                ->whereNotNull('finalgrade')
                ->first();
            if ($grade) {
                $grades[] = [
                    'grade' => (float) $grade->finalgrade,
                    'max' => (float) $item->grademax,
                    'weight' => (float) $item->aggregationcoef,
                ];
            }
        }

        if (empty($grades)) return null;

        return match ($category->aggregation) {
            'natural', 'sum' => array_sum(array_column($grades, 'grade')),
            'mean' => array_sum(array_column($grades, 'grade')) / count($grades),
            'max' => max(array_column($grades, 'grade')),
            'min' => min(array_column($grades, 'grade')),
            'weighted' => $this->weightedMean($grades),
            default => array_sum(array_column($grades, 'grade')),
        };
    }

    /**
     * Weighted mean calculation.
     */
    private function weightedMean(array $grades): float
    {
        $totalWeight = 0;
        $weightedSum = 0;
        foreach ($grades as $g) {
            $totalWeight += $g['weight'];
            $weightedSum += $g['grade'] * $g['weight'];
        }
        return $totalWeight > 0 ? $weightedSum / $totalWeight : 0;
    }

    /**
     * Get the full gradebook for a course (spec §12.3).
     */
    public function getCourseGradebook(string $courseId)
    {
        $items = MGradeItem::where('course_id', $courseId)
            ->orderBy('sortorder')
            ->with(['grades'])
            ->get();

        $categories = MGradeCategory::where('course_id', $courseId)->get();

        return [
            'items' => $items,
            'categories' => $categories,
        ];
    }

    /**
     * Get a student's grades for a course.
     */
    public function getStudentGrades(string $courseId, string $userId)
    {
        $items = MGradeItem::where('course_id', $courseId)
            ->where('hidden', false)
            ->orderBy('sortorder')
            ->get();

        $grades = [];
        foreach ($items as $item) {
            $grade = MGradeGrade::where('itemid', $item->id)
                ->where('user_id', $userId)
                ->first();
            $grades[] = [
                'item' => $item,
                'grade' => $grade,
                'percentage' => $grade && $grade->finalgrade && $item->grademax > 0
                    ? round(($grade->finalgrade / $item->grademax) * 100, 2)
                    : null,
            ];
        }

        return $grades;
    }

    /**
     * Mark a course module as complete (spec §14.1).
     */
    public function markModuleComplete(string $courseModuleId, string $userId, string $state = 'COMPLETE'): void
    {
        MCourseModuleCompletion::updateOrCreate(
            ['course_module_id' => $courseModuleId, 'user_id' => $userId],
            ['completionstate' => $state, 'timemodified' => now()]
        );

        // Check if course is now complete
        $this->checkCourseCompletion($courseModuleId, $userId);
    }

    /**
     * Check if a user has completed a course (spec §14.2).
     */
    public function checkCourseCompletion(string $courseModuleId, string $userId): void
    {
        $module = MCourseModule::find($courseModuleId);
        if (!$module) return;

        $courseId = $module->course_id;
        $criteria = MCourseCompletionCriteria::where('course_id', $courseId)->get();

        if ($criteria->isEmpty()) return;

        $allMet = true;
        foreach ($criteria as $criterion) {
            $met = match ($criterion->criteriatype) {
                'activity' => $this->checkActivityCriteria($criterion, $userId),
                'grade' => $this->checkGradeCriteria($criterion, $courseId, $userId),
                'date' => now()->gte($criterion->date),
                'self' => true, // Self-completion is always met
                default => false,
            };
            if (!$met) $allMet = false;
        }

        if ($allMet) {
            MCourseCompletion::updateOrCreate(
                ['course_id' => $courseId, 'user_id' => $userId],
                ['status' => 'COMPLETE', 'timecompleted' => now()]
            );
        }
    }

    private function checkActivityCriteria($criterion, string $userId): bool
    {
        if (!$criterion->module_instance) return false;
        return MCourseModuleCompletion::where('course_module_id', $criterion->module_instance)
            ->where('user_id', $userId)
            ->where('completionstate', 'COMPLETE')
            ->exists();
    }

    private function checkGradeCriteria($criterion, string $courseId, string $userId): bool
    {
        $items = MGradeItem::where('course_id', $courseId)->get();
        foreach ($items as $item) {
            $grade = MGradeGrade::where('itemid', $item->id)->where('user_id', $userId)->first();
            if ($grade && $grade->finalgrade && $grade->finalgrade >= $criterion->gradepass) {
                return true;
            }
        }
        return false;
    }

    /**
     * Export grades as CSV (spec §12.3).
     */
    public function exportCsv(string $courseId): string
    {
        $items = MGradeItem::where('course_id', $courseId)->where('hidden', false)->orderBy('sortorder')->get();
        $enrolledUsers = DB::table('m_user_enrolments')
            ->join('m_enrol', 'm_user_enrolments.enrol_id', '=', 'm_enrol.id')
            ->join('users', 'm_user_enrolments.user_id', '=', 'users.id')
            ->where('m_enrol.course_id', $courseId)
            ->where('m_user_enrolments.status', 'ACTIVE')
            ->select('users.id', 'users.full_name', 'users.email')
            ->get();

        $headers = ['Name', 'Email'];
        foreach ($items as $item) {
            $headers[] = $item->itemname ? $item->itemname : ($item->itemmodule ?? 'Item');
        }

        $rows = [implode(',', $headers)];
        foreach ($enrolledUsers as $user) {
            $row = [$user->full_name, $user->email];
            foreach ($items as $item) {
                $grade = MGradeGrade::where('itemid', $item->id)->where('user_id', $user->id)->first();
                $row[] = $grade && $grade->finalgrade ? $grade->finalgrade : '-';
            }
            $rows[] = implode(',', $row);
        }

        return implode("\n", $rows);
    }
}
