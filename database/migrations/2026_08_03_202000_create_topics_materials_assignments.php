<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->integer('order_no')->default(0);
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('topic_id');
            $table->string('type', 10); // PDF, SLIDE, VIDEO, LINK
            $table->string('title', 150);
            $table->string('file_url', 255)->nullable();
            $table->uuid('uploaded_by');
            $table->timestamps();

            $table->foreign('topic_id')->references('id')->on('topics')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->string('title', 150);
            $table->text('instructions')->nullable();
            $table->integer('max_marks')->default(100);
            $table->timestamp('open_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->integer('timer_minutes')->nullable();
            $table->boolean('allow_late')->default(false);
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('assignment_id');
            $table->uuid('student_id');
            $table->string('file_url', 255)->nullable();
            $table->text('content')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->integer('marks')->nullable();
            $table->text('feedback')->nullable();
            $table->string('status', 10)->default('ON_TIME'); // ON_TIME, LATE
            $table->timestamps();

            $table->foreign('assignment_id')->references('id')->on('assignments')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['assignment_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('topics');
    }
};
