<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
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
use Carbon\Carbon;

class LmsController extends Controller
{
    // ===== Topics =====
    public function getTopics(string $courseId)
    {
        $topics = Topic::where('course_id', $courseId)->orderBy('order_no')->get();
        return $this->success($topics);
    }

    public function createTopic(Request $request, string $courseId)
    {
        Course::findOrFail($courseId);

        $data = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'sometimes|string',
            'order_no' => 'sometimes|integer',
        ]);

        $topic = Topic::create(array_merge($data, ['course_id' => $courseId]));
        return $this->success($topic, 201);
    }

    // ===== Materials =====
    public function getMaterials(string $topicId)
    {
        $materials = Material::where('topic_id', $topicId)->latest()->get();
        return $this->success($materials);
    }

    public function createMaterial(Request $request, string $topicId)
    {
        Topic::findOrFail($topicId);

        $data = $request->validate([
            'type' => 'required|in:PDF,SLIDE,VIDEO,LINK',
            'title' => 'required|string|max:150',
            'file_url' => 'sometimes|string|max:255',
        ]);

        $material = Material::create(array_merge($data, [
            'topic_id' => $topicId,
            'uploaded_by' => $request->user()->id,
        ]));

        return $this->success($material, 201);
    }

    // ===== Assignments =====
    public function createAssignment(Request $request, string $courseId)
    {
        Course::findOrFail($courseId);

        $data = $request->validate([
            'title' => 'required|string|max:150',
            'instructions' => 'sometimes|string',
            'max_marks' => 'sometimes|integer|min:1',
            'open_at' => 'sometimes|date',
            'due_at' => 'sometimes|date',
            'timer_minutes' => 'sometimes|integer|min:1',
            'allow_late' => 'sometimes|boolean',
        ]);

        $assignment = Assignment::create(array_merge($data, ['course_id' => $courseId]));
        return $this->success($assignment, 201);
    }

    public function submitAssignment(Request $request, string $assignmentId)
    {
        $assignment = Assignment::findOrFail($assignmentId);
        $user = $request->user();

        $existing = AssignmentSubmission::where('assignment_id', $assignmentId)
            ->where('student_id', $user->id)
            ->first();

        if ($existing) {
            return $this->error('CONFLICT', 'You have already submitted this assignment.', 409);
        }

        $data = $request->validate([
            'file_url' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
        ]);

        $now = Carbon::now();
        $status = 'ON_TIME';

        if ($assignment->due_at && $now->isAfter($assignment->due_at)) {
            if (!$assignment->allow_late) {
                return $this->error('PAST_DUE', 'The assignment deadline has passed.', 422);
            }
            $status = 'LATE';
        }

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignmentId,
            'student_id' => $user->id,
            'file_url' => $data['file_url'] ?? null,
            'content' => $data['content'] ?? null,
            'submitted_at' => $now,
            'status' => $status,
        ]);

        return $this->success($submission, 201);
    }

    public function getSubmissions(string $assignmentId)
    {
        $submissions = AssignmentSubmission::where('assignment_id', $assignmentId)
            ->with('student')
            ->latest()
            ->get();

        return $this->success($submissions);
    }

    public function gradeSubmission(Request $request, string $submissionId)
    {
        $submission = AssignmentSubmission::findOrFail($submissionId);

        $data = $request->validate([
            'marks' => 'required|integer|min:0',
            'feedback' => 'sometimes|string',
        ]);

        $submission->update([
            'marks' => $data['marks'],
            'feedback' => $data['feedback'] ?? null,
        ]);

        $this->audit('grade_submission', 'AssignmentSubmission', $submission->id, $data);

        return $this->success($submission);
    }

    // ===== Quizzes =====
    public function createQuiz(Request $request, string $courseId)
    {
        Course::findOrFail($courseId);

        $data = $request->validate([
            'title' => 'required|string|max:150',
            'duration_minutes' => 'required|integer|min:1',
            'total_marks' => 'required|integer|min:1',
            'open_at' => 'sometimes|date',
            'close_at' => 'sometimes|date',
        ]);

        $quiz = Quiz::create(array_merge($data, ['course_id' => $courseId]));
        return $this->success($quiz, 201);
    }

    public function createQuestion(Request $request, string $quizId)
    {
        Quiz::findOrFail($quizId);

        $data = $request->validate([
            'question' => 'required|string',
            'type' => 'required|in:MCQ,TRUE_FALSE,SHORT',
            'options' => 'sometimes|array',
            'correct_answer' => 'required|string',
            'marks' => 'required|integer|min:1',
        ]);

        $question = QuizQuestion::create(array_merge($data, ['quiz_id' => $quizId]));
        return $this->success($question, 201);
    }

    public function startAttempt(Request $request, string $quizId)
    {
        $quiz = Quiz::findOrFail($quizId);
        $user = $request->user();

        $existing = QuizAttempt::where('quiz_id', $quizId)
            ->where('student_id', $user->id)
            ->whereNotNull('submitted_at')
            ->first();

        if ($existing) {
            return $this->error('CONFLICT', 'You have already completed this quiz.', 409);
        }

        $attempt = QuizAttempt::create([
            'quiz_id' => $quizId,
            'student_id' => $user->id,
            'started_at' => Carbon::now(),
        ]);

        return $this->success([
            'attempt' => $attempt,
            'questions' => $quiz->questions->makeHidden('correct_answer'),
        ], 201);
    }

    public function submitAttempt(Request $request, string $quizId)
    {
        $quiz = Quiz::findOrFail($quizId);
        $user = $request->user();

        $attempt = QuizAttempt::where('quiz_id', $quizId)
            ->where('student_id', $user->id)
            ->whereNull('submitted_at')
            ->first();

        if (!$attempt) {
            return $this->error('NO_ACTIVE_ATTEMPT', 'No active attempt found.', 404);
        }

        $now = Carbon::now();
        $elapsed = $now->diffInMinutes($attempt->started_at);

        if ($elapsed > $quiz->duration_minutes) {
            $elapsed = $quiz->duration_minutes;
        }

        $data = $request->validate([
            'answers' => 'required|array',
        ]);

        $score = 0;
        foreach ($quiz->questions as $question) {
            $studentAnswer = $data['answers'][$question->id] ?? null;
            if ($studentAnswer && $studentAnswer === $question->correct_answer) {
                $score += $question->marks;
            }
        }

        $attempt->update([
            'answers' => $data['answers'],
            'score' => $score,
            'submitted_at' => $now,
        ]);

        return $this->success([
            'attempt' => $attempt,
            'score' => $score,
            'total_marks' => $quiz->total_marks,
        ]);
    }

    public function getQuizResults(string $quizId)
    {
        $attempts = QuizAttempt::where('quiz_id', $quizId)
            ->with('student')
            ->latest()
            ->get();

        return $this->success($attempts);
    }

    // ===== Online Classes =====
    public function getOnlineClasses(string $courseId)
    {
        $classes = OnlineClass::where('course_id', $courseId)->latest()->get();
        return $this->success($classes);
    }

    public function createOnlineClass(Request $request, string $courseId)
    {
        Course::findOrFail($courseId);

        $data = $request->validate([
            'title' => 'required|string|max:150',
            'platform' => 'sometimes|in:MEET,ZOOM,OTHER',
            'link' => 'required|string|max:255',
            'scheduled_at' => 'required|date',
            'duration_minutes' => 'sometimes|integer|min:1',
        ]);

        $class = OnlineClass::create(array_merge($data, ['course_id' => $courseId]));
        return $this->success($class, 201);
    }

    // ===== Discussion Forum =====
    public function getThreads(string $courseId)
    {
        $threads = DiscussionThread::where('course_id', $courseId)
            ->with(['creator', 'posts.user'])
            ->latest()
            ->get();

        return $this->success($threads);
    }

    public function createThread(Request $request, string $courseId)
    {
        Course::findOrFail($courseId);

        $data = $request->validate([
            'title' => 'required|string|max:150',
        ]);

        $thread = DiscussionThread::create([
            'course_id' => $courseId,
            'title' => $data['title'],
            'created_by' => $request->user()->id,
        ]);

        return $this->success($thread, 201);
    }

    public function createPost(Request $request, string $threadId)
    {
        DiscussionThread::findOrFail($threadId);

        $data = $request->validate([
            'body' => 'required|string',
        ]);

        $post = DiscussionPost::create([
            'thread_id' => $threadId,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return $this->success($post, 201);
    }

    // ===== Announcements =====
    public function getAnnouncements(Request $request, string $courseId = null)
    {
        $query = Announcement::with('creator');

        if ($courseId) {
            $query->where('course_id', $courseId);
        } elseif ($request->has('scope')) {
            $query->where('scope', $request->scope);
        }

        $announcements = $query->latest()->get();
        return $this->success($announcements);
    }

    public function createAnnouncement(Request $request, ?string $courseId = null)
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'body' => 'required|string',
            'is_pinned' => 'sometimes|boolean',
            'scope' => 'sometimes|in:GLOBAL,COURSE',
        ]);

        $announcement = Announcement::create([
            'scope' => $data['scope'] ?? ($courseId ? 'COURSE' : 'GLOBAL'),
            'course_id' => $courseId,
            'title' => $data['title'],
            'body' => $data['body'],
            'is_pinned' => $data['is_pinned'] ?? false,
            'created_by' => $request->user()->id,
        ]);

        return $this->success($announcement, 201);
    }
}
