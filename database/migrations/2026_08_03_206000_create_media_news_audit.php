<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_gallery', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 5)->default('PHOTO'); // PHOTO, VIDEO
            $table->string('title', 150)->nullable();
            $table->string('url', 255);
            $table->uuid('uploaded_by');
            $table->timestamps();

            $table->foreign('uploaded_by')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('news_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 150);
            $table->text('body')->nullable();
            $table->string('image_url', 255)->nullable();
            $table->date('event_date')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('actor_id')->nullable();
            $table->string('action', 100);
            $table->string('entity', 50)->nullable();
            $table->uuid('entity_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('news_events');
        Schema::dropIfExists('media_gallery');
    }
};
