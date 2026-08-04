<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enrolment method instances per course (spec §6.1)
        Schema::create('m_enrol', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('enrol', 30)->comment('manual, self, cohort, payment, guest, meta');
            $table->uuid('course_id');
            $table->string('status', 10)->default('ENABLED')->comment('ENABLED, DISABLED');
            $table->uuid('role_id')->nullable()->comment('role granted upon enrolment');
            $table->timestamp('enrolstartdate')->nullable();
            $table->timestamp('enrolenddate')->nullable();
            $table->integer('enrolperiod')->nullable()->comment('duration in seconds, 0 = unlimited');
            $table->text('customint1')->nullable();
            $table->text('customchar1')->nullable()->comment('e.g. enrolment key for self method');
            $table->text('customchar2')->nullable()->comment('e.g. fee item for payment method');
            $table->integer('sortorder')->default(0);
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->index(['course_id', 'enrol']);
        });

        // User enrolments — separate from role (spec §6.2)
        Schema::create('m_user_enrolments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('enrol_id');
            $table->uuid('user_id');
            $table->string('status', 10)->default('ACTIVE')->comment('ACTIVE, SUSPENDED');
            $table->timestamp('timestart')->nullable();
            $table->timestamp('timeend')->nullable();
            $table->timestamp('timecreated')->useCurrent();
            $table->timestamps();

            $table->foreign('enrol_id')->references('id')->on('m_enrol')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['enrol_id', 'user_id']);
        });

        // Groups (spec §7.1)
        Schema::create('m_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->string('name', 100);
            $table->string('idnumber', 100)->nullable();
            $table->string('enrolmentkey', 50)->nullable();
            $table->string('picture', 255)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->index('course_id');
        });

        // Group members (spec §7.1)
        Schema::create('m_groups_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('group_id');
            $table->uuid('user_id');
            $table->timestamp('timeadded')->useCurrent();

            $table->foreign('group_id')->references('id')->on('m_groups')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['group_id', 'user_id']);
        });

        // Groupings — sets of groups (spec §7.1)
        Schema::create('m_groupings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });

        // Grouping → Group association (spec §7.1)
        Schema::create('m_groupings_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('grouping_id');
            $table->uuid('group_id');

            $table->foreign('grouping_id')->references('id')->on('m_groupings')->cascadeOnDelete();
            $table->foreign('group_id')->references('id')->on('m_groups')->cascadeOnDelete();
            $table->unique(['grouping_id', 'group_id']);
        });

        // Cohorts — site/category-wide user sets (spec §8.1)
        Schema::create('m_cohorts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('context_id')->nullable()->comment('system or category context');
            $table->string('name', 150);
            $table->string('idnumber', 100)->nullable();
            $table->boolean('visible')->default(true);
            $table->text('description')->nullable();
            $table->string('component', 100)->nullable()->comment('e.g. enrol_cohort, core');
            $table->timestamps();

            $table->index('context_id');
        });

        // Cohort members (spec §8.1)
        Schema::create('m_cohort_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cohort_id');
            $table->uuid('user_id');
            $table->timestamp('timeadded')->useCurrent();

            $table->foreign('cohort_id')->references('id')->on('m_cohorts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['cohort_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_cohort_members');
        Schema::dropIfExists('m_cohorts');
        Schema::dropIfExists('m_groupings_groups');
        Schema::dropIfExists('m_groupings');
        Schema::dropIfExists('m_groups_members');
        Schema::dropIfExists('m_groups');
        Schema::dropIfExists('m_user_enrolments');
        Schema::dropIfExists('m_enrol');
    }
};
