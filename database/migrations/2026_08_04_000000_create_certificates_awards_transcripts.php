<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Certificate templates (spec §2.1)
        Schema::create('m_certificate_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->string('type', 20)->comment('COMPLETION, AWARD, TRANSCRIPT, PARTICIPATION');
            $table->uuid('context_id')->nullable()->comment('site/category/course scope');
            $table->string('page_size', 10)->default('A4')->comment('A4, A5, LETTER');
            $table->string('orientation', 10)->default('PORTRAIT')->comment('PORTRAIT, LANDSCAPE');
            $table->string('trigger_type', 30)->default('MANUAL')->comment('MANUAL, COURSE_COMPLETION, ACTIVITY_COMPLETION, GRADE_THRESHOLD, PROGRAM_COMPLETION, COMPETENCY, COHORT');
            $table->json('trigger_config')->nullable();
            $table->boolean('requires_fees_cleared')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Certificate pages (spec §2.2)
        Schema::create('m_certificate_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('template_id');
            $table->integer('page_no')->default(1);
            $table->integer('width')->default(595)->comment('A4 width in pt');
            $table->integer('height')->default(842)->comment('A4 height in pt');
            $table->string('background_image_url', 255)->nullable();
            $table->integer('margin')->default(40);
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('m_certificate_templates')->cascadeOnDelete();
        });

        // Certificate elements — positioned items on a page (spec §2.3)
        Schema::create('m_certificate_elements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('page_id');
            $table->string('element_type', 30)->comment('static_text, recipient_name, program_name, course_name, teaching_subject, grade, date, verification_code, qr_code, signature_image, seal_logo, watermark, serial_number, custom_field');
            $table->json('data')->nullable()->comment('element-specific config');
            $table->integer('pos_x')->default(0);
            $table->integer('pos_y')->default(0);
            $table->integer('width')->nullable();
            $table->string('font', 100)->default('Helvetica');
            $table->integer('font_size')->default(12);
            $table->string('colour', 20)->default('#000000');
            $table->string('align', 10)->default('LEFT')->comment('LEFT, CENTER, RIGHT');
            $table->string('refpoint', 20)->default('TOP_LEFT')->comment('TOP_LEFT, TOP_CENTER, TOP_RIGHT');
            $table->integer('z_index')->default(0);
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('m_certificate_pages')->cascadeOnDelete();
        });

        // Certificate issues — one per person per award (spec §2.4)
        Schema::create('m_certificate_issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('template_id');
            $table->uuid('user_id')->comment('recipient');
            $table->uuid('course_id')->nullable();
            $table->uuid('program_id')->nullable();
            $table->string('code', 16)->unique()->comment('verification code');
            $table->string('hash', 64)->comment('SHA-256 tamper check');
            $table->string('grade', 50)->nullable()->comment('e.g. Distinction');
            $table->string('classification', 50)->nullable();
            $table->uuid('issued_by');
            $table->timestamp('issued_at')->useCurrent();
            $table->uuid('pdf_fileid')->nullable()->comment('FK to files table');
            $table->string('status', 10)->default('ISSUED')->comment('ISSUED, REVOKED, REISSUED');
            $table->text('revoke_reason')->nullable();
            $table->integer('serial_number')->nullable()->comment('register number per intake');
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('m_certificate_templates')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->nullOnDelete();
            $table->foreign('issued_by')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['template_id', 'user_id', 'course_id']);
        });

        // Certificate verifications — audit of public checks (spec §2.5)
        Schema::create('m_certificate_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 16);
            $table->timestamp('verified_at')->useCurrent();
            $table->string('ip', 45)->nullable();
            $table->string('result', 10)->comment('VALID, INVALID, REVOKED');
        });

        // Awards catalogue — for AWARD type (spec §2.6)
        Schema::create('m_awards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->json('criteria')->nullable()->comment('e.g. {"rule": "top_1_by_grade", "program_id": "..."}');
            $table->uuid('template_id')->nullable()->comment('certificate template to use');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('m_certificate_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_awards');
        Schema::dropIfExists('m_certificate_verifications');
        Schema::dropIfExists('m_certificate_issues');
        Schema::dropIfExists('m_certificate_elements');
        Schema::dropIfExists('m_certificate_pages');
        Schema::dropIfExists('m_certificate_templates');
    }
};
