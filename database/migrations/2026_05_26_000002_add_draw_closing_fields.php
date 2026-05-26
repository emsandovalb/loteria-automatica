<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draws', function (Blueprint $table): void {
            $table->time('close_time')->nullable()->after('draw_time');
            $table->unsignedInteger('cutoff_minutes_before')->nullable()->default(0)->after('close_time');
            $table->string('timezone')->nullable()->default('America/Costa_Rica')->after('cutoff_minutes_before');
            $table->boolean('closes_at_next_day')->default(false)->after('timezone');
            $table->boolean('is_accepting_requests')->default(true)->after('closes_at_next_day');
        });
    }

    public function down(): void
    {
        Schema::table('draws', function (Blueprint $table): void {
            $table->dropColumn([
                'close_time',
                'cutoff_minutes_before',
                'timezone',
                'closes_at_next_day',
                'is_accepting_requests',
            ]);
        });
    }
};
