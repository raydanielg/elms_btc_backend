<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MQuestionCategory;
use App\Models\MQuestion;
use App\Models\MQuestionAnswer;
use App\Models\MQuestionBankEntry;
use App\Models\MQuestionVersion;

class QuestionBankController extends Controller
{
    /** GET /question-categories — list categories (spec §10.2) */
    public function indexCategories(Request $request)
    {
        $query = MQuestionCategory::withCount('questions');

        if ($request->get('context_id')) {
            $query->where('context_id', $request->get('context_id'));
        }
        if ($request->get('parent_id')) {
            $query->where('parent_id', $request->get('parent_id'));
        }

        $categories = $query->orderBy('sortorder')->get();
        return $this->success($categories);
    }

    /** POST /question-categories — create a category */
    public function createCategory(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'parent_id' => 'nullable|uuid|exists:m_question_categories,id',
            'context_id' => 'nullable|uuid',
            'info' => 'nullable|string',
            'sortorder' => 'sometimes|integer',
        ]);

        $category = MQuestionCategory::create($data);
        return $this->success($category, 201);
    }

    /** GET /questions — list questions (spec §10.2) */
    public function indexQuestions(Request $request)
    {
        $query = MQuestion::with(['answers', 'category']);

        if ($request->get('category')) {
            $query->where('category_id', $request->get('category'));
        }
        if ($request->get('qtype')) {
            $query->where('qtype', $request->get('qtype'));
        }
        if ($request->get('hidden') !== null) {
            $query->where('hidden', $request->boolean('hidden'));
        } else {
            $query->where('hidden', false);
        }

        $questions = $query->orderBy('name')->paginate(50);
        return $this->paginated($questions);
    }

    /** POST /questions — create a question with answers (spec §10.2) */
    public function createQuestion(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'required|uuid|exists:m_question_categories,id',
            'qtype' => 'required|string|in:multichoice,truefalse,shortanswer,essay,numerical,matching,description',
            'name' => 'required|string|max:200',
            'questiontext' => 'required|string',
            'generalfeedback' => 'nullable|string',
            'defaultmark' => 'sometimes|numeric|min:0',
            'penalty' => 'sometimes|numeric|min:0',
            'hidden' => 'sometimes|boolean',
            'answers' => 'sometimes|array',
            'answers.*.answer' => 'required|string',
            'answers.*.fraction' => 'required|numeric|min:0|max:1',
            'answers.*.feedback' => 'nullable|string',
        ]);

        $question = MQuestion::create($data);

        // Create answers
        if (!empty($data['answers'])) {
            foreach ($data['answers'] as $i => $answerData) {
                MQuestionAnswer::create([
                    'question_id' => $question->id,
                    'answer_no' => $i + 1,
                    'answer' => $answerData['answer'],
                    'fraction' => $answerData['fraction'],
                    'feedback' => $answerData['feedback'] ?? null,
                ]);
            }
        }

        // Create bank entry + version
        $bankEntry = MQuestionBankEntry::create([
            'question_category_id' => $data['category_id'],
        ]);
        MQuestionVersion::create([
            'bank_entry_id' => $bankEntry->id,
            'question_id' => $question->id,
            'version' => 1,
        ]);

        return $this->success($question->load('answers'), 201);
    }

    /** PATCH /questions/:id — update (creates a new version) (spec §10.2) */
    public function updateQuestion(Request $request, string $id)
    {
        $question = MQuestion::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:200',
            'questiontext' => 'sometimes|string',
            'generalfeedback' => 'nullable|string',
            'defaultmark' => 'sometimes|numeric|min:0',
            'penalty' => 'sometimes|numeric|min:0',
            'hidden' => 'sometimes|boolean',
            'category_id' => 'sometimes|uuid|exists:m_question_categories,id',
        ]);

        $question->update($data);

        // Create new version if content changed
        if ($request->has('questiontext') || $request->has('answers')) {
            $bankEntry = MQuestionBankEntry::whereHas('versions', function ($q) use ($id) {
                $q->where('question_id', $id);
            })->first();

            if ($bankEntry) {
                $latestVersion = MQuestionVersion::where('bank_entry_id', $bankEntry->id)->max('version');
                MQuestionVersion::create([
                    'bank_entry_id' => $bankEntry->id,
                    'question_id' => $id,
                    'version' => $latestVersion + 1,
                ]);
            }
        }

        // Update answers if provided
        if ($request->has('answers')) {
            $question->answers()->delete();
            foreach ($request->get('answers') as $i => $answerData) {
                MQuestionAnswer::create([
                    'question_id' => $question->id,
                    'answer_no' => $i + 1,
                    'answer' => $answerData['answer'],
                    'fraction' => $answerData['fraction'],
                    'feedback' => $answerData['feedback'] ?? null,
                ]);
            }
        }

        return $this->success($question->fresh('answers'));
    }

    /** DELETE /questions/:id — delete a question */
    public function deleteQuestion(string $id)
    {
        MQuestion::findOrFail($id)->delete();
        return $this->success(null);
    }

    /** GET /questions/:id — view a question with answers */
    public function showQuestion(string $id)
    {
        $question = MQuestion::with(['answers', 'category'])->findOrFail($id);
        return $this->success($question);
    }
}
