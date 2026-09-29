<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outgoing_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intake_request_id')->nullable()->constrained('requests')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel_type')->index();
            $table->string('to_identifier');
            $table->string('message_type')->index();
            $table->text('text');
            $table->string('status')->default('pending')->index();
            $table->text('error')->nullable();
            $table->string('external_message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['intake_request_id', 'created_at']);
        });

        Schema::table('requests', function (Blueprint $table): void {
            $table->timestamp('awaiting_reply_since')->nullable()->after('notes')->index();
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->dropIndex(['awaiting_reply_since']);
            $table->dropColumn('awaiting_reply_since');
        });

        Schema::dropIfExists('outgoing_messages');
    }
};
