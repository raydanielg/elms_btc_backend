<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fix tokenable_id column from bigint to varchar(36) for UUID-based models
        DB::statement('ALTER TABLE personal_access_tokens MODIFY COLUMN tokenable_id VARCHAR(36) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE personal_access_tokens MODIFY COLUMN tokenable_id BIGINT NOT NULL');
    }
};
