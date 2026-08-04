<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Message conversations (spec §20.1)
        Schema::create('m_message_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 200)->nullable()->comment('group conversation name');
            $table->boolean('is_group')->default(false);
            $table->uuid('created_by');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });

        // Conversation members (spec §20.1)
        Schema::create('m_message_conversation_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->uuid('user_id');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('last_read_at')->nullable();

            $table->foreign('conversation_id')->references('id')->on('m_message_conversations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['conversation_id', 'user_id']);
        });

        // Messages (spec §20.1)
        Schema::create('m_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->uuid('user_id')->comment('sender');
            $table->text('body');
            $table->uuid('reply_to')->nullable();
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('m_message_conversations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['conversation_id', 'created_at']);
        });

        // Notifications (spec §20.2)
        Schema::create('m_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->comment('recipient');
            $table->string('component', 100)->nullable();
            $table->string('event_type', 100)->nullable();
            $table->string('subject', 200);
            $table->text('body')->nullable();
            $table->string('context_url', 255)->nullable();
            $table->boolean('read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'read']);
        });

        // Calendar events (spec §21.1)
        Schema::create('m_calendar_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->timestamp('timestart');
            $table->integer('timeduration')->default(0)->comment('seconds');
            $table->string('eventtype', 50)->comment('course, site, user, group, category');
            $table->uuid('course_id')->nullable();
            $table->uuid('group_id')->nullable();
            $table->uuid('category_id')->nullable();
            $table->uuid('user_id')->nullable()->comment('owner');
            $table->string('location', 255)->nullable();
            $table->boolean('visible')->default(true);
            $table->string('repeattype', 20)->nullable()->comment('daily, weekly, monthly');
            $table->integer('repeatinterval')->nullable();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->nullOnDelete();
            $table->foreign('group_id')->references('id')->on('m_groups')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['eventtype', 'timestart']);
        });

        // Structured event log (spec §23.1)
        Schema::create('m_logstore_standard_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('eventname', 200)->nullable();
            $table->string('component', 100)->nullable();
            $table->string('action', 100)->nullable();
            $table->string('target', 100)->nullable();
            $table->uuid('objecttable_id')->nullable();
            $table->string('objecttable', 50)->nullable();
            $table->uuid('contextid')->nullable();
            $table->uuid('userid')->nullable();
            $table->uuid('courseid')->nullable();
            $table->uuid('relateduserid')->nullable();
            $table->text('other')->nullable()->comment('JSON additional data');
            $table->string('origin', 100)->nullable()->comment('web, cli, ws');
            $table->string('ip', 45)->nullable();
            $table->timestamp('timecreated')->useCurrent();

            $table->index(['eventname', 'timecreated']);
            $table->index(['userid', 'timecreated']);
            $table->index(['courseid', 'timecreated']);
        });

        // Scheduled tasks (spec §23.2)
        Schema::create('m_task_scheduled', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('classname', 255);
            $table->string('component', 100);
            $table->integer('minute')->default(-1);
            $table->integer('hour')->default(-1);
            $table->integer('dayofweek')->default(-1);
            $table->integer('dayofmonth')->default(-1);
            $table->integer('month')->default(-1);
            $table->boolean('disabled')->default(false);
            $table->timestamp('nextruntime')->nullable();
            $table->timestamp('lastruntime')->nullable();
            $table->timestamps();
        });

        // Adhoc tasks (spec §23.2)
        Schema::create('m_task_adhoc', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('classname', 255);
            $table->string('component', 100);
            $table->text('customdata')->nullable();
            $table->uuid('userid')->nullable();
            $table->timestamp('nextruntime')->nullable();
            $table->boolean('faildelay')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_task_adhoc');
        Schema::dropIfExists('m_task_scheduled');
        Schema::dropIfExists('m_logstore_standard_log');
        Schema::dropIfExists('m_calendar_events');
        Schema::dropIfExists('m_notifications');
        Schema::dropIfExists('m_messages');
        Schema::dropIfExists('m_message_conversation_members');
        Schema::dropIfExists('m_message_conversations');
    }
};
