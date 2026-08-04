<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Custom profile field categories (spec §19.1)
        Schema::create('m_user_info_category', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->integer('sortorder')->default(0);
            $table->timestamps();
        });

        // Custom profile fields (spec §19.1)
        // datatype: text, textarea, checkbox, menu, date
        Schema::create('m_user_info_field', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('category_id');
            $table->string('shortname', 100)->unique()->comment('e.g. necta_index_no, region, mode');
            $table->string('name', 200);
            $table->string('datatype', 20)->default('text')->comment('text, textarea, checkbox, menu, date');
            $table->text('description')->nullable();
            $table->text('defaultdata')->nullable();
            $table->text('param1')->nullable()->comment('e.g. menu options (CSV)');
            $table->integer('sortorder')->default(0);
            $table->boolean('required')->default(false);
            $table->boolean('locked')->default(false);
            $table->boolean('visible')->default(true);
            $table->boolean('signup')->default(false)->comment('show on registration form');
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('m_user_info_category')->cascadeOnDelete();
        });

        // User profile field data (spec §19.1)
        Schema::create('m_user_info_data', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('field_id');
            $table->text('data')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('field_id')->references('id')->on('m_user_info_field')->cascadeOnDelete();
            $table->unique(['user_id', 'field_id']);
        });

        // Email confirmation tokens
        Schema::create('m_email_confirmations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_email_confirmations');
        Schema::dropIfExists('m_user_info_data');
        Schema::dropIfExists('m_user_info_field');
        Schema::dropIfExists('m_user_info_category');
    }
};
