<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MCourseCategory;
use App\Models\MCourseSection;
use App\Models\MCourseModule;
use App\Models\MModule;
use App\Models\Course;
use App\Models\MCourseFormatOption;

class CourseStructureController extends Controller
{
    // ===== Categories (spec §4.1) =====

    /** GET /categories — list category tree */
    public function indexCategories(Request $request)
    {
        $query = MCourseCategory::with('children');

        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->get('parent_id'));
        } else {
            $query->whereNull('parent_id');
        }

        if (!$request->get('include_hidden')) {
            $query->where('visible', true);
        }

        $categories = $query->orderBy('sortorder')->get();
        return $this->success($categories);
    }

    /** POST /categories — create a category */
    public function createCategory(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'idnumber' => 'nullable|string|max:100',
            'parent_id' => 'nullable|uuid|exists:m_course_categories,id',
            'visible' => 'sometimes|boolean',
            'sortorder' => 'sometimes|integer',
            'description' => 'nullable|string',
        ]);

        $category = new MCourseCategory($data);
        $category->save();

        // Build path
        $category->path = MCourseCategory::buildPath($category);
        $category->depth = $category->parent_id
            ? MCourseCategory::find($category->parent_id)->depth + 1
            : 1;
        $category->save();

        return $this->success($category->fresh(), 201);
    }

    /** PATCH /categories/:id — update a category */
    public function updateCategory(Request $request, string $id)
    {
        $category = MCourseCategory::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:150',
            'idnumber' => 'nullable|string|max:100',
            'parent_id' => 'sometimes|uuid|exists:m_course_categories,id',
            'visible' => 'sometimes|boolean',
            'sortorder' => 'sometimes|integer',
            'description' => 'nullable|string',
        ]);

        $category->update($data);

        // Rebuild path if parent changed
        if (isset($data['parent_id'])) {
            $category->path = MCourseCategory::buildPath($category);
            $category->depth = $category->parent_id
                ? MCourseCategory::find($category->parent_id)->depth + 1
                : 1;
            $category->save();
        }

        return $this->success($category->fresh());
    }

    /** DELETE /categories/:id — delete a category */
    public function deleteCategory(string $id)
    {
        $category = MCourseCategory::findOrFail($id);

        // Prevent deletion if has children or courses
        if ($category->children()->exists()) {
            return $this->error('HAS_CHILDREN', 'Cannot delete a category with sub-categories.', 400);
        }
        if ($category->courses()->exists()) {
            return $this->error('HAS_COURSES', 'Cannot delete a category with courses.', 400);
        }

        $category->delete();
        return $this->success(null);
    }

    // ===== Sections (spec §4.3) =====

    /** GET /courses/:id/sections — list sections */
    public function indexSections(string $courseId)
    {
        $sections = MCourseSection::where('course_id', $courseId)
            ->orderBy('section')
            ->get();
        return $this->success($sections);
    }

    /** POST /courses/:id/sections — create a section */
    public function createSection(Request $request, string $courseId)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:150',
            'summary' => 'nullable|string',
            'visible' => 'sometimes|boolean',
        ]);

        $nextSection = MCourseSection::where('course_id', $courseId)->max('section') + 1;

        $section = MCourseSection::create([
            'course_id' => $courseId,
            'section' => $nextSection,
            'name' => $data['name'] ?? "Section {$nextSection}",
            'summary' => $data['summary'] ?? null,
            'visible' => $data['visible'] ?? true,
        ]);

        return $this->success($section, 201);
    }

    /** PATCH /courses/:id/sections/:num — update a section */
    public function updateSection(Request $request, string $courseId, int $sectionNum)
    {
        $section = MCourseSection::where('course_id', $courseId)
            ->where('section', $sectionNum)
            ->firstOrFail();

        $data = $request->validate([
            'name' => 'sometimes|string|max:150',
            'summary' => 'nullable|string',
            'visible' => 'sometimes|boolean',
        ]);

        $section->update($data);
        return $this->success($section);
    }

    /** POST /courses/:id/sections/:num/move — move section to new position */
    public function moveSection(Request $request, string $courseId, int $sectionNum)
    {
        $request->validate(['new_position' => 'required|integer|min:0']);

        $section = MCourseSection::where('course_id', $courseId)
            ->where('section', $sectionNum)
            ->firstOrFail();

        $newPos = $request->get('new_position');

        // Shift sections between old and new position
        if ($newPos > $sectionNum) {
            MCourseSection::where('course_id', $courseId)
                ->whereBetween('section', [$sectionNum + 1, $newPos])
                ->decrement('section');
        } else {
            MCourseSection::where('course_id', $courseId)
                ->whereBetween('section', [$newPos, $sectionNum - 1])
                ->increment('section');
        }

        $section->update(['section' => $newPos]);
        return $this->success($section->fresh());
    }

    // ===== Course Modules (spec §5) =====

    /** GET /modules — list module registry */
    public function indexModules()
    {
        return $this->success(MModule::where('active', true)->orderBy('name')->get());
    }

    /** POST /courses/:id/modules — add a module to a section */
    public function addModule(Request $request, string $courseId)
    {
        $data = $request->validate([
            'module_id' => 'required|uuid|exists:m_modules,id',
            'section_id' => 'nullable|uuid|exists:m_course_sections,id',
            'instance_id' => 'nullable|uuid',
            'idnumber' => 'nullable|string|max:100',
            'visible' => 'sometimes|boolean',
            'completion' => 'sometimes|string|in:NONE,MANUAL,AUTO',
            'availability' => 'sometimes|array',
            'groupmode' => 'sometimes|string|in:NOGROUPS,SEPARATE,VISIBLE',
        ]);

        $data['course_id'] = $courseId;
        $module = MCourseModule::create($data);

        // Add to section sequence
        if ($module->section_id) {
            $section = MCourseSection::find($module->section_id);
            if ($section) {
                $seq = $section->getModuleIds();
                $seq[] = $module->id;
                $section->update(['sequence' => implode(',', $seq)]);
            }
        }

        return $this->success($module->load(['module', 'section']), 201);
    }

    /** PATCH /modules/:cmid — update a course module */
    public function updateModule(Request $request, string $cmid)
    {
        $module = MCourseModule::findOrFail($cmid);
        $data = $request->validate([
            'visible' => 'sometimes|boolean',
            'visibleoncoursepage' => 'sometimes|boolean',
            'completion' => 'sometimes|string|in:NONE,MANUAL,AUTO',
            'completiongradeitemnumber' => 'sometimes|integer',
            'completionview' => 'sometimes|boolean',
            'completionexpected' => 'sometimes|date',
            'availability' => 'sometimes|array',
            'groupmode' => 'sometimes|string|in:NOGROUPS,SEPARATE,VISIBLE',
            'section_id' => 'sometimes|uuid|exists:m_course_sections,id',
        ]);

        $oldSectionId = $module->section_id;
        $module->update($data);

        // If section changed, update sequences
        if (isset($data['section_id']) && $data['section_id'] !== $oldSectionId) {
            // Remove from old section
            if ($oldSectionId) {
                $oldSection = MCourseSection::find($oldSectionId);
                if ($oldSection) {
                    $seq = array_values(array_filter($oldSection->getModuleIds(), fn($id) => $id !== $cmid));
                    $oldSection->update(['sequence' => implode(',', $seq)]);
                }
            }
            // Add to new section
            $newSection = MCourseSection::find($data['section_id']);
            if ($newSection) {
                $seq = $newSection->getModuleIds();
                $seq[] = $cmid;
                $newSection->update(['sequence' => implode(',', $seq)]);
            }
        }

        return $this->success($module->fresh());
    }

    /** POST /modules/:cmid/move — move module to section/position */
    public function moveModule(Request $request, string $cmid)
    {
        $data = $request->validate([
            'section_id' => 'required|uuid|exists:m_course_sections,id',
        ]);

        $module = MCourseModule::findOrFail($cmid);
        $oldSectionId = $module->section_id;

        // Remove from old section
        if ($oldSectionId) {
            $oldSection = MCourseSection::find($oldSectionId);
            if ($oldSection) {
                $seq = array_values(array_filter($oldSection->getModuleIds(), fn($id) => $id !== $cmid));
                $oldSection->update(['sequence' => implode(',', $seq)]);
            }
        }

        // Add to new section
        $newSection = MCourseSection::find($data['section_id']);
        $seq = $newSection->getModuleIds();
        $seq[] = $cmid;
        $newSection->update(['sequence' => implode(',', $seq)]);

        $module->update(['section_id' => $data['section_id']]);
        return $this->success($module->fresh());
    }

    /** DELETE /modules/:cmid — delete a course module */
    public function deleteModule(string $cmid)
    {
        $module = MCourseModule::findOrFail($cmid);

        // Remove from section sequence
        if ($module->section_id) {
            $section = MCourseSection::find($module->section_id);
            if ($section) {
                $seq = array_values(array_filter($section->getModuleIds(), fn($id) => $id !== $cmid));
                $section->update(['sequence' => implode(',', $seq)]);
            }
        }

        $module->delete();
        return $this->success(null);
    }

    /** GET /courses/:id/contents — full course tree (sections → modules) */
    public function courseContents(string $courseId)
    {
        $course = Course::with([
            'sections' => function ($q) { $q->orderBy('section'); },
            'sections.modules.module',
            'category',
            'formatOptions',
        ])->findOrFail($courseId);

        return $this->success($course);
    }

    /** POST /courses/:id/reset — reset course (wipe user data, keep content) */
    public function resetCourse(string $courseId)
    {
        $course = Course::findOrFail($courseId);

        // Delete user data but keep structure
        \App\Models\Enrollment::where('course_id', $courseId)->delete();
        \App\Models\AssignmentSubmission::whereIn('assignment_id',
            \App\Models\Assignment::where('course_id', $courseId)->pluck('id')
        )->delete();
        \App\Models\QuizAttempt::whereIn('quiz_id',
            \App\Models\Quiz::where('course_id', $courseId)->pluck('id')
        )->delete();
        \App\Models\DiscussionPost::whereIn('thread_id',
            \App\Models\DiscussionThread::where('course_id', $courseId)->pluck('id')
        )->delete();

        return $this->success(['message' => 'Course reset successfully. User data cleared, content preserved.']);
    }
}
