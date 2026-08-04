<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Context hierarchy — the backbone of the permission system (spec §2)
        Schema::create('m_contexts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->smallInteger('contextlevel')->comment('10=SYSTEM, 30=USER, 40=COURSECAT, 50=COURSE, 70=MODULE, 80=BLOCK');
            $table->uuid('instanceid')->nullable()->comment('ID within that level (e.g. course.id for COURSE)');
            $table->string('path', 255)->default('')->comment('Slash-separated ancestor IDs e.g. /1/12/45/230');
            $table->smallInteger('depth')->default(0);
            $table->timestamps();

            $table->unique(['contextlevel', 'instanceid']);
            $table->index('path');
        });

        // Roles — granular RBAC (spec §3.1)
        Schema::create('m_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('shortname', 50)->unique()->comment('manager, editingteacher, teacher, student, guest');
            $table->string('archetype', 50)->nullable()->comment('maps to Butimba role enum');
            $table->text('description')->nullable();
            $table->integer('sortorder')->default(0);
            $table->timestamps();
        });

        // Capabilities — master list of atomic permissions (spec §3.1)
        Schema::create('m_capabilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100)->unique()->comment('e.g. mod/quiz:attempt, moodle/course:manageactivities');
            $table->smallInteger('contextlevel')->comment('where it applies (10/40/50/70)');
            $table->integer('riskbitmask')->default(0)->comment('RISK_XSS=1, RISK_CONFIG=2, RISK_PERSONAL=4, RISK_SPAM=8, RISK_DATALOSS=16');
            $table->string('component', 100)->nullable()->comment('e.g. mod_quiz, core_course');
            $table->timestamps();
        });

        // Role capabilities — what a role can do, per context (spec §3.1)
        Schema::create('m_role_capabilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('role_id');
            $table->uuid('capability_id');
            $table->uuid('context_id');
            $table->smallInteger('permission')->default(0)->comment('ALLOW=1, PREVENT=-1, PROHIBIT=-1000, INHERIT=0');
            $table->timestamp('timemodified')->useCurrent();
            $table->uuid('modifier_id')->nullable();

            $table->foreign('role_id')->references('id')->on('m_roles')->cascadeOnDelete();
            $table->foreign('capability_id')->references('id')->on('m_capabilities')->cascadeOnDelete();
            $table->foreign('context_id')->references('id')->on('m_contexts')->cascadeOnDelete();
            $table->unique(['role_id', 'capability_id', 'context_id']);
        });

        // Role assignments — who has which role, where (spec §3.1)
        Schema::create('m_role_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('role_id');
            $table->uuid('context_id');
            $table->uuid('user_id');
            $table->timestamp('timemodified')->useCurrent();
            $table->uuid('modifier_id')->nullable();

            $table->foreign('role_id')->references('id')->on('m_roles')->cascadeOnDelete();
            $table->foreign('context_id')->references('id')->on('m_contexts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['role_id', 'context_id', 'user_id']);
            $table->index(['user_id', 'context_id']);
        });

        // Role context levels — which levels a role may be assigned at (spec §3.1)
        Schema::create('m_role_context_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('role_id');
            $table->smallInteger('contextlevel');

            $table->foreign('role_id')->references('id')->on('m_roles')->cascadeOnDelete();
            $table->unique(['role_id', 'contextlevel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_role_context_levels');
        Schema::dropIfExists('m_role_assignments');
        Schema::dropIfExists('m_role_capabilities');
        Schema::dropIfExists('m_capabilities');
        Schema::dropIfExists('m_roles');
        Schema::dropIfExists('m_contexts');
    }
};
