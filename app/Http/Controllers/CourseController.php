<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Topic;
use App\Models\Material;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizAttempt;
use App\Models\OnlineClass;
use App\Models\DiscussionThread;
use App\Models\DiscussionPost;
use App\Models\Announcement;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $query = Course::with(['program', 'subject', 'tutors']);

        if ($request->has('program_id')) {
            $query->where('program_id', $request->program_id);
        }
        if ($request->has('year')) {
            $query->where('year', $request->year);
        }
        if ($request->has('semester')) {
            $query->where('semester', $request->semester);
        }

        $courses = $query->latest()->paginate($request->get('limit', 20));
        return $this->paginated($courses);
    }

    public function show(string $id)
    {
        $course = Course::with(['program', 'subject', 'tutors', 'topics.materials', 'enrollments'])->findOrFail($id);
        return $this->success($course);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:20|unique:courses,code',
            'title' => 'required|string|max:150',
            'program_id' => 'required|exists:programs,id',
            'subject_id' => 'sometimes|exists:subjects,id',
            'year' => 'required|integer|in:1,2',
            'semester' => 'required|in:I,II',
            'credits' => 'required|integer|min:1',
            'description' => 'sometimes|string',
        ]);

        $course = Course::create($data);
        return $this->success($course, 201);
    }

    public function update(Request $request, string $id)
    {
        $course = Course::findOrFail($id);

        $data = $request->validate([
            'code' => 'sometimes|string|max:20|unique:courses,code,' . $id,
            'title' => 'sometimes|string|max:150',
            'program_id' => 'sometimes|exists:programs,id',
            'subject_id' => 'sometimes|exists:subjects,id',
            'year' => 'sometimes|integer|in:1,2',
            'semester' => 'sometimes|in:I,II',
            'credits' => 'sometimes|integer|min:1',
            'description' => 'sometimes|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $course->update($data);
        return $this->success($course);
    }

    public function destroy(string $id)
    {
        $course = Course::findOrFail($id);
        $course->delete();
        return $this->success(null);
    }

    public function myCourses(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'STUDENT') {
            $courses = $user->enrolledCourses()->with(['program', 'tutors'])->latest()->get();
        } elseif ($user->role === 'TUTOR') {
            $courses = $user->teachingCourses()->with(['program', 'enrollments'])->latest()->get();
        } else {
            $courses = Course::with(['program', 'tutors'])->latest()->get();
        }

        return $this->success($courses);
    }

    public function assignTutor(Request $request, string $id)
    {
        $course = Course::findOrFail($id);

        $data = $request->validate([
            'tutor_id' => 'required|exists:users,id',
            'role' => 'sometimes|in:LEAD,ASSISTANT',
        ]);

        $course->tutors()->syncWithoutDetaching([
            $data['tutor_id'] => ['role' => $data['role'] ?? 'LEAD'],
        ]);

        return $this->success($course->load('tutors'));
    }

    public function removeTutor(string $id, string $tutorId)
    {
        $course = Course::findOrFail($id);
        $course->tutors()->detach($tutorId);
        return $this->success(null);
    }

    public function getTutors(string $id)
    {
        $course = Course::findOrFail($id);
        return $this->success($course->tutors);
    }

    public function enroll(Request $request, string $id)
    {
        $course = Course::findOrFail($id);

        $data = $request->validate([
            'student_id' => 'sometimes|exists:users,id',
        ]);

        $studentId = $data['student_id'] ?? $request->user()->id;

        $existing = Enrollment::where('student_id', $studentId)
            ->where('course_id', $course->id)
            ->first();

        if ($existing) {
            return $this->error('CONFLICT', 'Student is already enrolled in this course.', 409);
        }

        $enrollment = Enrollment::create([
            'student_id' => $studentId,
            'course_id' => $course->id,
            'status' => 'ACTIVE',
        ]);

        return $this->success($enrollment, 201);
    }

    public function getStudents(string $id)
    {
        $course = Course::findOrFail($id);
        return $this->success($course->students);
    }
}
