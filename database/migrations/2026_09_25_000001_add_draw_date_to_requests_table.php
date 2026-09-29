<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->date('draw_date')->nullable()->after('draw_id');
            $table->index(['organization_id', 'branch_id', 'draw_id', 'draw_date'], 'requests_draw_date_scope_index');
        });

        // Rows created before this migration stored created_at in UTC (the old app timezone).
        // Their business day is the local date in the draw's timezone.
        $drawTimezones = DB::table('draws')->pluck('timezone', 'id');

        DB::table('requests')
            ->whereNull('draw_date')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($drawTimezones): void {
                foreach ($rows as $row) {
                    $timezone = ($drawTimezones[$row->draw_id] ?? null) ?: 'America/Costa_Rica';

                    DB::table('requests')
                        ->where('id', $row->id)
                        ->update([
                            'draw_date' => Carbon::parse($row->created_at, 'UTC')
                                ->setTimezone($timezone)
                                ->toDateString(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->dropIndex('requests_draw_date_scope_index');
            $table->dropColumn('draw_date');
        });
    }
};
