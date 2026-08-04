<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('title', 150);
            $table->uuid('program_id');
            $table->uuid('subject_id')->nullable();
            $table->unsignedSmallInteger('year');
            $table->string('semester', 2); // I, II
            $table->unsignedSmallInteger('credits');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('program_id')->references('id')->on('programs')->cascadeOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->nullOnDelete();

            $table->index(['program_id']);
            $table->index(['year', 'semester']);
        });

        Schema::create('course_tutors', function (Blueprint $table) {
            $table->uuid('course_id');
            $table->uuid('tutor_id');
            $table->string('role')->default('LEAD'); // LEAD, ASSISTANT
            $table->timestamps();

            $table->primary(['course_id', 'tutor_id']);
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('tutor_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('course_id');
            $table->timestamp('enrolled_at')->useCurrent();
            $table->string('status')->default('ACTIVE'); // ACTIVE, COMPLETED, DROPPED
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->unique(['student_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('course_tutors');
        Schema::dropIfExists('courses');
    }
};
