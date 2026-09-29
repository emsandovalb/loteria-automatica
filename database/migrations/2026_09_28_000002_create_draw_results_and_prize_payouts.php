<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draws', function (Blueprint $table): void {
            $table->decimal('prize_multiplier', 8, 2)->default(80)->after('status');
            // Null means the draw does not offer reventado bets.
            $table->decimal('reventado_multiplier', 8, 2)->nullable()->after('prize_multiplier');
        });

        Schema::table('requests', function (Blueprint $table): void {
            $table->decimal('reventado_amount', 12, 2)->nullable()->after('detected_amount');
        });

        Schema::create('draw_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('draw_id')->constrained()->cascadeOnDelete();
            $table->date('draw_date');
            $table->string('winning_number', 2);
            $table->boolean('reventado_hit')->default(false);
            // Copied from the draw when the result is entered, so later setting changes never alter prizes.
            $table->decimal('prize_multiplier', 8, 2);
            $table->decimal('reventado_multiplier', 8, 2)->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('winners_notified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['draw_id', 'draw_date']);
            $table->index(['organization_id', 'draw_date']);
        });

        Schema::create('prize_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('draw_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intake_request_id')->unique()->constrained('requests')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 2);
            $table->decimal('bet_amount', 12, 2);
            $table->decimal('prize_amount', 12, 2);
            $table->decimal('reventado_amount', 12, 2)->default(0);
            $table->decimal('reventado_prize', 12, 2)->default(0);
            $table->decimal('total_prize', 12, 2);
            $table->string('status')->default('pending')->index();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'branch_id', 'status']);
        });

        Schema::table('branch_daily_closures', function (Blueprint $table): void {
            $table->decimal('total_prizes_amount', 12, 2)->default(0)->after('total_amount_confirmed');
        });
    }

    public function down(): void
    {
        Schema::table('branch_daily_closures', function (Blueprint $table): void {
            $table->dropColumn('total_prizes_amount');
        });

        Schema::dropIfExists('prize_payouts');
        Schema::dropIfExists('draw_results');

        Schema::table('requests', function (Blueprint $table): void {
            $table->dropColumn('reventado_amount');
        });

        Schema::table('draws', function (Blueprint $table): void {
            $table->dropColumn(['prize_multiplier', 'reventado_multiplier']);
        });
    }
};
