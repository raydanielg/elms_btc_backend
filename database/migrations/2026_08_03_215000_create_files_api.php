<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Files — content-addressed, referenced by area (spec §18.1)
        Schema::create('m_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('contenthash', 40)->comment('SHA1 of file content — dedup key');
            $table->uuid('context_id')->nullable()->comment('m_contexts.id');
            $table->string('component', 100)->comment('mod_assign, user, course, etc.');
            $table->string('filearea', 100)->comment('submission_files, intro, content, draft');
            $table->uuid('itemid')->nullable();
            $table->string('filepath', 255)->default('/')->comment('virtual path within filearea');
            $table->string('filename', 255);
            $table->bigInteger('filesize')->default(0);
            $table->string('mimetype', 100)->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('storage', 20)->default('local')->comment('local, s3, external');
            $table->string('pathnamehash', 255)->nullable()->comment('hash of full path for quick lookup');
            $table->timestamp('timecreated')->useCurrent();
            $table->timestamps();

            $table->index('contenthash');
            $table->index(['context_id', 'component', 'filearea', 'itemid']);
            $table->index('pathnamehash');
        });

        // File references — aliases to external files (spec §18.2)
        Schema::create('m_files_reference', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('referencehash', 40)->unique();
            $table->string('repositoryid', 100)->nullable();
            $table->text('reference')->comment('external path/URL');
            $table->string('lastsync', 50)->nullable();
            $table->timestamps();
        });

        // Repositories — file sources (spec §18.2)
        Schema::create('m_repositories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 50)->comment('upload, url, serverfiles, googledrive, onedrive, dropbox, s3');
            $table->string('name', 100);
            $table->boolean('active')->default(true);
            $table->integer('sortorder')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_repositories');
        Schema::dropIfExists('m_files_reference');
        Schema::dropIfExists('m_files');
    }
};
