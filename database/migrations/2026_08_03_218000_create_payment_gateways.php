<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Payment gateways config (spec §22.2)
        Schema::create('m_payment_gateways', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('type', 50)->comment('gepg, manual, stripe, mpesa, selcom');
            $table->boolean('active')->default(true);
            $table->json('config')->nullable()->comment('API keys, endpoints, etc.');
            $table->integer('sortorder')->default(0);
            $table->timestamps();
        });

        // Add gateway_id to payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->uuid('gateway_id')->nullable()->after('control_number');
            $table->string('gateway_reference', 100)->nullable()->after('gateway_id');
            $table->text('gateway_response')->nullable()->after('gateway_reference');
            $table->foreign('gateway_id')->references('id')->on('m_payment_gateways')->nullOnDelete();
        });

        // Add academic_year to applications for cohort assignment
        Schema::table('applications', function (Blueprint $table) {
            $table->string('academic_year', 9)->nullable()->after('mode');
            $table->uuid('cohort_id')->nullable()->after('status');
            $table->timestamp('accepted_at')->nullable()->after('reviewed_by');
            $table->foreign('cohort_id')->references('id')->on('m_cohorts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['cohort_id']);
            $table->dropColumn(['academic_year', 'cohort_id', 'accepted_at']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['gateway_id']);
            $table->dropColumn(['gateway_id', 'gateway_reference', 'gateway_response']);
        });
        Schema::dropIfExists('m_payment_gateways');
    }
};
