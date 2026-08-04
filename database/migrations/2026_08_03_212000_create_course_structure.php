<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Course categories — nested tree (spec §4.1)
        Schema::create('m_course_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('idnumber', 100)->nullable()->comment('external code e.g. DSE-SCI');
            $table->uuid('parent_id')->nullable();
            $table->string('path', 255)->default('')->comment('slash-separated ancestor IDs');
            $table->smallInteger('depth')->default(1);
            $table->boolean('visible')->default(true);
            $table->integer('sortorder')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('m_course_categories')->nullOnDelete();
            $table->index('parent_id');
            $table->index('path');
        });

        // Course format options — per-course format settings (spec §4.3)
        Schema::create('m_course_format_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->string('format', 30)->default('topics')->comment('topics, weeks, singleactivity, social');
            $table->string('name', 100);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->unique(['course_id', 'name']);
        });

        // Course sections — topics/weeks within a course (spec §4.3)
        Schema::create('m_course_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->integer('section')->default(0)->comment('ordinal position, 0 = general');
            $table->string('name', 150)->nullable();
            $table->text('summary')->nullable();
            $table->text('sequence')->nullable()->comment('CSV of course_module IDs in order');
            $table->boolean('visible')->default(true);
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->unique(['course_id', 'section']);
        });

        // Module registry — types of activities/resources (spec §5.1)
        Schema::create('m_modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 50)->unique()->comment('assign, quiz, forum, resource, page, url, folder, book, label, etc.');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Course modules — placement of an activity in a section (spec §5.2)
        Schema::create('m_course_modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->uuid('module_id')->comment('references m_modules (the type)');
            $table->uuid('instance_id')->nullable()->comment('ID in the module\'s own table (e.g. assignments.id)');
            $table->uuid('section_id')->nullable();
            $table->string('idnumber', 100)->nullable();
            $table->boolean('visible')->default(true);
            $table->boolean('visibleoncoursepage')->default(true);
            $table->string('completion', 20)->default('NONE')->comment('NONE, MANUAL, AUTO');
            $table->integer('completiongradeitemnumber')->nullable();
            $table->boolean('completionview')->default(false);
            $table->timestamp('completionexpected')->nullable();
            $table->json('availability')->nullable()->comment('access restrictions: date, grade, group, profile, completion');
            $table->string('groupmode', 20)->default('NOGROUPS')->comment('NOGROUPS, SEPARATE, VISIBLE');
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('module_id')->references('id')->on('m_modules')->cascadeOnDelete();
            $table->foreign('section_id')->references('id')->on('m_course_sections')->nullOnDelete();
            $table->index(['course_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_course_modules');
        Schema::dropIfExists('m_modules');
        Schema::dropIfExists('m_course_sections');
        Schema::dropIfExists('m_course_format_options');
        Schema::dropIfExists('m_course_categories');
    }
};
