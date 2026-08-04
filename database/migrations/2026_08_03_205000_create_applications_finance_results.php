<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('applicant_id');
            $table->uuid('program_id');
            $table->string('mode', 3); // ODL, DAY, BRD
            $table->json('form_data')->nullable();
            $table->string('pay_slip_url', 255)->nullable();
            $table->string('status', 15)->default('SUBMITTED'); // SUBMITTED, UNDER_REVIEW, ACCEPTED, REJECTED
            $table->uuid('reviewed_by')->nullable();
            $table->timestamps();

            $table->foreign('applicant_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('program_id')->references('id')->on('programs')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['program_id', 'status']);
        });

        Schema::create('fee_structures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('program_id');
            $table->string('mode', 3);
            $table->string('item', 100);
            $table->decimal('amount', 12, 2);
            $table->string('academic_year', 9); // e.g. 2025/2026
            $table->timestamps();

            $table->foreign('program_id')->references('id')->on('programs')->cascadeOnDelete();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->string('fee_item', 100);
            $table->decimal('amount', 12, 2);
            $table->string('control_number', 50)->nullable();
            $table->string('status', 10)->default('PENDING'); // PENDING, CONFIRMED
            $table->timestamp('paid_at')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('course_id');
            $table->integer('ca_marks')->default(0);
            $table->integer('exam_marks')->default(0);
            $table->integer('total')->default(0);
            $table->string('grade', 2)->nullable();
            $table->string('academic_year', 9);
            $table->string('semester', 2);
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });

        Schema::create('debts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->string('description', 150);
            $table->decimal('amount_due', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debts');
        Schema::dropIfExists('results');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('applications');
    }
};
