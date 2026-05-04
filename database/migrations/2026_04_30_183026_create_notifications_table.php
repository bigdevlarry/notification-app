<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('recipient_address')->nullable();
            $table->uuid('batch_id')->nullable();
            $table->string('idempotency_key')->unique();
            $table->string('channel');
            $table->text('content');
            $table->string('priority');
            $table->string('status')->default('pending');
            $table->string('provider_message_id')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('recipient_id');
            $table->index('status');
            $table->index('channel');
            $table->index('priority');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
