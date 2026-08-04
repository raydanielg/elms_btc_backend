<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Question categories — shared across quizzes (spec §10.1)
        Schema::create('m_question_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('context_id')->nullable()->comment('system/category/course context');
            $table->uuid('parent_id')->nullable();
            $table->string('name', 150);
            $table->text('info')->nullable();
            $table->integer('sortorder')->default(0);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('m_question_categories')->nullOnDelete();
            $table->index('context_id');
        });

        // Questions — shared bank entries (spec §10.1)
        Schema::create('m_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('category_id');
            $table->string('qtype', 30)->comment('multichoice, truefalse, shortanswer, essay, numerical, matching, description');
            $table->string('name', 200);
            $table->text('questiontext');
            $table->text('generalfeedback')->nullable();
            $table->decimal('defaultmark', 10, 2)->default(1.00);
            $table->decimal('penalty', 5, 2)->default(0.00)->comment('penalty for wrong attempts');
            $table->boolean('hidden')->default(false);
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('m_question_categories')->cascadeOnDelete();
            $table->index(['category_id', 'qtype']);
        });

        // Question answers (spec §10.1)
        Schema::create('m_question_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('question_id');
            $table->integer('answer_no');
            $table->text('answer');
            $table->decimal('fraction', 5, 2)->default(0.00)->comment('0-1 fraction of marks');
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->foreign('question_id')->references('id')->on('m_questions')->cascadeOnDelete();
            $table->index('question_id');
        });

        // Question bank entries + versions (spec §10.1)
        Schema::create('m_question_bank_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('question_category_id');
            $table->integer('idnumber')->nullable();
            $table->timestamps();

            $table->foreign('question_category_id')->references('id')->on('m_question_categories')->cascadeOnDelete();
        });

        Schema::create('m_question_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('bank_entry_id');
            $table->uuid('question_id');
            $table->integer('version')->default(1);
            $table->timestamps();

            $table->foreign('bank_entry_id')->references('id')->on('m_question_bank_entries')->cascadeOnDelete();
            $table->foreign('question_id')->references('id')->on('m_questions')->cascadeOnDelete();
            $table->unique(['bank_entry_id', 'version']);
        });

        // Quiz slots — questions placed in a quiz (spec §9.2)
        Schema::create('m_quiz_slots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('quiz_id');
            $table->integer('slot');
            $table->uuid('question_id');
            $table->decimal('maxmark', 10, 2)->default(1.00);
            $table->integer('page')->default(1);
            $table->timestamps();

            $table->foreign('quiz_id')->references('id')->on('quizzes')->cascadeOnDelete();
            $table->foreign('question_id')->references('id')->on('m_questions')->cascadeOnDelete();
            $table->unique(['quiz_id', 'slot']);
        });

        // Quiz attempts — enhanced (spec §9.2)
        Schema::create('m_quiz_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('quiz_id');
            $table->uuid('user_id');
            $table->integer('attempt')->default(1);
            $table->string('state', 20)->default('inprogress')->comment('inprogress, finished, overdue');
            $table->timestamp('timestart')->useCurrent();
            $table->timestamp('timefinish')->nullable();
            $table->decimal('sumgrades', 10, 2)->nullable();
            $table->timestamps();

            $table->foreign('quiz_id')->references('id')->on('quizzes')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['quiz_id', 'user_id']);
        });

        // Question usages — set of attempts for one activity attempt (spec §11)
        Schema::create('m_question_usages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('component', 100)->comment('mod_quiz, mod_assign');
            $table->string('context', 100)->comment('quiz attempt, assignment grading');
            $table->string('behaviour', 50)->default('deferredfeedback');
            $table->timestamps();
        });

        // Question attempts — one per question in a usage (spec §11)
        Schema::create('m_question_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('question_usage_id');
            $table->integer('slot');
            $table->uuid('question_id');
            $table->decimal('maxmark', 10, 2)->default(1.00);
            $table->decimal('minfraction', 5, 2)->default(0.00);
            $table->decimal('maxfraction', 5, 2)->default(1.00);
            $table->text('responsesummary')->nullable();
            $table->text('rightanswer')->nullable();
            $table->decimal('flagged', 5, 2)->default(0.00);
            $table->timestamps();

            $table->foreign('question_usage_id')->references('id')->on('m_question_usages')->cascadeOnDelete();
            $table->foreign('question_id')->references('id')->on('m_questions')->cascadeOnDelete();
            $table->index('question_usage_id');
        });

        // Question attempt steps — full history of state transitions (spec §11)
        Schema::create('m_question_attempt_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('question_attempt_id');
            $table->string('state', 50)->comment('todo, complete, graded, etc.');
            $table->uuid('user_id')->nullable();
            $table->decimal('fraction', 5, 2)->nullable();
            $table->timestamp('timecreated')->useCurrent();
            $table->timestamps();

            $table->foreign('question_attempt_id')->references('id')->on('m_question_attempts')->cascadeOnDelete();
        });

        // Quiz grades — per user (spec §9.2)
        Schema::create('m_quiz_grades', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('quiz_id');
            $table->uuid('user_id');
            $table->decimal('grade', 10, 2);
            $table->timestamp('timemodified')->useCurrent();
            $table->timestamps();

            $table->foreign('quiz_id')->references('id')->on('quizzes')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['quiz_id', 'user_id']);
        });

        // Add Moodle-style fields to existing quizzes table
        Schema::table('quizzes', function (Blueprint $table) {
            $table->string('grademethod', 20)->default('highest')->comment('highest, average, first, last')->after('close_at');
            $table->integer('attempts')->default(0)->comment('0 = unlimited')->after('grademethod');
            $table->boolean('shuffleanswers')->default(true)->after('attempts');
            $table->integer('questionsperpage')->default(1)->after('shuffleanswers');
            $table->json('reviewoptions')->nullable()->comment('when to show feedback, answers, marks')->after('questionsperpage');
        });

        // Add Moodle-style fields to existing assignments table
        Schema::table('assignments', function (Blueprint $table) {
            $table->timestamp('cutoffdate')->nullable()->after('due_at')->comment('hard deadline, no late after this');
            $table->timestamp('allowsubmissionsfromdate')->nullable()->after('cutoffdate');
            $table->integer('maxattempts')->default(0)->comment('0 = unlimited')->after('allowsubmissionsfromdate');
            $table->boolean('submissiondrafts')->default(false)->after('maxattempts');
            $table->boolean('teamsubmission')->default(false)->after('submissiondrafts');
            $table->boolean('blindmarking')->default(false)->after('teamsubmission');
            $table->boolean('markingworkflow')->default(false)->after('blindmarking');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['cutoffdate', 'allowsubmissionsfromdate', 'maxattempts', 'submissiondrafts', 'teamsubmission', 'blindmarking', 'markingworkflow']);
        });
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn(['grademethod', 'attempts', 'shuffleanswers', 'questionsperpage', 'reviewoptions']);
        });
        Schema::dropIfExists('m_quiz_grades');
        Schema::dropIfExists('m_question_attempt_steps');
        Schema::dropIfExists('m_question_attempts');
        Schema::dropIfExists('m_question_usages');
        Schema::dropIfExists('m_quiz_attempts');
        Schema::dropIfExists('m_quiz_slots');
        Schema::dropIfExists('m_question_versions');
        Schema::dropIfExists('m_question_bank_entries');
        Schema::dropIfExists('m_question_answers');
        Schema::dropIfExists('m_questions');
        Schema::dropIfExists('m_question_categories');
    }
};
