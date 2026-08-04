<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Badges (spec §15.1)
        Schema::create('m_badges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('image', 255)->nullable();
            $table->uuid('user_id')->comment('issuer');
            $table->uuid('context_id')->nullable();
            $table->string('type', 20)->default('site')->comment('site, course');
            $table->timestamp('issued_on')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Badge criteria (spec §15.1)
        Schema::create('m_badge_criteria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('badge_id');
            $table->string('criteriatype', 50)->comment('activity, profile, course_completion, manual');
            $table->uuid('module_instance')->nullable();
            $table->json('params')->nullable();
            $table->integer('sortorder')->default(0);
            $table->timestamps();

            $table->foreign('badge_id')->references('id')->on('m_badges')->cascadeOnDelete();
        });

        // Badge issued (spec §15.1)
        Schema::create('m_badge_issued', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('badge_id');
            $table->uuid('user_id');
            $table->timestamp('issued_at')->useCurrent();
            $table->boolean('visible')->default(true);
            $table->timestamps();

            $table->foreign('badge_id')->references('id')->on('m_badges')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['badge_id', 'user_id']);
        });

        // Competency frameworks (spec §16.1)
        Schema::create('m_competency_frameworks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->uuid('context_id')->nullable();
            $table->timestamps();
        });

        // Competencies (spec §16.1)
        Schema::create('m_competencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('framework_id');
            $table->uuid('parent_id')->nullable();
            $table->string('shortname', 100);
            $table->text('description')->nullable();
            $table->integer('sortorder')->default(0);
            $table->timestamps();

            $table->foreign('framework_id')->references('id')->on('m_competency_frameworks')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('m_competencies')->nullOnDelete();
        });

        // User competencies (spec §16.1)
        Schema::create('m_user_competencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('competency_id');
            $table->uuid('user_id');
            $table->string('proficiency', 20)->default('NOT_PROFICIENT')->comment('NOT_PROFICIENT, PROFICIENT');
            $table->integer('rating')->default(0)->comment('0-4 scale');
            $table->uuid('reviewer_id')->nullable();
            $table->string('status', 20)->default('PENDING')->comment('PENDING, REVIEWED, APPROVED');
            $table->timestamps();

            $table->foreign('competency_id')->references('id')->on('m_competencies')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reviewer_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['competency_id', 'user_id']);
        });

        // Learning plans (spec §16.2)
        Schema::create('m_learning_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->uuid('user_id');
            $table->timestamp('duedate')->nullable();
            $table->string('status', 20)->default('DRAFT')->comment('DRAFT, ACTIVE, COMPLETE');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Learning plan competencies
        Schema::create('m_learning_plan_competencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plan_id');
            $table->uuid('competency_id');
            $table->integer('sortorder')->default(0);
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('m_learning_plans')->cascadeOnDelete();
            $table->foreign('competency_id')->references('id')->on('m_competencies')->cascadeOnDelete();
        });

        // Web service tokens (spec §24.1)
        Schema::create('m_webservice_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('token', 128)->unique();
            $table->string('name', 200)->nullable();
            $table->string('service', 100)->comment('mobile, rest, webservice');
            $table->string('iprestriction', 100)->nullable();
            $table->timestamp('validuntil')->nullable();
            $table->timestamp('lastaccess')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Admin config (spec §25.1)
        Schema::create('m_config', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->text('value')->nullable();
            $table->text('description')->nullable();
            $table->string('type', 50)->default('text')->comment('text, number, boolean, select, json');
            $table->json('options')->nullable()->comment('for select type');
            $table->boolean('hidden')->default(false);
            $table->timestamps();

            $table->unique('name');
        });

        // Config plugins (spec §25.1)
        Schema::create('m_config_plugins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('plugin', 100);
            $table->string('name', 200);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['plugin', 'name']);
        });

        // Backup records (spec §26.1)
        Schema::create('m_backups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id')->nullable();
            $table->string('filename', 255);
            $table->string('filepath', 255);
            $table->bigInteger('filesize')->default(0);
            $table->string('type', 20)->default('course')->comment('course, section, activity');
            $table->uuid('user_id')->comment('who created the backup');
            $table->string('status', 20)->default('COMPLETE')->comment('COMPLETE, INPROGRESS, FAILED');
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_backups');
        Schema::dropIfExists('m_config_plugins');
        Schema::dropIfExists('m_config');
        Schema::dropIfExists('m_webservice_tokens');
        Schema::dropIfExists('m_learning_plan_competencies');
        Schema::dropIfExists('m_learning_plans');
        Schema::dropIfExists('m_user_competencies');
        Schema::dropIfExists('m_competencies');
        Schema::dropIfExists('m_competency_frameworks');
        Schema::dropIfExists('m_badge_issued');
        Schema::dropIfExists('m_badge_criteria');
        Schema::dropIfExists('m_badges');
    }
};
