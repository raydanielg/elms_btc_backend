<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\MQuestionCategory;
use App\Models\MQuestion;
use App\Models\MQuestionAnswer;
use App\Models\MQuizSlot;
use App\Models\MQuizAttempt;
use App\Models\MQuizGrade;
use Illuminate\Foundation\Testing\RefreshDatabase;

class QuestionBankQuizEngineTest extends TestCase
{
    use RefreshDatabase;

    private User $tutor;
    private User $student;
    private string $tutorToken;
    private string $studentToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tutor = User::factory()->create(['role' => 'TUTOR', 'status' => 'ACTIVE']);
        $this->student = User::factory()->create(['role' => 'STUDENT', 'status' => 'ACTIVE']);
        $this->tutorToken = $this->tutor->createToken('test')->plainTextToken;
        $this->studentToken = $this->student->createToken('test')->plainTextToken;
    }

    // ===== Question Bank =====

    public function test_create_question_category(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tutorToken)
            ->postJson('/api/question-categories', [
                'name' => 'Math Questions',
                'info' => 'Basic math questions',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Math Questions');
    }

    public function test_create_question_with_answers(): void
    {
        $cat = MQuestionCategory::create(['name' => 'Test Category']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tutorToken)
            ->postJson('/api/questions', [
                'category_id' => $cat->id,
                'qtype' => 'multichoice',
                'name' => 'Capital of Tanzania',
                'questiontext' => 'What is the capital of Tanzania?',
                'defaultmark' => 2,
                'answers' => [
                    ['answer' => 'Dar es Salaam', 'fraction' => 0, 'feedback' => 'Wrong'],
                    ['answer' => 'Dodoma', 'fraction' => 1, 'feedback' => 'Correct!'],
                    ['answer' => 'Arusha', 'fraction' => 0, 'feedback' => 'Wrong'],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.qtype', 'multichoice')
            ->assertJsonPath('data.answers.1.answer', 'Dodoma');

        $this->assertDatabaseHas('m_question_answers', [
            'question_id' => $response->json('data.id'),
            'answer' => 'Dodoma',
            'fraction' => 1.00,
        ]);
    }

    public function test_list_questions_by_category(): void
    {
        $cat = MQuestionCategory::create(['name' => 'Test Cat']);
        MQuestion::create([
            'category_id' => $cat->id, 'qtype' => 'truefalse',
            'name' => 'Q1', 'questiontext' => 'Is the sky blue?',
        ]);
        MQuestion::create([
            'category_id' => $cat->id, 'qtype' => 'shortanswer',
            'name' => 'Q2', 'questiontext' => 'Name a planet',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tutorToken)
            ->getJson("/api/questions?category={$cat->id}");

        $response->assertStatus(200);
    }

    public function test_update_question_creates_new_version(): void
    {
        $cat = MQuestionCategory::create(['name' => 'Test Cat']);
        $question = MQuestion::create([
            'category_id' => $cat->id, 'qtype' => 'shortanswer',
            'name' => 'Q1', 'questiontext' => 'Original question',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tutorToken)
            ->patchJson("/api/questions/{$question->id}", [
                'questiontext' => 'Updated question text',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('m_questions', [
            'id' => $question->id,
            'questiontext' => 'Updated question text',
        ]);
    }

    // ===== Quiz Engine =====

    public function test_add_question_to_quiz_slot(): void
    {
        $course = Course::factory()->create();
        $quiz = Quiz::create([
            'course_id' => $course->id, 'title' => 'Test Quiz',
            'duration_minutes' => 30, 'total_marks' => 10,
        ]);
        $cat = MQuestionCategory::create(['name' => 'Test']);
        $question = MQuestion::create([
            'category_id' => $cat->id, 'qtype' => 'multichoice',
            'name' => 'Q1', 'questiontext' => 'Test question',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tutorToken)
            ->postJson("/api/quiz/{$quiz->id}/slots", [
                'question_id' => $question->id,
                'maxmark' => 2,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.slot', 1)
            ->assertJsonPath('data.maxmark', 2);
    }

    public function test_start_quiz_attempt(): void
    {
        $course = Course::factory()->create();
        $quiz = Quiz::create([
            'course_id' => $course->id, 'title' => 'Test Quiz',
            'duration_minutes' => 30, 'total_marks' => 10,
        ]);
        $cat = MQuestionCategory::create(['name' => 'Test']);
        $question = MQuestion::create([
            'category_id' => $cat->id, 'qtype' => 'truefalse',
            'name' => 'Q1', 'questiontext' => 'Is 2+2=4?',
        ]);
        MQuestionAnswer::create([
            'question_id' => $question->id, 'answer_no' => 1,
            'answer' => 'True', 'fraction' => 1,
        ]);
        MQuizSlot::create([
            'quiz_id' => $quiz->id, 'slot' => 1,
            'question_id' => $question->id, 'maxmark' => 1, 'page' => 1,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
            ->postJson("/api/quiz/{$quiz->id}/attempt/start");

        $response->assertStatus(201)
            ->assertJsonPath('data.attempt.state', 'inprogress');
    }

    public function test_quiz_not_open_yet(): void
    {
        $course = Course::factory()->create();
        $quiz = Quiz::create([
            'course_id' => $course->id, 'title' => 'Future Quiz',
            'duration_minutes' => 30, 'total_marks' => 10,
            'open_at' => now()->addDays(1),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
            ->postJson("/api/quiz/{$quiz->id}/attempt/start");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'QUIZ_NOT_OPEN');
    }

    public function test_quiz_closed(): void
    {
        $course = Course::factory()->create();
        $quiz = Quiz::create([
            'course_id' => $course->id, 'title' => 'Past Quiz',
            'duration_minutes' => 30, 'total_marks' => 10,
            'close_at' => now()->subDays(1),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
            ->postJson("/api/quiz/{$quiz->id}/attempt/start");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'QUIZ_CLOSED');
    }

    public function test_finish_attempt_grades_and_updates_quiz_grade(): void
    {
        $course = Course::factory()->create();
        $quiz = Quiz::create([
            'course_id' => $course->id, 'title' => 'Test Quiz',
            'duration_minutes' => 30, 'total_marks' => 2,
            'grademethod' => 'highest',
        ]);
        $cat = MQuestionCategory::create(['name' => 'Test']);
        $question = MQuestion::create([
            'category_id' => $cat->id, 'qtype' => 'truefalse',
            'name' => 'Q1', 'questiontext' => 'Is 2+2=4?',
        ]);
        MQuestionAnswer::create([
            'question_id' => $question->id, 'answer_no' => 1,
            'answer' => 'True', 'fraction' => 1,
        ]);
        MQuizSlot::create([
            'quiz_id' => $quiz->id, 'slot' => 1,
            'question_id' => $question->id, 'maxmark' => 2, 'page' => 1,
        ]);

        // Start attempt
        $startResp = $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
            ->postJson("/api/quiz/{$quiz->id}/attempt/start");
        $startResp->assertStatus(201);
        $attemptId = $startResp->json('data.attempt.id');

        // Process answers
        $processResp = $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
            ->postJson("/api/quiz/{$quiz->id}/attempt/{$attemptId}/process", [
                'answers' => [
                    ['slot' => 1, 'answer' => 'True'],
                ],
            ]);
        $processResp->assertStatus(200);

        // Finish attempt
        $finishResp = $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
            ->postJson("/api/quiz/{$quiz->id}/attempt/{$attemptId}/finish");
        $finishResp->assertStatus(200)
            ->assertJsonPath('data.attempt.state', 'finished');

        // Verify quiz grade was created
        $this->assertDatabaseHas('m_quiz_grades', [
            'quiz_id' => $quiz->id,
            'user_id' => $this->student->id,
        ]);
    }

    public function test_max_attempts_enforced(): void
    {
        $course = Course::factory()->create();
        $quiz = Quiz::create([
            'course_id' => $course->id, 'title' => 'Limited Quiz',
            'duration_minutes' => 10, 'total_marks' => 5,
            'attempts' => 1,
        ]);

        // First attempt
        $r1 = $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
            ->postJson("/api/quiz/{$quiz->id}/attempt/start");
        $r1->assertStatus(201);

        // Second attempt should fail
        $r2 = $this->withHeader('Authorization', 'Bearer ' . $this->studentToken)
            ->postJson("/api/quiz/{$quiz->id}/attempt/start");
        $r2->assertStatus(403)
            ->assertJsonPath('error.code', 'MAX_ATTEMPTS');
    }
}
