<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 10)->unique();
            $table->string('name', 120);
            $table->uuid('hod_id')->nullable();
            $table->timestamps();

            $table->foreign('hod_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('level')->default('DIPLOMA');
            $table->uuid('department_id');
            $table->integer('duration_years');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('department_id')->references('id')->on('departments')->cascadeOnDelete();
        });

        Schema::create('program_modes', function (Blueprint $table) {
            $table->uuid('program_id');
            $table->string('mode', 3); // ODL, DAY, BRD
            $table->primary(['program_id', 'mode']);
            $table->foreign('program_id')->references('id')->on('programs')->cascadeOnDelete();
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 10)->unique();
            $table->string('name', 120);
            $table->uuid('department_id')->nullable();
            $table->timestamps();

            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('program_modes');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('departments');
    }
};
