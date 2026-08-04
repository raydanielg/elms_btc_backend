<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Grade items — columns in the gradebook (spec §12.1)
        Schema::create('m_grade_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->uuid('category_id')->nullable()->comment('parent grade category');
            $table->string('itemname', 200)->nullable();
            $table->string('itemtype', 30)->comment('course, category, mod, manual');
            $table->string('itemmodule', 50)->nullable()->comment('assign, quiz, etc.');
            $table->uuid('iteminstance')->nullable()->comment('module instance ID');
            $table->integer('itemnumber')->default(0);
            $table->decimal('grademax', 10, 2)->default(100.00);
            $table->decimal('grademin', 10, 2)->default(0.00);
            $table->decimal('gradepass', 10, 2)->default(0.00);
            $table->decimal('multfactor', 10, 4)->default(1.0000);
            $table->decimal('aggregationcoef', 10, 4)->default(0.0000)->comment('weight for weighted mean');
            $table->integer('sortorder')->default(0);
            $table->boolean('hidden')->default(false);
            $table->boolean('locked')->default(false);
            $table->boolean('needsupdate')->default(false);
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->index(['course_id', 'itemtype']);
        });

        // Grade categories — aggregation groups (spec §12.1)
        Schema::create('m_grade_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->string('fullname', 200);
            $table->string('aggregation', 30)->default('natural')->comment('natural, weighted, mean, sum, min, max');
            $table->boolean('aggregateonlygraded')->default(false);
            $table->boolean('aggregateoutcomes')->default(false);
            $table->boolean('droplow')->default(false);
            $table->integer('keephigh')->default(0);
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });

        // Add category_id FK to grade_items after categories table exists
        Schema::table('m_grade_items', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('m_grade_categories')->nullOnDelete();
        });

        // Grade grades — actual student grades (spec §12.1)
        Schema::create('m_grade_grades', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('itemid');
            $table->uuid('user_id');
            $table->decimal('rawgrade', 10, 2)->nullable();
            $table->decimal('rawgrademax', 10, 2)->default(100.00);
            $table->decimal('rawgrademin', 10, 2)->default(0.00);
            $table->decimal('finalgrade', 10, 2)->nullable();
            $table->boolean('hidden')->default(false);
            $table->boolean('locked')->default(false);
            $table->boolean('overridden')->default(false);
            $table->boolean('excluded')->default(false);
            $table->text('feedback')->nullable();
            $table->timestamp('timemodified')->useCurrent();
            $table->timestamps();

            $table->foreign('itemid')->references('id')->on('m_grade_items')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['itemid', 'user_id']);
        });

        // Grade history (spec §12.1)
        Schema::create('m_grade_grades_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('itemid');
            $table->uuid('user_id');
            $table->uuid('loggeduser')->nullable();
            $table->decimal('oldgrade', 10, 2)->nullable();
            $table->decimal('newgrade', 10, 2)->nullable();
            $table->string('source', 50)->nullable();
            $table->timestamp('timemodified')->useCurrent();
        });

        // Scales — custom grading scales (spec §12.2)
        Schema::create('m_scales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id')->nullable()->comment('null = global scale');
            $table->string('name', 100);
            $table->text('scale')->comment('CSV of scale items');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->nullOnDelete();
        });

        // Grade letters — A/B/C/D/F mapping (spec §12.2)
        Schema::create('m_grade_letters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->string('letter', 10);
            $table->decimal('grade_boundary', 5, 2)->comment('minimum percentage for this letter');
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->unique(['course_id', 'letter']);
        });

        // Course modules completion (spec §14.1)
        Schema::create('m_course_modules_completion', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_module_id');
            $table->uuid('user_id');
            $table->string('completionstate', 20)->default('INCOMPLETE')->comment('INCOMPLETE, COMPLETE, COMPLETE_FAIL');
            $table->timestamp('timemodified')->useCurrent();
            $table->timestamps();

            $table->foreign('course_module_id')->references('id')->on('m_course_modules')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['course_module_id', 'user_id']);
        });

        // Course completion criteria (spec §14.2)
        Schema::create('m_course_completion_criteria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->string('criteriatype', 30)->comment('activity, grade, course, self, date');
            $table->uuid('module_instance')->nullable();
            $table->decimal('gradepass', 10, 2)->nullable();
            $table->timestamp('enrolmentdate')->nullable();
            $table->timestamp('date')->nullable();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });

        // Course completions (spec §14.2)
        Schema::create('m_course_completions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->uuid('user_id');
            $table->string('status', 20)->default('INPROGRESS')->comment('INPROGRESS, COMPLETE, COMPLETE_FAIL');
            $table->timestamp('timecompleted')->nullable();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['course_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_course_completions');
        Schema::dropIfExists('m_course_completion_criteria');
        Schema::dropIfExists('m_course_modules_completion');
        Schema::dropIfExists('m_grade_letters');
        Schema::dropIfExists('m_scales');
        Schema::dropIfExists('m_grade_grades_history');
        Schema::dropIfExists('m_grade_grades');
        Schema::table('m_grade_items', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });
        Schema::dropIfExists('m_grade_items');
        Schema::dropIfExists('m_grade_categories');
    }
};
