<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Quiz;
use App\Models\MQuizSlot;
use App\Models\MQuizAttempt;
use App\Models\MQuizGrade;
use App\Models\MQuestion;
use App\Models\MQuestionAnswer;
use App\Models\MQuestionUsage;
use App\Models\MQuestionAttempt;
use App\Models\MQuestionAttemptStep;

class QuizEngineController extends Controller
{
    /** POST /quiz/:id/slots — add a question to a quiz */
    public function addSlot(Request $request, string $quizId)
    {
        $data = $request->validate([
            'question_id' => 'required|uuid|exists:m_questions,id',
            'maxmark' => 'sometimes|numeric|min:0',
            'page' => 'sometimes|integer|min:1',
        ]);

        $nextSlot = MQuizSlot::where('quiz_id', $quizId)->max('slot') + 1;

        $slot = MQuizSlot::create([
            'quiz_id' => $quizId,
            'slot' => $nextSlot,
            'question_id' => $data['question_id'],
            'maxmark' => $data['maxmark'] ?? 1.00,
            'page' => $data['page'] ?? 1,
        ]);

        return $this->success($slot->load('question'), 201);
    }

    /** GET /quiz/:id/slots — list quiz slots with questions */
    public function indexSlots(string $quizId)
    {
        $slots = MQuizSlot::where('quiz_id', $quizId)
            ->with(['question.answers'])
            ->orderBy('slot')
            ->get();
        return $this->success($slots);
    }

    /** DELETE /quiz/:id/slots/:slot — remove a question from quiz */
    public function removeSlot(string $quizId, int $slot)
    {
        MQuizSlot::where('quiz_id', $quizId)->where('slot', $slot)->delete();
        return $this->success(null);
    }

    /** POST /quiz/:id/attempt/start — start a quiz attempt (spec §9.5) */
    public function startAttempt(Request $request, string $quizId)
    {
        $quiz = Quiz::findOrFail($quizId);
        $userId = $request->user()->id;

        // Check if quiz is open
        if ($quiz->open_at && now()->lt($quiz->open_at)) {
            return $this->error('QUIZ_NOT_OPEN', 'This quiz is not yet open.', 403);
        }
        if ($quiz->close_at && now()->gt($quiz->close_at)) {
            return $this->error('QUIZ_CLOSED', 'This quiz has closed.', 403);
        }

        // Check attempt limit
        if ($quiz->attempts > 0) {
            $attemptCount = MQuizAttempt::where('quiz_id', $quizId)
                ->where('user_id', $userId)
                ->count();
            if ($attemptCount >= $quiz->attempts) {
                return $this->error('MAX_ATTEMPTS', 'Maximum attempts reached.', 403);
            }
        }

        $nextAttempt = MQuizAttempt::where('quiz_id', $quizId)
            ->where('user_id', $userId)
            ->max('attempt') + 1;

        $attempt = MQuizAttempt::create([
            'quiz_id' => $quizId,
            'user_id' => $userId,
            'attempt' => $nextAttempt,
            'state' => MQuizAttempt::STATE_INPROGRESS,
            'timestart' => now(),
        ]);

        // Create question usage
        $usage = MQuestionUsage::create([
            'component' => 'mod_quiz',
            'context' => 'quiz_attempt_' . $attempt->id,
            'behaviour' => 'deferredfeedback',
        ]);

        // Create question attempts for each slot
        $slots = MQuizSlot::where('quiz_id', $quizId)->orderBy('slot')->get();
        foreach ($slots as $slot) {
            $qa = MQuestionAttempt::create([
                'question_usage_id' => $usage->id,
                'slot' => $slot->slot,
                'question_id' => $slot->question_id,
                'maxmark' => $slot->maxmark,
            ]);

            MQuestionAttemptStep::create([
                'question_attempt_id' => $qa->id,
                'state' => 'todo',
                'user_id' => $userId,
            ]);
        }

        return $this->success([
            'attempt' => $attempt,
            'usage_id' => $usage->id,
        ], 201);
    }

    /** POST /quiz/:id/attempt/process — save answers during attempt */
    public function processAttempt(Request $request, string $quizId, string $attemptId)
    {
        $data = $request->validate([
            'answers' => 'required|array',
            'answers.*.slot' => 'required|integer',
            'answers.*.answer' => 'required|string',
        ]);

        $attempt = MQuizAttempt::where('quiz_id', $quizId)
            ->where('id', $attemptId)
            ->where('user_id', $request->user()->id)
            ->where('state', MQuizAttempt::STATE_INPROGRESS)
            ->firstOrFail();

        // Update question attempt steps
        foreach ($data['answers'] as $answer) {
            $qa = MQuestionAttempt::where('question_usage_id', function ($q) use ($attemptId) {
                $q->where('context', 'quiz_attempt_' . $attemptId)
                  ->from('m_question_usages');
            })->where('slot', $answer['slot'])->first();

            if ($qa) {
                MQuestionAttemptStep::create([
                    'question_attempt_id' => $qa->id,
                    'state' => 'complete',
                    'user_id' => $request->user()->id,
                ]);

                $qa->update(['responsesummary' => $answer['answer']]);
            }
        }

        return $this->success(['saved' => true]);
    }

    /** POST /quiz/:id/attempt/finish — submit and grade the attempt */
    public function finishAttempt(Request $request, string $quizId, string $attemptId)
    {
        $attempt = MQuizAttempt::where('quiz_id', $quizId)
            ->where('id', $attemptId)
            ->where('user_id', $request->user()->id)
            ->where('state', MQuizAttempt::STATE_INPROGRESS)
            ->firstOrFail();

        $quiz = Quiz::findOrFail($quizId);

        // Check timer — if overdue, mark as overdue
        if ($quiz->duration_minutes > 0) {
            $elapsed = now()->diffInMinutes($attempt->timestart);
            if ($elapsed > $quiz->duration_minutes + 5) {
                $attempt->update(['state' => MQuizAttempt::STATE_OVERDUE]);
            }
        }

        // Grade the attempt
        $slots = MQuizSlot::where('quiz_id', $quizId)->orderBy('slot')->get();
        $totalMarks = 0;
        $maxMarks = 0;

        foreach ($slots as $slot) {
            $maxMarks += $slot->maxmark;
            $qa = MQuestionAttempt::where('question_usage_id', function ($q) use ($attemptId) {
                $q->where('context', 'quiz_attempt_' . $attemptId)->from('m_question_usages');
            })->where('slot', $slot->slot)->first();

            if ($qa && $qa->responsesummary) {
                // Auto-grade: check against correct answers
                $correctAnswers = MQuestionAnswer::where('question_id', $slot->question_id)
                    ->where('fraction', '>', 0)
                    ->get();

                $isCorrect = false;
                foreach ($correctAnswers as $correct) {
                    if (strtolower(trim($qa->responsesummary)) === strtolower(trim($correct->answer))) {
                        $isCorrect = true;
                        $totalMarks += $slot->maxmark * $correct->fraction;
                        break;
                    }
                }

                // Update question attempt step with grade
                MQuestionAttemptStep::create([
                    'question_attempt_id' => $qa->id,
                    'state' => 'graded',
                    'user_id' => $request->user()->id,
                    'fraction' => $isCorrect ? 1.0 : 0.0,
                ]);
            }
        }

        $attempt->update([
            'state' => MQuizAttempt::STATE_FINISHED,
            'timefinish' => now(),
            'sumgrades' => $totalMarks,
        ]);

        // Update quiz grade based on grademethod
        $this->updateQuizGrade($quizId, $request->user()->id, $quiz->grademethod);

        return $this->success([
            'attempt' => $attempt->fresh(),
            'score' => $totalMarks,
            'max' => $maxMarks,
        ]);
    }

    /** GET /quiz/:id/attempts — list attempts for a quiz */
    public function indexAttempts(string $quizId)
    {
        $attempts = MQuizAttempt::where('quiz_id', $quizId)
            ->with('user:id,full_name,email')
            ->orderBy('attempt', 'desc')
            ->get();
        return $this->success($attempts);
    }

    /** GET /quiz/:id/my-attempts — student's own attempts */
    public function myAttempts(Request $request, string $quizId)
    {
        $attempts = MQuizAttempt::where('quiz_id', $quizId)
            ->where('user_id', $request->user()->id)
            ->orderBy('attempt', 'desc')
            ->get();
        return $this->success($attempts);
    }

    /**
     * Update quiz grade based on grademethod (highest, average, first, last).
     */
    private function updateQuizGrade(string $quizId, string $userId, string $grademethod): void
    {
        $attempts = MQuizAttempt::where('quiz_id', $quizId)
            ->where('user_id', $userId)
            ->whereNotNull('sumgrades')
            ->orderBy('attempt')
            ->get();

        if ($attempts->isEmpty()) return;

        $grade = match ($grademethod) {
            'highest' => $attempts->max('sumgrades'),
            'average' => $attempts->avg('sumgrades'),
            'first' => $attempts->first()->sumgrades,
            'last' => $attempts->last()->sumgrades,
            default => $attempts->max('sumgrades'),
        };

        MQuizGrade::updateOrCreate(
            ['quiz_id' => $quizId, 'user_id' => $userId],
            ['grade' => $grade, 'timemodified' => now()]
        );
    }
}
