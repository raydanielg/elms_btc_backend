<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix tokenable_id column from bigint to varchar(36) for UUID-based models
        try {
            DB::statement('ALTER TABLE personal_access_tokens MODIFY COLUMN tokenable_id VARCHAR(36) NOT NULL');
        } catch (\Exception $e) {
            // SQLite doesn't support MODIFY COLUMN — recreate the table instead
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('CREATE TABLE personal_access_tokens_temp AS SELECT * FROM personal_access_tokens');
                DB::statement('DROP TABLE personal_access_tokens');
                Schema::create('personal_access_tokens', function (Blueprint $table) {
                    $table->id();
                    $table->morphs('tokenable');
                    $table->string('name');
                    $table->string('token', 64)->unique();
                    $table->text('abilities')->nullable();
                    $table->timestamp('last_used_at')->nullable();
                    $table->timestamp('expires_at')->nullable();
                    $table->timestamps();
                });
                DB::statement('INSERT INTO personal_access_tokens SELECT * FROM personal_access_tokens_temp');
                DB::statement('DROP TABLE personal_access_tokens_temp');
            } else {
                throw $e;
            }
        }
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE personal_access_tokens MODIFY COLUMN tokenable_id BIGINT NOT NULL');
        } catch (\Exception $e) {
            // Best-effort rollback for SQLite — no-op
        }
    }
};
