<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->uuid('category_id')->nullable()->after('subject_id');
            $table->string('fullname', 200)->nullable()->after('title');
            $table->string('shortname', 100)->nullable()->after('fullname');
            $table->string('format', 30)->default('topics')->after('is_active');
            $table->date('startdate')->nullable()->after('format');
            $table->date('enddate')->nullable()->after('startdate');
            $table->boolean('enablecompletion')->default(false)->after('enddate');
            $table->string('groupmode', 20)->default('NOGROUPS')->after('enablecompletion');

            $table->foreign('category_id')->references('id')->on('m_course_categories')->nullOnDelete();
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn([
                'category_id', 'fullname', 'shortname', 'format',
                'startdate', 'enddate', 'enablecompletion', 'groupmode',
            ]);
        });
    }
};
